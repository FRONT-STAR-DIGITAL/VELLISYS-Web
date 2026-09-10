<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en" data-pwa-login="<?= h(url('login.php')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Terms · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
</head>
<body class="gate">
<div class="gate-shell">
  <?php gate_art(
      'A desk for the company books.',
      'Vellisys is branded stationery and books: quotations, invoices, receipts, expenses and headed notes.',
      '',
      [
          'kicker' => 'Documents simplified',
          'heading_html' => 'A desk for the<br>company books.',
          'tag' => 'Simple tools. Real progress.',
      ]
  ); ?>
  <main class="gate-panel">
    <img class="gate-panel-mark" src="<?= h(product_v_mark_url()) ?>" alt="" decoding="async">
    <div class="gate-stack">
      <div class="gate-box">
        <img class="gate-logo" src="<?= h(product_original_logo_url()) ?>" alt="<?= h(product_name()) ?>">
        <h2>Terms</h2>
        <p class="gate-lead">Registering or paying for a package asks Vellisys to onboard a company desk. You get a login when the desk is live. You are responsible for the documents you issue and for who you add under People.</p>
        <p class="gate-lead">The sending mailbox is assigned by Vellisys. Packages, term and expiry sit on the company record. A product of <?= h(product_maker_name()) ?>.</p>
        <p class="gate-lead">Questions: <?= h(product_email()) ?> or <?= h(implode(' / ', product_phones())) ?>.</p>
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
