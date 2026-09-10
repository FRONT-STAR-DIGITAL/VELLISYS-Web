<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($user = current_user())) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}

$error = '';
$remember = true;
form_mark_open('login');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remember = post('remember') === '1';
    if (!csrf_valid()) {
        $error = 'Your session expired. Please sign in again.';
    } elseif (form_rate_blocked('login', 8, 900)) {
        $error = 'Please wait a few minutes before trying again.';
    } elseif (attempt_login(post('email', '', 190), post('password', '', 256))) {
        remember_login($remember);
        $user = current_user();
        redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
    } else {
        form_rate_hit('login', 900);
        $error = 'Those details did not match an account.';
    }
}

$adminEmail = platform_admin_email();
$adminPass = platform_admin_password();
$showDemoKeys = !folio_is_live_host();
?>
<!DOCTYPE html>
<html lang="en" data-sw="<?= h(url('sw.js')) ?>" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Sign in · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate gate-login">
<div class="gate-shell">
  <?php gate_art(
      'Manage your business documents in one place.',
      'Store, organize, and access your important documents securely - so you can focus on what moves your business forward.',
      '',
      [
          'heading_html' => 'Manage your<br>business documents<br><em>in one place.</em>',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <?php render_gate_home(); ?>
    <div class="gate-stack">
    <?php render_gate_card_mark(); ?>
    <form class="gate-box" method="post" action="<?= h(url('login.php')) ?>">
      <?= csrf_field() ?>
      <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
      <h2>Welcome back</h2>
      <p class="gate-lead">Sign in to your account to continue</p>
      <?php if ($error): ?><p class="lp-err"><?= h($error) ?></p><?php endif; ?>
      <label class="gate-field" for="email">Email</label>
      <div class="gate-control">
        <?= icon('mail', 18) ?>
        <input id="email" name="email" type="email" required maxlength="190" value="<?= h(post('email')) ?>" autocomplete="username" placeholder="you@company.com">
      </div>
      <label class="gate-field" for="password">Password</label>
      <div class="gate-control gate-pw">
        <?= icon('lock', 18) ?>
        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
      <div class="gate-row">
        <label class="gate-check">
          <input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>>
          Remember me
        </label>
        <a class="gate-forgot" href="<?= h(url('forgot.php')) ?>">Forgot password?</a>
      </div>
      <button class="gate-submit" type="submit">Sign In <?= icon('arrow-right', 18) ?></button>
      <p class="gate-or"><span>or</span></p>
      <a class="gate-alt" href="<?= h(url('register.php')) ?>">Register</a>
      <?php if ($showDemoKeys): ?>
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
      <?php endif; ?>
      <?php render_gate_legal(); ?>
    </form>
    </div>

    <details class="gate-install" data-pwa-install>
      <summary class="gate-install-kicker">Install the app</summary>
      <h3>Put Vellisys on your phone or computer</h3>
      <p>The installed app opens on this sign-in page. The public website still starts on the landing page.</p>
      <button class="gate-submit gate-install-btn" type="button" data-pwa-install-btn>Install the Vellisys app</button>
      <p class="gate-install-note" data-pwa-ready hidden>Your browser is ready. Tap the button to add Vellisys.</p>
      <div class="gate-install-ios" data-pwa-ios hidden>
        <p><strong>iPhone and iPad</strong></p>
        <ol>
          <li>Open this sign-in page in Safari.</li>
          <li>Tap the Share button, then <strong>Add to Home Screen</strong>.</li>
          <li>Open the new Vellisys icon. It comes here, not to the landing page.</li>
        </ol>
      </div>
      <div class="gate-install-fallback" data-pwa-fallback hidden>
        <p><strong>Chrome, Edge, or Android</strong></p>
        <ol>
          <li>Use the install icon in the address bar, or the browser menu item <strong>Install app</strong> / <strong>Add to Home screen</strong>.</li>
          <li>Keep this sign-in page open while you install so the app starts here.</li>
        </ol>
        <p>On a Mac in Safari: File, then Add to Dock.</p>
      </div>
    </details>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
<script src="<?= h(asset('js/pwa.js')) ?>" defer></script>
</body>
</html>
