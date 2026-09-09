<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login(post('email'), post('password'))) {
        redirect('dashboard.php');
    }
    $error = 'Those details did not match an account.';
}
$color = '#82B440';
$company = 'Ofagros Limited';
$tag = 'Solutions for agriculture';
$logo = 'assets/img/ofagros-logo.png';
try {
    $b = db_one('SELECT brand_color, name, tagline, logo_path FROM branding WHERE id=1');
    if ($b) {
        $color = $b['brand_color'] ?: $color;
        $company = $b['name'] ?: $company;
        $tag = $b['tagline'] ?: $tag;
        $logo = $b['logo_path'] ?: $logo;
    }
} catch (Throwable $e) {
    // Desk not installed yet — keep demo copy.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Folio</title>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: <?= h($color) ?>; }</style>
</head>
<body>
<div class="login">
  <section class="login-art">
    <div>
      <p style="letter-spacing:.24em;text-transform:uppercase;font-size:12px;opacity:.85">Folio</p>
      <h1>Books that look like your company.</h1>
      <p style="max-width:420px;opacity:.9">Logo and colour once. Invoices, quotations and receipts your clients can keep. Uganda shillings, VAT, a clean print.</p>
      <div class="paper">
        <div class="paper-bar">INVOICE</div>
        <div class="paper-body">
          <div>BILL TO</div>
          <strong>Demo Visitor</strong>
          <p style="margin:8px 0 0">Coffee Estate Share</p>
        </div>
        <div class="paper-total"><span>Total</span><span>UGX 12,500,000</span></div>
      </div>
    </div>
    <p style="opacity:.8"><?= h($company ?? 'Ofagros Limited') ?><?php if (!empty($tag)): ?> · <?= h($tag) ?><?php endif; ?></p>
  </section>
  <section class="login-form">
    <form class="login-box" method="post" action="<?= h(url('login.php')) ?>">
      <?php if (!empty($logo)): ?>
        <img src="<?= h(url($logo)) ?>" alt="" style="height:40px;margin-bottom:16px">
      <?php endif; ?>
      <h2>Sign in</h2>
      <p class="hint">Use the account that will appear as the sender on emails.</p>
      <?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="accounts@ofagros.org" autocomplete="username">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required value="folio2026" autocomplete="current-password">
      <button class="btn" type="submit" style="margin-top:16px;width:100%">Enter the desk</button>
      <p class="hint" style="margin-top:14px">Demo: accounts@ofagros.org · folio2026<br>If the desk is empty, open <a href="<?= h(url('install.php')) ?>">install.php</a> once.</p>
    </form>
  </section>
</div>
</body>
</html>
