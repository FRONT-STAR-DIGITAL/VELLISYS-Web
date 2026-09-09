<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login(post('email'), post('password'))) {
        $user = current_user();
        redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
    }
    $error = 'Those details did not match an account.';
}

$adminEmail = platform_admin_email();
$adminPass = platform_admin_password();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_font_links(); ?>
  <?php folio_css_links(); ?>
  <link rel="stylesheet" href="<?= h(asset('css/landing.css')) ?>">
</head>
<body>
<div class="login">
  <section class="login-art">
    <div>
      <a class="lp-brand" href="<?= h(url()) ?>" style="margin-bottom:18px">
        <img class="lp-logo lp-logo-on-dark" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
      </a>
      <p class="login-kicker"><?= h(product_name()) ?></p>
      <h1>Your books. One click from the client.</h1>
      <p class="standfirst">Quotations, invoices, receipts and reports on one desk. Share a record, or open the books from anywhere.</p>
    </div>
    <p class="login-caption">Generate · share · collect</p>
  </section>
  <section class="login-panel">
    <form class="login-box" method="post" action="<?= h(url('login.php')) ?>">
      <img class="login-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
      <h2>Sign in</h2>
      <p class="hint">Company desks use the mailbox issued when Vellisys onboarded you. Super admin controls every client company.</p>
      <?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
      <div class="field">
        <label for="email">Email</label>
      <div class="field-control">
        <?= icon('letter', 16) ?>
        <input id="email" name="email" type="email" required value="<?= h(post('email') ?: $adminEmail) ?>" autocomplete="username">
      </div>
      </div>
      <div class="field">
        <label for="password">Password</label>
      <div class="field-control">
        <?= icon('lock', 16) ?>
        <input id="password" name="password" type="password" required value="<?= h(post('password') ?: $adminPass) ?>" autocomplete="current-password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
      </div>
      <button class="btn" type="submit"><?= icon('desk', 16) ?>Enter the desk</button>

      <div class="login-keys">
        <p><strong>Vellisys super admin</strong> - companies and sign-ups</p>
        <code><?= h($adminEmail) ?></code>
        <code><?= h($adminPass) ?></code>
        <button type="button" class="btn ghost sm" data-fill-login data-fill-email="<?= h($adminEmail) ?>" data-fill-password="<?= h($adminPass) ?>">Use super admin</button>
        <p style="margin-top:12px"><strong>Demo company desk</strong></p>
        <code>accounts@ofagros.org</code>
        <code>folio2026</code>
        <button type="button" class="btn ghost sm" data-fill-login data-fill-email="accounts@ofagros.org" data-fill-password="folio2026">Use demo desk</button>
      </div>

      <p class="hint" style="margin-top:16px">New company? <a href="<?= h(url('register.php')) ?>">Register</a> · <a href="<?= h(url()) ?>">Back to <?= h(product_name()) ?></a></p>
    </form>
  </section>
</div>
<script src="<?= h(asset('js/app.js')) ?>"></script>
</body>
</html>
