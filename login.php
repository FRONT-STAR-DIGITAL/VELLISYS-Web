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
  <?php folio_landing_head(); ?>
  <?php folio_css_links(false); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'Your books. One click from the client.',
      'Quotations, invoices, receipts and reports on one desk. Share a record, or open the books from anywhere.',
      'Don\'t have an account? <a href="' . h(url('register.php')) . '">Register now</a>'
  ); ?>
  <main class="gate-panel">
    <form class="gate-box" method="post" action="<?= h(url('login.php')) ?>">
      <img class="gate-logo" src="<?= h(product_logo_url()) ?>" alt="<?= h(product_name()) ?>">
      <h2><em>Log in</em> to your desk to continue</h2>
      <p class="gate-lead">Use the mailbox issued when Vellisys onboarded your company.</p>
      <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
      <label class="gate-field" for="email">Email
        <input id="email" name="email" type="email" required value="<?= h(post('email')) ?>" autocomplete="username" placeholder="accounts@company.ug">
      </label>
      <label class="gate-field" for="password">Password
        <span class="gate-pw">
          <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Password">
          <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password">
            <span data-eye><?= icon('eye', 16) ?></span>
            <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
          </button>
        </span>
      </label>
      <button class="gate-submit" type="submit">Sign in</button>
      <details class="gate-keys">
        <summary>Try a desk</summary>
        <p><strong>Vellisys super admin</strong> - companies and sign-ups</p>
        <code><?= h($adminEmail) ?></code>
        <code><?= h($adminPass) ?></code>
        <button type="button" class="lp-btn lp-btn-ghost" data-fill-login data-fill-email="<?= h($adminEmail) ?>" data-fill-password="<?= h($adminPass) ?>">Use super admin</button>
        <p><strong>Demo company desk</strong></p>
        <code>accounts@ofagros.org</code>
        <code>folio2026</code>
        <button type="button" class="lp-btn lp-btn-ghost" data-fill-login data-fill-email="accounts@ofagros.org" data-fill-password="folio2026">Use demo desk</button>
      </details>
      <p class="gate-switch">Don't have an account? <a href="<?= h(url('register.php')) ?>">Register now</a></p>
      <p class="gate-home"><a href="<?= h(url()) ?>">Back to <?= h(product_name()) ?></a></p>
    </form>
  </main>
</div>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>"></script>
</body>
</html>
