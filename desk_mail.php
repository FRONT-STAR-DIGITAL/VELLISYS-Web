<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$brand = branding();
$company = current_company();
$sendAcct = $company ? company_mail_account($company) : null;
$fromEmail = (string) ($sendAcct['from_email'] ?? '');
$fromName = (string) ($sendAcct['from_name'] ?? $brand['name']);
$cid = current_company_id();

$type = strtolower(trim((string) ($_GET['type'] ?? post('type'))));
if (!in_array($type, ['reminder', 'creditor', 'custom'], true)) {
    $type = 'custom';
}
$docId = (int) ($_GET['id'] ?? post('document_id'));
$partyId = (int) ($_GET['party'] ?? post('party_id'));

$doc = $docId ? load_document($docId) : null;
if ($docId && !$doc) {
    flash('That document is not on this desk.', 'err');
    redirect('desk_mail.php');
}

if ($type === 'reminder') {
    if (!$doc || ($doc['kind'] ?? '') !== 'invoice') {
        flash('Pick an open invoice to remind the debtor.', 'err');
        redirect('debtors.php');
    }
} elseif ($type === 'creditor') {
    if (!$doc || ($doc['kind'] ?? '') !== 'expense') {
        flash('Pick an unpaid bill to write to the supplier.', 'err');
        redirect('creditors.php');
    }
}

if ($doc && $partyId === 0) {
    $partyId = (int) ($doc['party_id'] ?? 0);
}

$party = $partyId
    ? db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid])
    : null;
$parties = db_all('SELECT id, name, email, kind FROM parties WHERE company_id = ? ORDER BY name', 'i', [$cid]);

$toPrefill = post('to');
$subjectPrefill = post('subject');
$messagePrefill = post('message');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($toPrefill === '' && $doc) {
        $toPrefill = (string) ($doc['party_email'] ?? '');
    }
    if ($toPrefill === '' && $party) {
        $toPrefill = (string) ($party['email'] ?? '');
    }
    $who = (string) ($doc['party_name'] ?? ($party['name'] ?? ''));
    if ($who === '') {
        $who = 'the team';
    }
    $signOff = $user['name'] . "\n" . $brand['name'];

    if ($type === 'reminder' && $doc) {
        $due = !empty($doc['due_date']) ? ' due on ' . format_date($doc['due_date']) : '';
        $balance = money((float) ($doc['balance'] ?? 0), doc_currency($doc));
        $subjectPrefill = $subjectPrefill !== '' ? $subjectPrefill : ('Reminder: invoice ' . $doc['number'] . ' from ' . $brand['name']);
        $messagePrefill = $messagePrefill !== '' ? $messagePrefill : (
            "Dear {$who},\n\n"
            . 'This is a reminder that invoice ' . $doc['number'] . ' still has a balance of ' . $balance . $due . ".\n\n"
            . "Please settle the amount so we can keep your account current.\n\n"
            . document_share_url($doc) . "\n\n"
            . "Kind regards,\n" . $signOff
        );
    } elseif ($type === 'creditor' && $doc) {
        $balance = money((float) ($doc['balance'] ?? 0), doc_currency($doc));
        $subjectPrefill = $subjectPrefill !== '' ? $subjectPrefill : ('Regarding bill ' . $doc['number'] . ' from ' . $brand['name']);
        $messagePrefill = $messagePrefill !== '' ? $messagePrefill : (
            "Dear {$who},\n\n"
            . 'I am writing about bill ' . $doc['number'] . ' dated ' . format_date((string) $doc['date']) . '. The balance still owing is ' . $balance . ".\n\n"
            . document_share_url($doc) . "\n\n"
            . "Kind regards,\n" . $signOff
        );
    } else {
        $subjectPrefill = $subjectPrefill !== '' ? $subjectPrefill : ('A note from ' . $brand['name']);
        $messagePrefill = $messagePrefill !== '' ? $messagePrefill : (
            "Dear {$who},\n\n\n\nKind regards,\n" . $signOff
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = strtolower(post('to'));
    $subject = post('subject') ?: ('A note from ' . $brand['name']);
    $message = post('message');
    $partyId = (int) post('party_id');
    if ($partyId && $to === '') {
        $picked = db_one('SELECT email FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid]);
        $to = strtolower(trim((string) ($picked['email'] ?? '')));
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid recipient email, or pick a client who has one on file.', 'err');
        redirect('desk_mail.php' . desk_mail_query($type, $docId, $partyId));
    }
    if (mb_strlen($message) < 8) {
        flash('Write a little more in the message.', 'err');
        redirect('desk_mail.php' . desk_mail_query($type, $docId, $partyId));
    }
    $kicker = match ($type) {
        'reminder' => 'Payment reminder',
        'creditor' => 'Note to supplier',
        default => 'A note from ' . $brand['name'],
    };
    $sent = send_company_email($user, $to, $subject, $message, $doc, $kicker);
    if ($sent['ok']) {
        flash('Sent from ' . $sent['from'] . ' to ' . $to . '.');
    } else {
        flash($sent['error'] ?? ('Queued from ' . ($sent['from'] ?: 'the company mailbox') . '.'), 'err');
    }
    if ($doc) {
        redirect('document_view.php?id=' . (int) $doc['id']);
    }
    redirect('desk_mail.php');
}

$recent = $fromEmail !== ''
    ? db_all(
        'SELECT e.* FROM emails e LEFT JOIN documents d ON d.id = e.document_id WHERE e.from_email = ? OR d.company_id = ? ORDER BY e.id DESC LIMIT 20',
        'si',
        [$fromEmail, $cid]
    )
    : db_all(
        'SELECT e.* FROM emails e INNER JOIN documents d ON d.id = e.document_id WHERE d.company_id = ? ORDER BY e.id DESC LIMIT 20',
        'i',
        [$cid]
    );

$pageTitle = match ($type) {
    'reminder' => 'Remind debtor',
    'creditor' => 'Message supplier',
    default => 'Email',
};
$lede = match ($type) {
    'reminder' => 'Send a payment reminder from the company mailbox Vellisys assigned. The client sees your logo on a white band, in your colours.',
    'creditor' => 'Write to a supplier from the company mailbox. Replies come back there, not to the person signed in.',
    default => 'Quotations, invoices, receipts and headed letters still go from Share → Email on the sheet. Use this page for reminders, notes to creditors, and any other letter from your assigned mailbox.',
};

layout_start($pageTitle, $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('send') ?><?= h($pageTitle) ?></h1>
    <p class="lede"><?= $lede ?></p>
  </div>
  <?php if ($type === 'reminder' && $doc): ?>
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=letter&party=' . (int) $doc['party_id'] . '&template=demand&related=' . (int) $doc['id'])) ?>"><?= icon('letter', 16) ?>Demand letter</a>
  <?php endif; ?>
</div>

<form class="card form-wide" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="type" value="<?= h($type) ?>">
  <input type="hidden" name="document_id" value="<?= $doc ? (int) $doc['id'] : 0 ?>">
  <p class="from-line"><?= icon('send', 16) ?>From <?= $sendAcct ? h($fromName . ' <' . $fromEmail . '>') : 'mailbox not assigned' ?></p>
  <?php if ($doc): ?>
    <p class="hint" style="margin-top:10px"><?= h(kind_meta($doc['kind'])['singular']) ?> <?= h($doc['number']) ?> · <?= h($doc['party_name']) ?><?php if (isset($doc['balance'])): ?> · Balance <?= h(money((float) $doc['balance'], doc_currency($doc))) ?><?php endif; ?></p>
  <?php endif; ?>
  <div class="form-grid">
    <div>
      <label for="party_id">Client or supplier</label>
      <select id="party_id" name="party_id">
        <option value="">Choose a name…</option>
        <?php foreach ($parties as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= $partyId === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?><?= $p['email'] ? ' · ' . h($p['email']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="to">To</label>
      <input id="to" name="to" type="email" value="<?= h($toPrefill) ?>" placeholder="client@company.ug">
    </div>
    <div style="grid-column:1 / -1">
      <label for="subject">Subject</label>
      <input id="subject" name="subject" required value="<?= h($subjectPrefill) ?>">
    </div>
  </div>
  <label for="message">Message</label>
  <textarea id="message" name="message" rows="12" required><?= h($messagePrefill) ?></textarea>
  <p class="hint">The letter uses your logo on a white background. Vellisys stationery (questions, registration, onboarding) still leaves from <?= h(product_email()) ?>.</p>
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit" <?= $sendAcct ? '' : 'disabled' ?>><?= icon('send') ?>Send from company mailbox</button>
    <?php if ($doc): ?>
      <a class="btn ghost" href="<?= h(url('document_view.php?id=' . (int) $doc['id'])) ?>">Cancel</a>
    <?php endif; ?>
  </div>
</form>

<div class="card" style="margin-top:24px">
  <div class="card-head"><h2><?= icon('letter', 16) ?>Sent from this desk</h2></div>
  <?php if (!$recent): ?>
    <p class="empty"><?= $sendAcct ? 'Nothing has left this mailbox yet.' : 'Assign a company mailbox on the Vellisys company page, then send from here.' ?></p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>To</th>
          <th>Subject</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $row['created_at'], 0, 16)) ?></td>
            <td class="mono"><?= h($row['to_email']) ?></td>
            <td><?= h($row['subject']) ?></td>
            <td>
              <span class="pill<?= $row['status'] === 'queued' ? ' warn' : '' ?>"><?= h($row['status']) ?></span>
              <?php if ($row['error']): ?><div class="hint"><?= h($row['error']) ?></div><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end();
