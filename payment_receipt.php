<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['t'] ?? '');
$company = $id > 0 ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;

if (!$company || !hash_equals(payment_receipt_share_token($company), $token) || !company_expires_on($company)) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt not available</title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
</head>
<body class="print-body">
  <p class="empty" style="padding:48px;text-align:center">This payment receipt is not available. Ask Vellisys to send the link again.</p>
</body>
</html>
    <?php
    exit;
}

$thisPayment = max(0, (float) ($_GET['paid'] ?? 0));
if (isset($_GET['og'])) {
    payment_receipt_send_preview($company, $thisPayment);
}

$brand = branding_for($id) ?: [];
$members = db_all('SELECT id, name, email, phone FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]);
$contact = company_notice_email($id, $brand, $members);
$copy = payment_receipt_copy($company, $contact, $members, $thisPayment);
$print = isset($_GET['print']);
// Warm the WhatsApp preview cache when the page is opened.
payment_receipt_ensure_preview($company, $thisPayment);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <title><?= h($copy['subject']) ?></title>
  <?php payment_receipt_og_meta($company, $thisPayment); ?>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>:root { <?= product_css_vars() ?> }</style>
  <?php if ($print): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
</head>
<body class="print-body pay-receipt-body">
  <div class="pay-receipt-sheet">
    <header class="pay-receipt-brand">
      <img class="pay-receipt-favicon" src="<?= h(product_favicon_url()) ?>" alt="<?= h(product_name()) ?>" width="36" height="36">
      <div>
        <strong><?= h(product_name()) ?></strong>
        <span>Payment receipt</span>
      </div>
    </header>
    <p class="pay-receipt-ref"><?= h($copy['ref']) ?></p>
    <h1><?= h((string) ($company['name'] ?? '')) ?></h1>
    <dl class="pay-receipt-meta">
      <div><dt>Period</dt><dd><?= h($copy['period']) ?></dd></div>
      <?php if ($thisPayment > 0.009): ?>
        <div><dt>This payment</dt><dd><?= h(money($thisPayment, $copy['currency'])) ?></dd></div>
      <?php endif; ?>
      <div><dt>Amount paid</dt><dd><?= h(money((float) $copy['amount_paid'], $copy['currency'])) ?></dd></div>
      <div><dt>Fee for this term</dt><dd><?= h(money((float) $copy['fee'], $copy['currency'])) ?></dd></div>
      <div><dt>Balance remaining</dt><dd><?= h(money((float) $copy['balance'], $copy['currency'])) ?></dd></div>
      <div><dt>Term started</dt><dd><?= h(format_date((string) ($company['paid_from'] ?? '')) ?: '-') ?></dd></div>
      <div><dt>Account expires</dt><dd><?= h(format_date((string) ($company['expires_at'] ?? '')) ?: '-') ?></dd></div>
    </dl>
    <p class="pay-receipt-note">Thank you for your payment. Your desk is where quotations, invoices and receipts leave in your branding.</p>
    <p class="pay-receipt-foot"><?= h(product_email()) ?> · <?= h(implode(' · ', product_phones())) ?></p>
  </div>
  <?php if (!$print): ?>
    <p class="pay-receipt-actions no-print">
      <a class="btn" href="<?= h(url('payment_receipt.php?id=' . $id . '&t=' . rawurlencode($token) . '&print=1')) ?>"><?= icon('printer', 16) ?>Print</a>
    </p>
  <?php endif; ?>
</body>
</html>
