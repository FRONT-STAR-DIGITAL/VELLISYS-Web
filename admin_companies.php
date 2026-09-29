<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if (isset($_GET['new']) || isset($_GET['signup'])) {
    $to = 'admin_company_new.php';
    if (!empty($_GET['signup'])) {
        $to .= '?signup=' . (int) $_GET['signup'];
    }
    redirect($to);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $tid = (int) post('company_id');

    if ($action === 'create_testing') {
        $ownerId = (int) post('testing_owner_id');
        $made = sales_create_testing_company([
            'name' => post('name'),
            'contact_name' => post('contact_name'),
            'phone' => post('phone'),
            'city' => post('city'),
            'address' => post('address'),
            'nature_of_business' => post('nature_of_business'),
            'testing_owner_id' => $ownerId,
            'user_email' => post('user_email'),
            'days' => (int) post('days') ?: sales_testing_default_days(),
            'enabled_kinds' => $_POST['enabled_kinds'] ?? [],
        ], (int) $user['id'], true);
        if (empty($made['ok'])) {
            $error = (string) ($made['error'] ?? 'Could not create testing company.');
        } else {
            flash(
                $made['name'] . ' created. Desk login '
                . $made['email'] . ' - password ' . $made['password'] . '.'
            );
            redirect('admin_company.php?id=' . (int) $made['company_id']);
        }
    } elseif ($action === 'testing_extend') {
        $days = (int) post('days');
        if ($days === 0) {
            $days = 7;
        }
        $done = sales_adjust_testing_days($tid, $days);
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Testing time updated to ' . format_date((string) ($done['expires_at'] ?? '')), empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_companies.php#testing');
    } elseif ($action === 'testing_set_expiry') {
        $done = sales_set_testing_expiry($tid, post('testing_expires_at'));
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : 'Testing end date saved.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_companies.php#testing');
    } elseif ($action === 'testing_set_login') {
        $done = sales_testing_set_login($tid, post('desk_email'), post('reset_password') !== '');
        flash(empty($done['ok']) ? ($done['error'] ?? 'Failed') : ('Desk login updated to ' . ($done['email'] ?? '') . (post('reset_password') !== '' ? ' · password Folio2026' : '')), empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_companies.php#testing');
    } elseif ($action === 'testing_promote') {
        // Promote needs email + keep/clean choices on the company page.
        redirect('admin_company.php?id=' . $tid . '#promote-onboard');
    } elseif ($action === 'testing_delete') {
        $confirm = trim(post('delete_confirm'));
        $co = db_one('SELECT name FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$tid]);
        if (!$co || $confirm !== (string) $co['name']) {
            flash('Type the company name exactly to delete.', 'err');
            redirect('admin_companies.php#testing');
        }
        $gone = platform_delete_company($tid);
        flash(empty($gone['ok']) ? ($gone['error'] ?? 'Could not delete.') : ($co['name'] . ' deleted.'), empty($gone['ok']) ? 'err' : 'ok');
        redirect('admin_companies.php#testing');
    }
}

$companies = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users,
            (SELECT COUNT(*) FROM documents d WHERE d.company_id = c.id) AS docs
     FROM companies c
     WHERE COALESCE(c.testing_mode, 0) = 0
     ORDER BY c.id DESC'
);
$testing = function_exists('admin_testing_companies') ? admin_testing_companies() : [];
$agents = function_exists('sales_agents') ? sales_agents(true) : [];
$from = desk_now()->modify('-30 days')->format('Y-m-d');
$to = desk_now()->format('Y-m-d');
$presence = [];
foreach (platform_company_presence() as $row) {
    $presence[(int) $row['id']] = $row;
}

layout_admin_start('Companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Companies</h1>
    <p class="lede">Who is online, who signed in last, how heavily they use the desk, and when their term renews.</p>
  </div>
  <a class="btn" href="<?= h(url('admin_company_new.php')) ?>"><?= icon('plus') ?>New company</a>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="card" id="live">
  <div class="company-table-head">
    <h2 style="margin:0">Live and onboarding</h2>
    <?php if ($companies): ?>
      <label class="company-table-search">
        <span class="sr-only">Search live companies</span>
        <input type="search" data-table-filter="live-companies" placeholder="Search companies..." autocomplete="off">
      </label>
    <?php endif; ?>
  </div>
  <?php if (!$companies): ?>
    <p class="empty">No companies yet. <a href="<?= h(url('admin_company_new.php')) ?>">Create the first one from scratch</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid" data-filterable="live-companies">
      <thead>
        <tr>
          <th>Company</th>
          <th>Country</th>
          <th>Status</th>
          <th>Online</th>
          <th>Last sign-in</th>
          <th>Paid term</th>
          <th>Renews</th>
          <th>They pay</th>
          <th>Users</th>
          <th>Use (30d)</th>
          <th>Onboard</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c):
            $p = $presence[(int) $c['id']] ?? [];
            $onlineN = (int) ($p['online_users'] ?? 0);
            $use = platform_usage_counts((int) $c['id'], $from, $to);
            $onboard = company_onboard_progress($c);
            $searchBlob = strtolower(trim(
                (string) $c['name'] . ' ' . company_loc($c, 'country') . ' ' . (string) $c['status']
            ));
            ?>
          <tr data-search="<?= h($searchBlob) ?>">
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td><?= company_loc($c, 'country') !== '' ? h(company_loc($c, 'country')) : '<span class="muted">-</span>' ?></td>
            <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
            <td><?php if ($onlineN > 0): ?><span class="pill"><?= $onlineN ?> online</span><?php else: ?><span class="muted">Off</span><?php endif; ?></td>
            <td class="mono"><?= h(format_when($p['last_login_at'] ?? null)) ?></td>
            <td><?= h(company_term_label($c)) ?></td>
            <td class="<?= company_expiry_state($c) === 'expired' ? 'expiry-expired' : (company_expiry_state($c) === 'soon' ? 'expiry-soon' : '') ?>"><?= !empty($c['expires_at']) ? h(format_date((string) $c['expires_at'])) : h(company_remaining_phrase($c)) ?></td>
            <td class="mono"><?= company_fee_amount($c) > 0 ? h(platform_money_company_fee($c, 'amount')) : '-' ?></td>
            <td class="mono"><?= (int) $c['users'] ?> / <?= (int) company_user_limit($c) ?></td>
            <td class="mono"><?= (int) $use['score'] ?></td>
            <td class="mono"><?= (int) $onboard['done'] ?> / <?= (int) $onboard['total'] ?></td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
                <a class="btn ghost sm" href="<?= h(url('admin_desk.php?id=' . $c['id'])) ?>"><?= icon('desk', 14) ?>Desk</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="hint">Use in the last 30 days is sheets issued plus desk events. Online means a sign-in was seen in the last <?= (int) platform_online_window_minutes() ?> minutes. Amounts use the currency in Settings.</p>
  <?php endif; ?>
</div>

<div class="card" id="testing" style="margin-top:20px">
  <div class="company-table-head">
    <div>
      <h2 style="margin:0">Testing mode</h2>
      <p class="lede" style="margin:6px 0 0">Trial desks. Default 2 weeks.</p>
    </div>
    <?php if ($testing): ?>
      <label class="company-table-search">
        <span class="sr-only">Search testing companies</span>
        <input type="search" data-table-filter="testing-companies" placeholder="Search testing companies..." autocomplete="off">
      </label>
    <?php endif; ?>
  </div>

  <?php if (!$testing): ?>
    <p class="empty">No companies in testing mode right now.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid testing-companies-table" data-filterable="testing-companies">
      <thead>
        <tr>
          <th>Company</th>
          <th>Agent</th>
          <th>Desk login</th>
          <th>Ends</th>
          <th>Time left</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($testing as $c):
            $expired = company_testing_expired($c);
            $expVal = '';
            if (!empty($c['testing_expires_at'])) {
                $expVal = substr((string) $c['testing_expires_at'], 0, 10);
            }
            $cid = (int) $c['id'];
            $searchBlob = strtolower(trim(
                (string) $c['name'] . ' ' . (string) ($c['owner_name'] ?? '') . ' ' . (string) ($c['desk_email'] ?? '')
            ));
            ?>
          <tr data-search="<?= h($searchBlob) ?>">
            <td>
              <a href="<?= h(url('admin_company.php?id=' . $cid)) ?>"><strong><?= h((string) $c['name']) ?></strong></a>
              <?php if ($expired): ?><span class="pill bad">Expired</span><?php else: ?><span class="pill warn">Testing</span><?php endif; ?>
            </td>
            <td><?= h((string) ($c['owner_name'] ?: '-')) ?></td>
            <td class="mono"><?= h((string) ($c['desk_email'] ?? '-')) ?></td>
            <td class="mono"><?= $expVal !== '' ? h(format_date($expVal)) : '-' ?></td>
            <td><?= h(company_testing_remaining_label($c)) ?></td>
            <td class="row-actions">
              <div class="actions testing-row-actions">
                <a class="btn sm" href="<?= h(url('admin_company.php?id=' . $cid)) ?>"><?= icon('eye', 14) ?>Open</a>
                <a class="btn ghost sm" href="<?= h(url('sales_desk.php?id=' . $cid . '&go=1')) ?>"><?= icon('desk', 14) ?>Desk</a>
                <form method="post" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="testing_extend">
                  <input type="hidden" name="company_id" value="<?= $cid ?>">
                  <input type="hidden" name="days" value="7">
                  <button class="btn ghost sm" type="submit">+7 days</button>
                </form>
                <form method="post" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="testing_extend">
                  <input type="hidden" name="company_id" value="<?= $cid ?>">
                  <input type="hidden" name="days" value="-7">
                  <button class="btn ghost sm" type="submit">-7 days</button>
                </form>
                <form method="post" class="inline-form testing-expiry-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="testing_set_expiry">
                  <input type="hidden" name="company_id" value="<?= $cid ?>">
                  <input type="date" name="testing_expires_at" value="<?= h($expVal) ?>" required aria-label="End date">
                  <button class="btn ghost sm" type="submit">Set end</button>
                </form>
                <a class="btn sm" href="<?= h(url('admin_company.php?id=' . $cid . '#promote-onboard')) ?>">Onboard</a>
                <form method="post" class="inline-form testing-delete-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="testing_delete">
                  <input type="hidden" name="company_id" value="<?= $cid ?>">
                  <input type="text" name="delete_confirm" placeholder="Type name" aria-label="Confirm delete" required autocomplete="off">
                  <button class="btn danger sm" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="hint">On a phone, scroll sideways for every action. Preferred client email · password Folio2026.</p>
  <?php endif; ?>

  <details class="sales-test-create" style="margin-top:20px">
    <summary class="btn ghost"><?= icon('plus', 14) ?>Create testing company</summary>
    <form method="post" class="pad-form" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_testing">
      <div class="form-grid">
        <div>
          <label for="t_name">Business name</label>
          <input id="t_name" name="name" required value="<?= h(post('name')) ?>">
        </div>
        <div>
          <label for="t_owner">Sales agent</label>
          <select id="t_owner" name="testing_owner_id" required>
            <option value="">Choose agent</option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (int) post('testing_owner_id') === (int) $a['id'] ? 'selected' : '' ?>><?= h((string) $a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="t_contact">Contact person</label>
          <input id="t_contact" name="contact_name" required value="<?= h(post('contact_name')) ?>">
        </div>
        <div>
          <label for="t_phone">Phone</label>
          <input id="t_phone" name="phone" inputmode="tel" required value="<?= h(post('phone')) ?>">
        </div>
        <div>
          <label for="t_email">Client login email</label>
          <input id="t_email" name="user_email" type="email" required value="<?= h(post('user_email')) ?>" placeholder="client@theircompany.com">
          <p class="hint" style="margin:4px 0 0">Preferred email the client will sign in with. Password: Folio2026.</p>
        </div>
        <div>
          <label for="t_city">City</label>
          <input id="t_city" name="city" value="<?= h(post('city')) ?>">
        </div>
        <div>
          <label for="t_days">Days (default 14)</label>
          <input id="t_days" name="days" type="number" min="1" max="90" value="<?= h(post('days') !== '' ? post('days') : '14') ?>">
        </div>
        <div class="full">
          <label for="t_address">Address</label>
          <input id="t_address" name="address" value="<?= h(post('address')) ?>">
        </div>
        <div class="full">
          <label for="t_nature">Nature of business</label>
          <input id="t_nature" name="nature_of_business" value="<?= h(post('nature_of_business')) ?>">
        </div>
      </div>
      <?php if (function_exists('render_client_fields_admin')) {
          render_client_fields_admin();
      } ?>
      <?php render_desk_kinds_fields(); ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Create in testing mode</button>
      </div>
    </form>
  </details>
</div>
<script>
(function(){
  document.querySelectorAll('[data-table-filter]').forEach(function(input){
    var key = input.getAttribute('data-table-filter');
    var table = document.querySelector('table[data-filterable="' + key + '"]');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-search]');
    input.addEventListener('input', function(){
      var q = (input.value || '').toLowerCase().trim();
      rows.forEach(function(row){
        var hay = row.getAttribute('data-search') || '';
        row.hidden = q !== '' && hay.indexOf(q) === -1;
      });
    });
  });
})();
</script>
<?php layout_end(); ?>
