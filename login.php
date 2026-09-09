<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login(post('email'), post('password'))) {
        $user = current_user();
        redirect(($user['role'] ?? '') === 'platform' ? 'admin_companies.php' : 'dashboard.php');
    }
    $error = 'Those details did not match an account.';
}
$color = '#82B440';
$company = 'Folio';
$tag = 'Branded books for Ugandan SMEs';
$logo = 'assets/img/ofagros-logo.png';
try {
    $b = db_one('SELECT brand_color, name, tagline, logo_path FROM branding ORDER BY id LIMIT 1');
    if ($b) {
        $color = $b['brand_color'] ?: $color;
        $company = $b['name'] ?: $company;
        $tag = $b['tagline'] ?: $tag;
        $logo = $b['logo_path'] ?: $logo;
    }
} catch (Throwable $e) {
    // Desk not installed yet.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Folio</title>
  <?php folio_font_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: <?= h($color) ?>; }</style>
</head>
<body>
<div class="login">
  <section class="login-art">
    <div>
      <p class="login-kicker">Folio</p>
      <h1>Books that look like your company.</h1>
      <p class="standfirst">Set the stationery once. Issue quotations, invoices and receipts your clients will keep - in your colour, on your paper.</p>
    </div>
    <p class="login-caption"><?= h($company) ?><?php if ($tag): ?> · <?= h($tag) ?><?php endif; ?></p>
  </section>
  <section class="login-panel">
    <div class="login-still" role="img" aria-label="Stationery on a desk"></div>
    <form class="login-box" method="post" action="<?= h(url('login.php')) ?>">
      <?php if (!empty($logo)): ?>
        <img class="login-logo" src="<?= h(url($logo)) ?>" alt="<?= h($company) ?>">
      <?php endif; ?>
      <h2>Sign in</h2>
      <p class="hint">Use the mailbox that should appear as the sender on every document you email.</p>
      <?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
      <div class="field">
        <label for="email">Email</label>
      <div class="field-control">
        <?= icon('letter', 16) ?>
        <input id="email" name="email" type="email" required value="accounts@ofagros.org" autocomplete="username">
      </div>
      </div>
      <div class="field">
        <label for="password">Password</label>
      <div class="field-control">
        <?= icon('lock', 16) ?>
        <input id="password" name="password" type="password" required value="folio2026" autocomplete="current-password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
      </div>
      <button class="btn" type="submit"><?= icon('desk', 16) ?>Enter the desk</button>
      <p class="hint" style="margin-top:16px">Company desk: accounts@ofagros.org · folio2026<br>Platform admin: admin@folio.ug · folio-admin-2026<br>If the desk is empty, open <a href="<?= h(url('install.php')) ?>">install.php</a> once.</p>
    </form>
  </section>
</div>
<script src="<?= h(asset('js/app.js')) ?>"></script>
</body>
</html>
