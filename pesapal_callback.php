<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$tracking = (string) ($_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? '');
$ref = (string) ($_GET['OrderMerchantReference'] ?? $_GET['orderMerchantReference'] ?? '');
$cancelled = isset($_GET['cancel']);

$order = null;
if ($tracking !== '') {
    $order = order_by_tracking($tracking);
}
if (!$order && $ref !== '') {
    $order = order_by_merchant($ref);
}

if ($order) {
    if ($cancelled && ($order['status'] ?? '') !== 'paid') {
        $order = apply_order_payment_status($order, 'cancelled', 'Customer cancelled payment.');
    } else {
        $order = refresh_order_from_pesapal($order);
    }
    if (($order['status'] ?? '') === 'paid') {
        $order = provision_paid_order($order);
    }
}

$status = (string) ($order['status'] ?? ($cancelled ? 'cancelled' : 'pending'));
$pkg = $order ? pricing_package((string) $order['plan']) : null;
$retry = $order
    ? url(checkout_plan_url((string) $order['plan'], (string) $order['public_id']))
    : url('index.php#pricing');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment · <?= h(product_name()) ?></title>
  <?php product_icons(); ?>
  <?php folio_landing_head(); ?>
  <script>if (window.top !== window.self) { window.top.location.replace(window.location.href); }</script>
</head>
<body class="lp">
  <?php public_header('checkout'); ?>
  <main class="lp-checkout lp-checkout-done">
    <ol class="lp-check-steps" aria-label="Checkout">
      <li class="is-done"><b>1</b><span>Company</span></li>
      <li class="<?= $status === 'paid' ? 'is-done' : 'is-current' ?>"><b>2</b><span>Pay</span></li>
    </ol>
    <div class="lp-checkout-copy">
      <?php if ($status === 'paid'): ?>
        <p class="lp-kicker">Paid</p>
        <h1>We have your payment</h1>
        <p>Thank you. <strong><?= h((string) ($order['company'] ?? '')) ?></strong> paid for <?= h($pkg['name'] ?? 'a desk') ?>. Set the admin email and password you will use. We also emailed this link to <?= h((string) ($order['email'] ?? '')) ?> from <?= h(product_email()) ?>.</p>
      <?php elseif ($status === 'cancelled'): ?>
        <p class="lp-kicker">Cancelled</p>
        <h1>Payment was cancelled</h1>
        <p>We kept the company details. <?= h(product_email()) ?> has been notified. You can pay again, <a href="<?= h(url('register.php')) ?>">register for onboarding</a>, or <a href="<?= h(url('demo.php')) ?>">book a demo</a>.</p>
      <?php elseif ($status === 'failed'): ?>
        <p class="lp-kicker">Not paid</p>
        <h1>Payment did not go through</h1>
        <p>We still have your form. <?= h(product_email()) ?> has been notified and will follow up. You can try again on this page.</p>
      <?php else: ?>
        <p class="lp-kicker">Waiting</p>
        <h1>Payment is still processing</h1>
        <p>If money left the account, sit tight. We email <?= h(product_email()) ?> when the payment confirms. Refresh this page in a minute.</p>
      <?php endif; ?>
      <div class="lp-cta">
        <?php if ($status === 'paid' && $order && trim((string) ($order['onboard_token'] ?? '')) !== ''): ?>
          <a class="lp-btn lp-btn-solid" href="<?= h(url('register.php?t=' . rawurlencode((string) $order['onboard_token']))) ?>">Set up your desk</a>
        <?php endif; ?>
        <?php if ($order && in_array($status, ['failed', 'cancelled', 'pending', 'draft'], true)): ?>
          <a class="lp-btn lp-btn-solid" href="<?= h($retry) ?>">Return to checkout</a>
          <a class="lp-btn lp-btn-ghost" href="<?= h(url('register.php')) ?>">Register without paying</a>
        <?php endif; ?>
        <a class="lp-btn lp-btn-ghost" href="<?= h($status === 'paid' ? url('login.php') : url()) ?>"><?= $status === 'paid' ? 'Sign in' : 'Back to Vellisys' ?></a>
      </div>
    </div>
  </main>
  <?php public_float_widgets(); ?>
  <script src="<?= h(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
