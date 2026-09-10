<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? post('id'));
$doc = load_document($id);
if (!$doc) {
    flash('Document not found.', 'err');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = post('to');
    $subject = post('subject');
    $message = post('message');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid recipient email.', 'err');
        redirect('document_email.php?id=' . $id);
    }
    $result = send_document_email($user, $doc, $to, $subject, $message);
    if ($result['ok']) {
        flash('Sent from ' . $result['from'] . ' to ' . $to . '.');
    } else {
        flash($result['error'] ?? ('Queued from ' . ($result['from'] ?: 'the company mailbox') . '.'), 'err');
    }
    redirect('document_view.php?id=' . $id);
}

$meta = kind_meta($doc['kind']);
$company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [(int) $doc['company_id']]);
$sendAcct = $company ? company_mail_account($company) : null;
$fromEmail = $sendAcct['from_email'] ?? '';
$fromName = $sendAcct['from_name'] ?? branding()['name'];
$defaultTo = (string) ($doc['party_email'] ?? '');
$defaultSubject = $meta['singular'] . ' ' . $doc['number'] . ' from ' . branding()['name'];
$defaultBody = "Dear " . $doc['party_name'] . ",\n\nPlease find " . strtolower($meta['singular']) . " " . $doc['number'] . ".\n\n" . document_share_url($doc) . "\n\nKind regards,\n" . $user['name'] . "\n" . branding()['name'];

layout_start('Email ' . $doc['number'], $user, ['kind' => $doc['kind']]);
?>
<div class="page-head">
  <div>
    <h1><?= icon('send') ?>Email <?= h($meta['singular']) ?></h1>
    <p class="lede"><?php if ($sendAcct): ?>Sends as <strong><?= h($fromName) ?></strong> &lt;<?= h($fromEmail) ?>&gt; - the company mailbox Vellisys assigned, with your logo on a white band. A copy goes to <?= h(product_email()) ?> so Vellisys can follow up. For a reminder, a note to a supplier, or any other letter, use Email in the menu.<?php else: ?>Vellisys has not assigned a sending mailbox yet. You can still print and share a link. Ask the platform admin to add the Hostinger address on the company.<?php endif; ?></p>
  </div>
</div>

<form class="card form" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <p class="from-line"><?= icon('send', 16) ?>From <?= $sendAcct ? h($fromName . ' <' . $fromEmail . '>') : 'mailbox not assigned' ?></p>
  <label for="to">To</label>
  <input id="to" name="to" type="email" required value="<?= h($defaultTo) ?>">
  <label for="subject">Subject</label>
  <input id="subject" name="subject" required value="<?= h($defaultSubject) ?>">
  <label for="message">Message</label>
  <textarea id="message" name="message" rows="10" required><?= h($defaultBody) ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit" <?= $sendAcct ? '' : 'disabled' ?>><?= icon('send') ?>Send email</button>
    <a class="btn ghost" href="<?= h(url('document_view.php?id=' . $id)) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
