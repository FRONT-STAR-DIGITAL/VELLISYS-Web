<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Privacy · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'Your books stay on your desk.',
      'We collect what we need to run the desk and to answer you. We do not sell your books.',
      '',
      [
          'kicker' => 'Documents simplified',
          'heading_html' => 'Your books stay<br>on your desk.',
          'tag' => 'Simple tools. Real progress.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <div class="gate-stack">
      <div class="gate-box">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Privacy</h2>
        <p class="gate-lead">Vellisys keeps the company name, people, and documents you put on the desk so quotations, invoices and receipts can be issued in your brand. Sign-in uses the mailbox issued at onboarding.</p>
        <p class="gate-lead">Public forms (register, quote, questions) send the name, company, email and phone you type to <?= h(product_email()) ?> so we can onboard you or answer. Payment on Pesapal is handled by Pesapal.</p>
        <p class="gate-lead">Write <?= h(product_email()) ?> if you need a copy of what we hold, or to ask us to close a desk.</p>
        <a class="gate-submit" href="<?= h(url('login.php')) ?>">Back to Sign In <?= icon('arrow-right', 18) ?></a>
        <?php render_gate_legal(); ?>
      </div>
    </div>
  </main>
</div>
<?php public_float_widgets(); ?>
<script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
