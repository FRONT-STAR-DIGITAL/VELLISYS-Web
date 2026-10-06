<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$role = (string) ($user['role'] ?? '');

if (isset($_GET['leave'])) {
    $left = sales_demo_leave();
    if (!empty($left['ok'])) {
        flash('Left the testing desk.');
        $r = (string) ($left['role'] ?? '');
        redirect($r === 'platform' ? 'admin_companies.php' : ($r === 'sales_agent' ? 'sales_testing.php' : 'dashboard.php'));
    }
    flash($left['error'] ?? 'Could not leave desk.', 'err');
    redirect('dashboard.php');
}

if ($role !== 'sales_agent' && $role !== 'platform') {
    flash('Only sales agents and platform admin can open a testing desk this way.', 'err');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? post('id'));
if ($id < 1) {
    flash('Pick a testing company.', 'err');
    redirect($role === 'platform' ? 'admin_companies.php' : 'sales_testing.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['go'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
    }
    $done = sales_testing_desk_enter($id, $user);
    if (empty($done['ok'])) {
        flash((string) ($done['error'] ?? 'Could not open desk.'), 'err');
        redirect($role === 'platform' ? 'admin_company.php?id=' . $id : 'sales_company.php?id=' . $id);
    }
    flash('Opened ' . ($done['name'] ?? 'testing desk') . '. Use Leave desk when you finish.');
    redirect('dashboard.php');
}

$company = $role === 'platform'
    ? db_one('SELECT * FROM companies WHERE id = ? AND testing_mode = 1', 'i', [$id])
    : sales_testing_company($id, (int) $user['id']);
if (!$company) {
    flash('Testing company not found.', 'err');
    redirect($role === 'platform' ? 'admin_companies.php' : 'sales_testing.php');
}

if ($role === 'platform') {
    layout_admin_start('Testing desk', $user);
} else {
    sales_layout_start('Testing desk', $user);
}
$creds = sales_testing_credentials($id);
?>
<div class="page-head">
  <div>
    <h1><?= icon('desk') ?><?= h((string) $company['name']) ?></h1>
    <p class="lede"><?= h(company_testing_remaining_label($company)) ?>.</p>
  </div>
</div>
<div class="card pad-form">
  <p><strong>Username:</strong> <code><?= h((string) ($creds['email'] ?: '-')) ?></code></p>
  <p><strong>Password:</strong> <code><?= h((string) ($creds['password'] !== '' ? $creds['password'] : '-')) ?></code></p>
  <?php if (company_testing_expired($company)): ?>
    <p class="flash flash-err">This testing desk has ended.</p>
  <?php else: ?>
    <form method="post" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <button class="btn" type="submit"><?= icon('arrow-right', 16) ?>Open desk</button>
      <a class="btn ghost" href="<?= h(url($role === 'platform' ? 'admin_company.php?id=' . $id : 'sales_company.php?id=' . $id)) ?>">Cancel</a>
    </form>
  <?php endif; ?>
</div>
<?php
if ($role === 'platform') {
    layout_end();
} else {
    sales_layout_end();
}
