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
        flash('Queued from ' . $result['from'] . '. XAMPP mail() needs Mercury or SMTP — the attempt is logged on the document.', 'err');
    }
    redirect('document_view.php?id=' . $id);
}

$meta = kind_meta($doc['kind']);
$defaultTo = (string) ($doc['party_email'] ?? '');
$defaultSubject = $meta['singular'] . ' ' . $doc['number'] . ' from ' . branding()['name'];
$defaultBody = "Dear " . $doc['party_name'] . ",\n\nPlease find " . strtolower($meta['singular']) . " " . $doc['number'] . ".\n\nKind regards,\n" . $user['name'] . "\n" . branding()['name'];

layout_start('Email ' . $doc['number'], $user, ['kind' => $doc['kind']]);
?>
<div class="page-head">
  <div>
    <h1><?= icon('send') ?>Email <?= h($meta['singular']) ?></h1>
    <p class="lede">Sends as <strong><?= h($user['name']) ?></strong> &lt;<?= h($user['email']) ?>&gt; — the account you signed in with.</p>
  </div>
</div>

<form class="card form" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <p class="from-line"><?= icon('send', 16) ?>From <?= h($user['email']) ?></p>
  <label for="to">To</label>
  <input id="to" name="to" type="email" required value="<?= h($defaultTo) ?>">
  <label for="subject">Subject</label>
  <input id="subject" name="subject" required value="<?= h($defaultSubject) ?>">
  <label for="message">Message</label>
  <textarea id="message" name="message" rows="10" required><?= h($defaultBody) ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('send') ?>Send email</button>
    <a class="btn ghost" href="<?= h(url('document_view.php?id=' . $id)) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
