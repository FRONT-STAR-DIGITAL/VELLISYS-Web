<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$role = (string) ($user['role'] ?? '');

if (isset($_GET['leave'])) {
    $left = sales_demo_leave();
    if (!empty($left['ok'])) {
        flash('Left the sales demo desk.');
        $r = (string) ($left['role'] ?? '');
        redirect($r === 'platform' ? platform_home() : ($r === 'sales_agent' ? sales_home() : 'dashboard.php'));
    }
    flash($left['error'] ?? 'Could not leave demo.', 'err');
    redirect('dashboard.php');
}

if ($role !== 'sales_agent' && $role !== 'platform') {
    flash('Demo desk is for sales agents and platform admin.', 'err');
    redirect($role === 'sales_agent' ? sales_home() : 'dashboard.php');
}

// Confirmation / open page for agents; admin can jump straight in with ?go=1
$go = isset($_GET['go']) || $role === 'platform' && isset($_GET['enter']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['enter'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
    }
    $done = sales_demo_enter_from($user);
    if (empty($done['ok'])) {
        flash((string) ($done['error'] ?? 'Could not open demo.'), 'err');
        redirect($role === 'platform' ? 'admin_sales.php' : sales_home());
    }
    $creds = sales_demo_credentials();
    flash('Opened ' . $creds['name'] . '. Use Leave demo when you finish the walkthrough.');
    redirect('dashboard.php');
}

if ($role === 'platform') {
    layout_admin_start('Sales demo', $user);
} else {
    sales_layout_start('Demo', $user);
}
$creds = sales_demo_credentials();
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Sales demo desk</h1>
    <p class="lede">Open the shared demo account to walk a client through quotations, invoices, receipts, stock and the desk.</p>
  </div>
</div>
<div class="card pad-form">
  <p class="lede" style="margin-top:0">Login for this desk (also used if you sign in directly):</p>
  <p><strong>Email:</strong> <code><?= h($creds['email']) ?></code></p>
  <p><strong>Password:</strong> <code><?= h($creds['password']) ?></code></p>
  <form method="post" style="margin-top:16px">
    <?= csrf_field() ?>
    <button class="btn" type="submit"><?= icon('arrow-right', 16) ?>Open demo desk</button>
  </form>
</div>
<?php
if ($role === 'platform') {
    layout_end();
} else {
    sales_layout_end();
}
