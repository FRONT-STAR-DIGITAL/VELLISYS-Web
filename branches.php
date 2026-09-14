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
        $saved = save_named_branch([
            'name' => post('name'),
            'address' => post('address'),
            'city' => post('city'),
            'phone' => post('phone'),
            'email' => post('email'),
        ], $id > 0 ? $id : null);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save that branch.');
        } else {
            record_company_activity('branch', ($id > 0 ? 'Updated ' : 'Added ') . post('name'), [
                'detail' => trim(post('city') . ' ' . post('address')),
                'href' => 'branches.php',
                'ref_type' => 'branch',
                'ref_id' => (int) $saved['id'],
                'branch_id' => (int) $saved['id'],
            ]);
            flash($id > 0 ? 'Branch updated.' : 'Branch added.');
            redirect('branches.php');
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

$branches = company_all_branches();
$members = db_all("SELECT id, name, job_title, email, role, access, branch_id FROM users WHERE company_id = ? AND role <> 'platform' ORDER BY role = 'admin' DESC, name", 'i', [$cid]);
$seats = company_user_limit();
$locations = company_location_count($cid);
$canAddBranch = $admin && company_can_add_named_branch();
$activityByBranch = [];
if ($admin) {
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
    <p class="lede">Head office uses the company address in Settings. Named branches print their own address. You may have as many locations as logins (<?= (int) $seats ?>), including Head office. Several people can share one branch.</p>
  </div>
  <?php if ($admin): ?>
    <div class="actions">
      <a class="btn ghost" href="<?= h(url('activities.php')) ?>"><?= icon('clock', 16) ?>All activity</a>
      <a class="btn ghost" href="<?= h(url('settings.php#company')) ?>"><?= icon('building', 16) ?>Company address</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

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
      ?>
    <article class="card branch-card">
      <header class="branch-card-top">
        <div class="branch-card-title">
          <?= icon($isHead ? 'building' : 'pin', 18) ?>
          <div>
            <h2><?= h((string) $b['name']) ?></h2>
            <?php if ($isHead): ?><p>Company address from Settings</p><?php endif; ?>
          </div>
        </div>
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
  <form class="card branch-form" method="post" id="branch-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_branch">
    <?php if ($edit): ?>
      <input type="hidden" name="branch_id" value="<?= (int) $edit['id'] ?>">
    <?php endif; ?>
    <h2><?= icon($edit ? 'pencil' : 'plus', 16) ?><?= $edit ? 'Edit ' . h((string) $edit['name']) : 'Add a named branch' ?></h2>
    <p class="lede">Use a city or shop name. That address prints on documents issued from the branch. <?= (int) $locations ?> of <?= (int) $seats ?> location slots in use.</p>
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
    </div>
    <div class="branch-form-actions">
      <button class="btn" type="submit"><?= icon('check') ?><?= $edit ? 'Save branch' : 'Add branch' ?></button>
      <?php if ($edit): ?>
        <a class="btn ghost" href="<?= h(url('branches.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
  <?php else: ?>
  <p class="lede">All <?= (int) $seats ?> location slots are in use. Remove a named branch, or add a login in Settings, before adding another shop.</p>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php layout_end(); ?>
