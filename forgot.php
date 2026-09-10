<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($user = current_user()) {
    redirect(($user['role'] ?? '') === 'platform' ? 'admin_signups.php' : 'dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Forgot password · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'We can get you back on the desk.',
      'Passwords are reset by the company admin on the desk, or by Vellisys if the company is locked out.',
      '',
      [
          'kicker' => 'Documents simplified',
          'heading_html' => 'We can get you<br>back on the desk.',
          'tag' => 'Simple tools. Real progress.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <div class="gate-stack">
      <div class="gate-box">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Forgot password?</h2>
        <p class="gate-lead">Anyone on the desk can change their own password once they are signed in. If you cannot sign in, ask the company admin to reset it under Settings, People.</p>
        <p class="gate-lead">If the company is locked out, write to <?= h(product_email()) ?> or call <?= h(implode(' or ', product_phones())) ?>.</p>
        <a class="gate-submit" href="<?= h(url('login.php')) ?>">Back to Sign In <?= icon('arrow-right', 18) ?></a>
        <p class="gate-or"><span>or</span></p>
        <a class="gate-alt" href="mailto:<?= h(product_email()) ?>">Email <?= h(product_email()) ?></a>
        <?php render_gate_legal(); ?>
      </div>
    </div>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
