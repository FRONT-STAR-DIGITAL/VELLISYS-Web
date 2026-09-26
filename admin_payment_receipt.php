<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$company = $id > 0 ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
if (!$company) {
    flash('Company not found.', 'err');
    redirect('admin_finances.php');
}

$thisPayment = max(0, (float) ($_GET['paid'] ?? 0));
$brand = branding_for($id) ?: [];
$members = db_all('SELECT id, name, email, phone FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]);
$contact = company_notice_email($id, $brand, $members);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) post('action');
    if ($action === 'send_email') {
        $sent = send_payment_receipt($company, $user);
        if (!empty($sent['ok'])) {
            flash('Payment receipt sent to ' . ($sent['contact']['email'] ?? '') . '.');
        } elseif (($sent['status'] ?? '') === 'queued') {
            flash('Payment receipt queued for ' . ($sent['contact']['email'] ?? '') . '. ' . ($sent['error'] ?? ''), 'err');
        } else {
            flash($sent['error'] ?? 'Could not send the payment receipt.', 'err');
        }
        redirect('admin_payment_receipt.php?id=' . $id . ($thisPayment > 0 ? '&paid=' . rawurlencode((string) $thisPayment) : ''));
    }
}

$canPreview = (bool) company_expires_on($company);
$copy = $canPreview ? payment_receipt_copy($company, $contact, $members, $thisPayment) : null;
$shareUrl = $canPreview ? payment_receipt_share_url($company) : '';
$waUrl = $canPreview ? payment_receipt_whatsapp_url($company, '', $thisPayment) : '';

layout_admin_start('Payment receipt', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('receipt') ?>Payment receipt</h1>
    <p class="lede">Preview what <?= h((string) $company['name']) ?> receives after paying. Share on WhatsApp or email.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('admin_finances.php')) ?>"><?= icon('bank', 16) ?>Finances</a>
    <a class="btn ghost" href="<?= h(url('admin_company.php?id=' . $id)) ?>"><?= icon('building', 16) ?>Company</a>
  </div>
</div>

<?php if (!$canPreview): ?>
  <div class="card pad-form">
    <p class="empty" style="margin:0">Save a paid term with a start date on the company before you can preview or share a receipt.</p>
    <div class="actions" style="margin-top:14px">
      <a class="btn" href="<?= h(url('admin_company.php?id=' . $id . '#term')) ?>"><?= icon('calendar', 16) ?>Set paid term</a>
      <a class="btn ghost" href="<?= h(url('admin_finances.php?pay=' . $id)) ?>"><?= icon('bank', 16) ?>Make payment</a>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-head"><h2><?= icon('invoice', 16) ?>Summary</h2></div>
    <div class="pad-form">
      <div class="stats" style="margin:0">
        <div class="card stat"><span>Period</span><strong style="font-size:1rem"><?= h($copy['period']) ?></strong></div>
        <div class="card stat"><span>Amount paid</span><strong><?= h(money((float) $copy['amount_paid'], $copy['currency'])) ?></strong></div>
        <div class="card stat"><span>Balance remaining</span><strong><?= h(money((float) $copy['balance'], $copy['currency'])) ?></strong></div>
        <?php if ($thisPayment > 0.009): ?>
          <div class="card stat"><span>This payment</span><strong><?= h(money($thisPayment, $copy['currency'])) ?></strong></div>
        <?php endif; ?>
      </div>
      <div class="actions admin-term-actions" style="margin-top:16px">
        <?php if ($waUrl !== ''): ?>
          <a class="btn" href="<?= h($waUrl) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?>Share on WhatsApp</a>
        <?php endif; ?>
        <a class="btn ghost" href="<?= h($shareUrl) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?>Open share link</a>
        <a class="btn ghost" href="<?= h($shareUrl . '&print=1') ?>" target="_blank" rel="noopener"><?= icon('printer', 16) ?>Print</a>
        <form method="post" style="display:inline;margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="send_email">
          <button class="btn ghost" type="submit"><?= icon('letter', 16) ?>Email receipt</button>
        </form>
      </div>
      <p class="hint" style="margin:12px 0 0">
        WhatsApp opens with the receipt text (period, amount paid, balance and link). Pick the company contact in WhatsApp yourself.
        Email still goes to <?= h($contact['email'] !== '' ? $contact['email'] : 'the company email on file') ?>.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= icon('receipt', 16) ?>Receipt preview</h2></div>
    <div class="pad-form">
      <div class="mail-preview">
        <div class="mail-preview-head"><?= h($copy['subject']) ?></div>
        <iframe title="Payment receipt preview" srcdoc="<?= h(email_html_preview($copy['html'])) ?>"></iframe>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
