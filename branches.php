<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_branches();
$admin = is_desk_admin($user);
$cid = current_company_id();
$error = '';
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? db_one('SELECT * FROM branches WHERE id = ? AND company_id = ?', 'ii', [$editId, $cid]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$admin) {
        flash('Only the company admin can change branches.', 'err');
        redirect('branches.php');
    }
    $action = post('action');
    if ($action === 'save_branch') {
        $id = (int) post('branch_id');
        $logoPath = '';
        if ($id > 0) {
            $taken = store_branch_logo_upload($cid, $id);
            if (empty($taken['ok'])) {
                $error = (string) ($taken['error'] ?? 'Could not save the branch logo.');
            } else {
                $logoPath = (string) ($taken['path'] ?? '');
            }
        }
        if ($error === '') {
            $saved = save_named_branch([
                'name' => post('name'),
                'address' => post('address'),
                'city' => post('city'),
                'phone' => post('phone'),
                'email' => post('email'),
                'brand_color' => post('brand_color'),
                'brand_accent' => post('brand_accent'),
                'logo_path' => $logoPath,
            ], $id > 0 ? $id : null);
            if (empty($saved['ok'])) {
                $error = (string) ($saved['error'] ?? 'Could not save that branch.');
            } else {
                $newId = (int) $saved['id'];
                if ($id < 1 && !empty($_FILES['logo']['tmp_name'])) {
                    $taken = store_branch_logo_upload($cid, $newId);
                    if (!empty($taken['ok']) && !empty($taken['path'])) {
                        db_exec('UPDATE branches SET logo_path = ? WHERE id = ? AND company_id = ?', 'sii', [$taken['path'], $newId, $cid]);
                    }
                }
                record_company_activity('branch', ($id > 0 ? 'Updated ' : 'Added ') . post('name'), [
                    'detail' => trim(post('city') . ' ' . post('address')),
                    'href' => 'branches.php',
                    'ref_type' => 'branch',
                    'ref_id' => $newId,
                    'branch_id' => $newId,
                ]);
                flash($id > 0 ? 'Branch updated.' : 'Branch added.');
                redirect('branches.php');
            }
        }
    } elseif ($action === 'delete_branch') {
        $id = (int) post('branch_id');
        $row = db_one('SELECT name FROM branches WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if ($row && delete_named_branch($id)) {
            record_company_activity('branch', 'Removed ' . $row['name'], [
                'href' => 'branches.php',
                'ref_type' => 'branch',
                'ref_id' => $id,
                'branch_id' => 0,
            ]);
            flash($row['name'] . ' was removed. Staff there now sit at Head office.');
        } else {
            flash('That branch could not be removed.', 'err');
        }
        redirect('branches.php');
    } elseif ($action === 'assign_staff') {
        $uid = (int) post('user_id');
        $bid = post('branch_id');
        if (assign_user_branch($uid, $bid)) {
            $member = db_one('SELECT name FROM users WHERE id = ? AND company_id = ?', 'ii', [$uid, $cid]);
            record_company_activity('branch', 'Assigned ' . ($member['name'] ?? 'staff') . ' to ' . company_branch_label((int) $bid), [
                'href' => 'branches.php',
                'ref_type' => 'user',
                'ref_id' => $uid,
                'branch_id' => $bid,
            ]);
            flash('Staff branch updated.');
        } else {
            flash('That login is not on this desk.', 'err');
        }
        redirect('branches.php');
    }
}

$tab = (($_GET['tab'] ?? '') === 'performance') ? 'performance' : 'list';
$branches = company_all_branches();
$members = db_all("SELECT id, name, job_title, email, role, access, branch_id FROM users WHERE company_id = ? AND role <> 'platform' ORDER BY role = 'admin' DESC, name", 'i', [$cid]);
$seats = company_user_limit();
$branchCap = company_location_limit();
$locations = company_location_count($cid);
$canAddBranch = $admin && company_can_add_named_branch();
$activityByBranch = [];
$perf = ['overall' => branch_performance_blank(), 'branches' => []];
$period = period_range();
$showProfit = !function_exists('user_can_see_profit') || user_can_see_profit();
if ($tab === 'performance') {
    $from = $period['from'] !== '' ? $period['from'] : '1970-01-01';
    $to = $period['to'] !== '' ? $period['to'] : today();
    $perf = branch_performance_for_range($from, $to);
} elseif ($admin) {
    foreach ($branches as $b) {
        $bid = (int) ($b['id'] ?? 0);
        $raw = company_activities(['branch_id' => $bid, 'limit' => 12]);
        $seen = [];
        $compact = [];
        foreach ($raw as $row) {
            $key = strtolower(trim((string) ($row['title'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $compact[] = $row;
            if (count($compact) >= 3) {
                break;
            }
        }
        $activityByBranch[$bid] = $compact;
    }
}

layout_start('Branches', $user);
?>
<div class="branch-page">
<div class="page-head">
  <div>
    <h1><?= icon('pin') ?>Branches</h1>
    <p class="lede"><?php if ($tab === 'performance'): ?>
      Income, spend and collections for each branch, plus overall. Income share is that branch's contribution to invoiced net.
    <?php else: ?>
      Head office uses the company address in Settings. Named branches print their own address. This package allows up to <?= (int) $branchCap ?> branch<?= $branchCap === 1 ? '' : 'es' ?>, including Head office. Several people can share a branch. Vellisys sets how many users this desk has (<?= (int) $seats ?> of <?= (int) plan_user_limit_max() ?> on <?= h(company_plan_label()) ?>).
    <?php endif; ?></p>
  </div>
  <?php if ($admin): ?>
    <div class="actions">
      <a class="btn ghost" href="<?= h(url('activities.php')) ?>"><?= icon('clock', 16) ?>All activity</a>
      <a class="btn ghost" href="<?= h(url('settings.php#company')) ?>"><?= icon('building', 16) ?>Company address</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php render_branch_subnav($tab); ?>

<?php if ($tab === 'performance'):
    $overall = $perf['overall'];
    $perfRows = $perf['branches'];
    $hasIncome = false;
    foreach ($perfRows as $row) {
        if ((float) $row['income'] > 0) {
            $hasIncome = true;
            break;
        }
    }
    ?>
<?php render_filters('branches.php', ['tab' => 'performance']); ?>
<p class="hint" style="margin:-8px 0 16px">
  Showing <?= $period['from'] ? h(format_date($period['from']) . ' - ' . format_date($period['to'])) : 'all dates' ?>.
</p>
<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Overall income</span><strong><?= h(ugx($overall['income'])) ?></strong></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Overall collected</span><strong><?= h(ugx($overall['cash_in'])) ?></strong></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Overall expenses</span><strong><?= h(ugx($overall['expenses'])) ?></strong></div>
  <?php if ($showProfit): ?>
  <div class="card stat"><?= icon('package', 20) ?><span>Overall profit</span><strong><?= h(ugx($overall['profit'])) ?></strong><em>Sell minus buy</em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Overall net</span><strong><?= h(ugx($overall['net'])) ?></strong></div>
  <?php endif; ?>
</div>
<div class="chart-grid">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('invoice', 16) ?>Income by branch</h2></div>
    <?php if (!$hasIncome): ?>
      <p class="empty">No invoiced income in this period.</p>
    <?php else: ?>
      <canvas id="chart-branch-income"></canvas>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses by branch</h2></div>
    <?php
    $hasExp = false;
    foreach ($perfRows as $row) {
        if ((float) $row['expenses'] > 0) {
            $hasExp = true;
            break;
        }
    }
    ?>
    <?php if (!$hasExp): ?>
      <p class="empty">No operating expenses in this period.</p>
    <?php else: ?>
      <canvas id="chart-branch-expense"></canvas>
    <?php endif; ?>
  </div>
</div>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>All branches</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Branch</th>
        <th class="right">Income</th>
        <th class="right">Share</th>
        <th class="right">Collected</th>
        <th class="right">Expenses</th>
        <?php if ($showProfit): ?>
          <th class="right">Profit</th>
          <th class="right">Net</th>
        <?php endif; ?>
        <th class="right">Sheets</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Overall</strong></td>
        <td class="right mono"><?= h(ugx($overall['income'])) ?></td>
        <td class="right mono">100%</td>
        <td class="right mono"><?= h(ugx($overall['cash_in'])) ?></td>
        <td class="right mono"><?= h(ugx($overall['expenses'])) ?></td>
        <?php if ($showProfit): ?>
          <td class="right mono"><?= h(ugx($overall['profit'])) ?></td>
          <td class="right mono"><?= h(ugx($overall['net'])) ?></td>
        <?php endif; ?>
        <td class="right mono"><?= (int) $overall['docs'] ?></td>
      </tr>
      <?php foreach ($perfRows as $row): ?>
        <tr>
          <td><?= h((string) $row['name']) ?><?= !empty($row['is_head']) ? ' <span class="hint">Head office</span>' : '' ?></td>
          <td class="right mono"><?= h(ugx($row['income'])) ?></td>
          <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['income_share'], 1, '.', ''), '0'), '.')) ?>%</td>
          <td class="right mono"><?= h(ugx($row['cash_in'])) ?></td>
          <td class="right mono"><?= h(ugx($row['expenses'])) ?></td>
          <?php if ($showProfit): ?>
            <td class="right mono"><?= h(ugx($row['profit'])) ?></td>
            <td class="right mono"><?= h(ugx($row['net'])) ?></td>
          <?php endif; ?>
          <td class="right mono"><?= (int) $row['docs'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php foreach ($perfRows as $row): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-head"><h2><?= icon(!empty($row['is_head']) ? 'building' : 'pin', 16) ?><?= h((string) $row['name']) ?></h2></div>
    <div class="stats" style="margin:0">
      <div class="card stat"><?= icon('invoice', 20) ?><span>Income</span><strong><?= h(ugx($row['income'])) ?></strong><em><?= h(rtrim(rtrim(number_format((float) $row['income_share'], 1, '.', ''), '0'), '.')) ?>% of overall</em></div>
      <div class="card stat"><?= icon('receipt', 20) ?><span>Collected</span><strong><?= h(ugx($row['cash_in'])) ?></strong></div>
      <div class="card stat"><?= icon('expense', 20) ?><span>Expenses</span><strong><?= h(ugx($row['expenses'])) ?></strong><em><?= h(rtrim(rtrim(number_format((float) $row['expense_share'], 1, '.', ''), '0'), '.')) ?>% of spend</em></div>
      <?php if ($showProfit): ?>
        <div class="card stat"><?= icon('package', 20) ?><span>Profit</span><strong><?= h(ugx($row['profit'])) ?></strong></div>
        <div class="card stat"><?= icon('reports', 20) ?><span>Net</span><strong><?= h(ugx($row['net'])) ?></strong></div>
      <?php endif; ?>
      <div class="card stat"><?= icon('clients', 20) ?><span>Outstanding</span><strong><?= h(ugx($row['outstanding'])) ?></strong></div>
    </div>
    <p class="hint" style="margin:12px 22px 16px"><?= (int) $row['invoices'] ?> invoices · <?= (int) $row['receipts'] ?> receipts · <?= (int) $row['expenses_n'] ?> expenses · <?= (int) $row['quotes'] ?> quotations · supplier payments <?= h(ugx($row['cash_out'])) ?></p>
  </div>
<?php endforeach; ?>
<?php
    $payload = json_encode([
        'incomeLabels' => array_column($perfRows, 'name'),
        'incomeValues' => array_map(static fn ($r) => (float) $r['income'], $perfRows),
        'expenseValues' => array_map(static fn ($r) => (float) $r['expenses'], $perfRows),
        'color' => brand_color(),
        'currency' => default_currency(),
    ], JSON_UNESCAPED_UNICODE);
    $extraJs = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d = ' . $payload . ';
  var brand = d.color || "#82B440";
  var palette = [brand, "#1f3a12", "#b42318", "#1E4EFF", "#c4a35a", "#4a6fa5", "#66705f", "#82B440"];
  function pie(id, values) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    new Chart(el, {
      type: "pie",
      data: { labels: d.incomeLabels, datasets: [{ data: values, backgroundColor: palette }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  pie("chart-branch-income", d.incomeValues);
  pie("chart-branch-expense", d.expenseValues);
})();
</script>';
    echo '</div>';
    layout_end($extraJs);
    return;
endif; ?>

<div class="branch-list">
  <?php foreach ($branches as $b):
      $bid = (int) ($b['id'] ?? 0);
      $staff = branch_staff($bid);
      $isHead = !empty($b['is_head']);
      $recent = $activityByBranch[$bid] ?? [];
      $addr = trim((string) ($b['address'] ?? ''));
      $city = trim((string) ($b['city'] ?? ''));
      $phone = trim((string) ($b['phone'] ?? ''));
      $email = trim((string) ($b['email'] ?? ''));
      $place = trim($addr . ($addr !== '' && $city !== '' ? ', ' : '') . $city);
      $bColor = trim((string) ($b['brand_color'] ?? ''));
      $bLogo = ltrim((string) ($b['logo_path'] ?? ''), '/');
      ?>
    <article class="card branch-card">
      <header class="branch-card-top">
        <div class="branch-card-title">
          <?= icon($isHead ? 'building' : 'pin', 18) ?>
          <div>
            <h2><?= h((string) $b['name']) ?></h2>
            <?php if ($isHead): ?><p>Company address from Settings</p>
            <?php elseif ($bColor !== '' || $bLogo !== ''): ?>
              <p>
                <?php if ($bColor !== ''): ?><span class="branch-brand-swatch" style="background:<?= h($bColor) ?>" title="Branch colour"></span> Own branding<?php else: ?>Own branding<?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!$isHead && $bLogo !== '' && is_file(ROOT_PATH . '/' . $bLogo)): ?>
          <img class="branch-logo-preview" src="<?= h(url($bLogo)) ?>" alt="">
        <?php endif; ?>
      </header>
      <dl class="branch-meta">
        <div>
          <dt>Address</dt>
          <dd><?= h($place !== '' ? $place : 'No address yet') ?></dd>
        </div>
        <div>
          <dt>Phone</dt>
          <dd><?= h($phone !== '' ? $phone : 'No phone') ?></dd>
        </div>
        <div>
          <dt>Email</dt>
          <dd class="branch-meta-email"><?= h($email !== '' ? $email : 'No email') ?></dd>
        </div>
      </dl>
      <div class="branch-staff">
        <span>Staff</span>
        <?php if (!$staff): ?>
          <p><?= $isHead ? 'Unassigned logins sit here.' : 'Nobody assigned yet.' ?></p>
        <?php else: ?>
          <ul>
            <?php foreach ($staff as $m): ?>
              <li><?= h((string) $m['name']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <?php if ($admin): ?>
        <div class="branch-card-actions">
          <a class="btn ghost sm" href="<?= h(url('activities.php?branch=' . $bid)) ?>"><?= icon('clock', 14) ?>Activity</a>
          <?php if ($isHead): ?>
            <a class="btn ghost sm" href="<?= h(url('settings.php#company')) ?>"><?= icon('pencil', 14) ?>Edit address</a>
          <?php else: ?>
            <a class="btn ghost sm" href="<?= h(url('branches.php?edit=' . $bid . '#branch-form')) ?>"><?= icon('pencil', 14) ?>Edit</a>
            <form method="post" onsubmit="return confirm('Remove this branch? Staff move to Head office.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_branch">
              <input type="hidden" name="branch_id" value="<?= $bid ?>">
              <button class="btn danger sm" type="submit"><?= icon('trash', 14) ?>Remove</button>
            </form>
          <?php endif; ?>
        </div>
        <?php if ($recent): ?>
          <div class="branch-activity">
            <h3>Recent</h3>
            <?php foreach ($recent as $row):
                $href = trim((string) ($row['href'] ?? ''));
                $tag = $href !== '' ? 'a' : 'div';
                ?>
              <<?= $tag ?><?= $href !== '' ? ' href="' . h(url($href)) . '"' : '' ?> class="branch-activity-row">
                <strong><?= h((string) $row['title']) ?></strong>
                <span><?= h(trim((string) ($row['actor_name'] ?? ''))) ?><?= !empty($row['actor_name']) ? ' - ' : '' ?><?= h(format_date(substr((string) $row['occurred_at'], 0, 10))) ?></span>
              </<?= $tag ?>>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($admin): ?>
  <div class="card branch-assign-card">
    <h2><?= icon('user', 16) ?>Assign staff</h2>
    <?php if (!$members): ?>
      <p class="empty">Add logins in Settings, then assign them here.</p>
    <?php else: ?>
      <form method="post" class="branch-assign">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign_staff">
        <div>
          <label for="user_id">Person</label>
          <select id="user_id" name="user_id" required>
            <?php foreach ($members as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= h($m['name']) ?> - <?= h(company_branch_label((int) ($m['branch_id'] ?? 0))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="assign_branch">Branch</label>
          <select id="assign_branch" name="branch_id">
            <?php render_branch_options(); ?>
          </select>
        </div>
        <button class="btn" type="submit">Save</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($edit || $canAddBranch): ?>
  <form class="card branch-form" method="post" id="branch-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_branch">
    <?php if ($edit): ?>
      <input type="hidden" name="branch_id" value="<?= (int) $edit['id'] ?>">
    <?php endif; ?>
    <h2><?= icon($edit ? 'pencil' : 'plus', 16) ?><?= $edit ? 'Edit ' . h((string) $edit['name']) : 'Add a named branch' ?></h2>
    <p class="lede">Use a city or shop name. That address and branding print on documents from the branch. <?= (int) $locations ?> of <?= (int) $branchCap ?> branch slots in use.</p>
    <div class="branch-form-grid">
      <div>
        <label for="name">Branch name</label>
        <input id="name" name="name" required value="<?= h((string) ($edit['name'] ?? post('name'))) ?>" placeholder="Kampala shop">
      </div>
      <div>
        <label for="city">City</label>
        <input id="city" name="city" value="<?= h((string) ($edit['city'] ?? post('city'))) ?>">
      </div>
      <div class="branch-form-wide">
        <label for="address">Address</label>
        <input id="address" name="address" value="<?= h((string) ($edit['address'] ?? post('address'))) ?>">
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= h((string) ($edit['phone'] ?? post('phone'))) ?>">
      </div>
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= h((string) ($edit['email'] ?? post('email'))) ?>">
      </div>
      <div>
        <label for="brand_color">Brand colour</label>
        <input id="brand_color" name="brand_color" type="color" value="<?= h((string) (($edit['brand_color'] ?? '') !== '' ? $edit['brand_color'] : '#1E4EFF')) ?>">
      </div>
      <div>
        <label for="brand_accent">Accent colour</label>
        <input id="brand_accent" name="brand_accent" type="color" value="<?= h((string) (($edit['brand_accent'] ?? '') !== '' ? $edit['brand_accent'] : '#C6A15B')) ?>">
      </div>
      <div class="branch-form-wide">
        <label for="logo">Branch logo <?= $edit ? '(optional replace)' : '(optional)' ?></label>
        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
        <?php
          $logoRel = ltrim((string) ($edit['logo_path'] ?? ''), '/');
          if ($logoRel !== '' && is_file(ROOT_PATH . '/' . $logoRel)):
        ?>
          <img class="branch-logo-preview" src="<?= h(url($logoRel)) ?>" alt="Branch logo">
        <?php endif; ?>
      </div>
    </div>
    <div class="branch-form-actions">
      <button class="btn" type="submit"><?= icon('check') ?><?= $edit ? 'Save branch' : 'Add branch' ?></button>
      <?php if ($edit): ?>
        <a class="btn ghost" href="<?= h(url('branches.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
  <?php else: ?>
  <p class="lede">All <?= (int) $branchCap ?> branch slots are in use. Remove a named branch before adding another shop.</p>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php layout_end(); ?>
