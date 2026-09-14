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
$activityByBranch = [];
if ($admin) {
    foreach ($branches as $b) {
        $bid = (int) ($b['id'] ?? 0);
        $activityByBranch[$bid] = company_activities([
            'branch_id' => $bid,
            'limit' => 5,
        ]);
    }
}

layout_start('Branches', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('pin') ?>Branches</h1>
    <p class="lede">Head office is the company address in Settings. Named branches print their own address on sheets issued from there. Assign staff so their work lands on that branch.</p>
  </div>
  <?php if ($admin): ?>
    <div class="actions">
      <a class="btn ghost" href="<?= h(url('activities.php')) ?>"><?= icon('clock', 16) ?>All activity</a>
      <a class="btn ghost" href="<?= h(url('settings.php#company')) ?>"><?= icon('building', 16) ?>Head office address</a>
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
      ?>
    <article class="card branch-card">
      <div class="card-head">
        <h2><?= icon($isHead ? 'building' : 'pin', 16) ?><?= h((string) $b['name']) ?></h2>
        <?php if ($isHead): ?><span class="pill">Head office</span><?php endif; ?>
      </div>
      <p class="lede" style="margin:0 0 10px">
        <?= h(trim((string) ($b['address'] ?? '')) !== '' ? (string) $b['address'] : 'No street yet') ?>
        <?php if (trim((string) ($b['city'] ?? '')) !== ''): ?> · <?= h((string) $b['city']) ?><?php endif; ?>
      </p>
      <p class="hint" style="margin:0 0 12px">
        <?= h(trim((string) ($b['phone'] ?? '')) !== '' ? (string) $b['phone'] : 'No phone') ?>
        · <?= h(trim((string) ($b['email'] ?? '')) !== '' ? (string) $b['email'] : 'No email') ?>
      </p>
      <p class="hint"><strong>Staff</strong>
        <?php if (!$staff): ?>
          — none assigned<?= $isHead ? ' (unassigned logins sit here)' : '' ?>.
        <?php else: ?>
          — <?= h(implode(', ', array_map(static fn ($m) => (string) $m['name'], $staff))) ?>
        <?php endif; ?>
      </p>
      <?php if ($admin): ?>
        <div class="actions" style="margin:12px 0 0;flex-wrap:wrap">
          <a class="btn ghost sm" href="<?= h(url('activities.php?branch=' . $bid)) ?>"><?= icon('clock', 14) ?>Activity</a>
          <?php if ($isHead): ?>
            <a class="btn ghost sm" href="<?= h(url('settings.php#company')) ?>"><?= icon('pencil', 14) ?>Edit address</a>
          <?php else: ?>
            <a class="btn ghost sm" href="<?= h(url('branches.php?edit=' . $bid)) ?>"><?= icon('pencil', 14) ?>Edit</a>
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
            <?php foreach ($recent as $row): ?>
              <div class="branch-activity-row">
                <strong><?= h((string) $row['title']) ?></strong>
                <span><?= h((string) ($row['actor_name'] ?? '')) ?> · <?= h(format_date(substr((string) $row['occurred_at'], 0, 10))) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($admin): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-head"><h2><?= icon('user', 16) ?>Assign staff</h2></div>
    <?php if (!$members): ?>
      <p class="empty" style="padding:0 22px 18px">Add logins in Settings, then assign them here.</p>
    <?php else: ?>
      <form method="post" class="form-grid" style="padding:0 22px 18px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign_staff">
        <div>
          <label for="user_id">Person</label>
          <select id="user_id" name="user_id" required>
            <?php foreach ($members as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= h($m['name']) ?> · <?= h(company_branch_label((int) ($m['branch_id'] ?? 0))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="assign_branch">Branch</label>
          <select id="assign_branch" name="branch_id">
            <?php render_branch_options(); ?>
          </select>
        </div>
        <div class="actions" style="align-self:end">
          <button class="btn sm" type="submit">Save assignment</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <form class="card form-wide" method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_branch">
    <?php if ($edit): ?>
      <input type="hidden" name="branch_id" value="<?= (int) $edit['id'] ?>">
    <?php endif; ?>
    <div class="card-head">
      <h2><?= icon('plus', 16) ?><?= $edit ? 'Edit ' . h((string) $edit['name']) : 'Add a named branch' ?></h2>
    </div>
    <p class="lede" style="padding:0 22px">Use the city or a shop name. This address prints on documents issued from the branch.</p>
    <div class="form-grid" style="padding:0 22px 18px">
      <div>
        <label for="name">Branch name</label>
        <input id="name" name="name" required value="<?= h((string) ($edit['name'] ?? post('name'))) ?>" placeholder="Kampala shop">
      </div>
      <div>
        <label for="city">City</label>
        <input id="city" name="city" value="<?= h((string) ($edit['city'] ?? post('city'))) ?>">
      </div>
      <div style="grid-column:1 / -1">
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
    <div class="actions" style="padding:0 22px 18px">
      <button class="btn" type="submit"><?= icon('check') ?><?= $edit ? 'Save branch' : 'Add branch' ?></button>
      <?php if ($edit): ?>
        <a class="btn ghost" href="<?= h(url('branches.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
<?php endif; ?>
<?php layout_end(); ?>
