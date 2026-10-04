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
    if (!$doc || !function_exists('document_is_open_debtor') || !document_is_open_debtor($doc)) {
        flash('Pick an open invoice or part-paid sale to remind the debtor.', 'err');
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
$parties = db_all("SELECT id, name, email, phone, phone2, kind FROM parties WHERE company_id = ? AND (status IS NULL OR status <> 'deleted') ORDER BY name", 'i', [$cid]);

$toPrefill = post('to');
$phonePrefill = post('phone');
$subjectPrefill = post('subject');
$messagePrefill = post('message');
$channel = strtolower(trim((string) (post('channel') ?: ($_GET['channel'] ?? 'email'))));
if (!in_array($channel, ['email', 'whatsapp'], true)) {
    $channel = 'email';
}
// WhatsApp channel is for payment reminders (and optional creditor notes).
$allowWhatsapp = in_array($type, ['reminder', 'creditor'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($toPrefill === '' && $doc) {
        $toPrefill = (string) ($doc['party_email'] ?? '');
    }
    if ($toPrefill === '' && $party) {
        $toPrefill = (string) ($party['email'] ?? '');
    }
    if ($phonePrefill === '') {
        $phonePrefill = (string) ($doc['party_phone'] ?? ($party['phone'] ?? ''));
        if ($phonePrefill === '' && $party) {
            $phonePrefill = (string) ($party['phone2'] ?? '');
        }
        if ($phonePrefill === '' && $doc) {
            $phonePrefill = (string) ($doc['party_phone2'] ?? '');
        }
    }
    $who = (string) ($doc['party_name'] ?? ($party['name'] ?? ''));
    if ($who === '') {
        $who = 'the team';
    }
    $signOff = $user['name'] . "\n" . $brand['name'];

    if ($type === 'reminder' && $doc) {
        $due = !empty($doc['due_date']) ? ' due on ' . format_date($doc['due_date']) : '';
        $balanceAmt = function_exists('document_due_amount')
            ? document_due_amount($doc)
            : (float) ($doc['balance'] ?? 0);
        $balance = money($balanceAmt, doc_currency($doc));
        $kindWord = ($doc['kind'] ?? '') === 'receipt' ? 'sale' : 'invoice';
        $subjectPrefill = $subjectPrefill !== '' ? $subjectPrefill : ('Reminder: ' . $kindWord . ' ' . $doc['number'] . ' from ' . $brand['name']);
        $messagePrefill = $messagePrefill !== '' ? $messagePrefill : (
            "Dear {$who},\n\n"
            . 'This is a reminder that ' . $kindWord . ' ' . $doc['number'] . ' still has a balance of ' . $balance . $due . ".\n\n"
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
    $channel = strtolower(trim((string) post('channel')));
    if (!in_array($channel, ['email', 'whatsapp'], true) || !$allowWhatsapp) {
        $channel = 'email';
    }
    $to = strtolower(post('to'));
    $phone = trim((string) post('phone'));
    $subject = post('subject') ?: ('A note from ' . $brand['name']);
    $message = posted_rich('message');
    $partyId = (int) post('party_id');
    if ($partyId) {
        $picked = db_one('SELECT email, phone, phone2 FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid]);
        if ($to === '' && $picked) {
            $to = strtolower(trim((string) ($picked['email'] ?? '')));
        }
        if ($phone === '' && $picked) {
            $phone = trim((string) ($picked['phone'] ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($picked['phone2'] ?? ''));
            }
        }
    }
    if ($phone === '' && $doc) {
        $phone = trim((string) ($doc['party_phone'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($doc['party_phone2'] ?? ''));
        }
    }
    if (mb_strlen(html_to_plain($message)) < 8) {
        flash('Write a little more in the message.', 'err');
        redirect('desk_mail.php' . desk_mail_query($type, $docId, $partyId) . '&channel=' . urlencode($channel));
    }

    if ($channel === 'whatsapp') {
        $plain = html_to_plain($message);
        $href = function_exists('phone_whatsapp_href') ? phone_whatsapp_href($phone, $plain) : '';
        if ($href === '') {
            flash('Add the customer’s WhatsApp number (on the client, or in the phone field) to send this reminder.', 'err');
            redirect('desk_mail.php' . desk_mail_query($type, $docId, $partyId) . '&channel=whatsapp');
        }
        header('Location: ' . $href);
        exit;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid recipient email, or pick a client who has one on file.', 'err');
        redirect('desk_mail.php' . desk_mail_query($type, $docId, $partyId) . '&channel=email');
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
    'reminder' => 'Send a payment reminder by email from the company mailbox, or open WhatsApp using the customer’s number on file.',
    'creditor' => 'Write to a supplier by email or WhatsApp. Email leaves from the company mailbox; WhatsApp uses their phone number.',
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

<form class="card form-wide" method="post" data-desk-mail>
  <?= csrf_field() ?>
  <input type="hidden" name="type" value="<?= h($type) ?>">
  <input type="hidden" name="document_id" value="<?= $doc ? (int) $doc['id'] : 0 ?>">
  <?php if ($allowWhatsapp): ?>
    <fieldset class="desk-mail-channel" style="margin:0 0 14px;border:0;padding:0">
      <legend class="label" style="margin:0 0 8px">Send by</legend>
      <div class="radio-row" style="display:flex;flex-wrap:wrap;gap:12px 18px">
        <label class="check"><input type="radio" name="channel" value="email" <?= $channel === 'email' ? 'checked' : '' ?> data-mail-channel> Email</label>
        <label class="check"><input type="radio" name="channel" value="whatsapp" <?= $channel === 'whatsapp' ? 'checked' : '' ?> data-mail-channel> WhatsApp</label>
      </div>
    </fieldset>
  <?php else: ?>
    <input type="hidden" name="channel" value="email">
  <?php endif; ?>
  <p class="from-line" data-mail-from-line <?= $channel === 'whatsapp' ? 'hidden' : '' ?>><?= icon('send', 16) ?>From <?= $sendAcct ? h($fromName . ' <' . $fromEmail . '>') : 'mailbox not assigned' ?></p>
  <p class="hint" data-wa-from-line <?= $channel === 'whatsapp' ? '' : 'hidden' ?> style="margin-top:0"><?= icon('whatsapp', 16) ?> WhatsApp opens with the customer’s number and your message ready to send.</p>
  <?php if ($doc): ?>
    <p class="hint" style="margin-top:10px"><?= h(kind_meta($doc['kind'])['singular']) ?> <?= h($doc['number']) ?> · <?= h($doc['party_name']) ?><?php if (isset($doc['balance'])): ?> · Balance <?= h(money((float) $doc['balance'], doc_currency($doc))) ?><?php endif; ?></p>
  <?php endif; ?>
  <div class="form-grid">
    <div>
      <label for="party_id">Client or supplier</label>
      <select id="party_id" name="party_id" data-party-pick>
        <option value="">Choose a name…</option>
        <?php foreach ($parties as $p): ?>
          <option
            value="<?= (int) $p['id'] ?>"
            <?= $partyId === (int) $p['id'] ? 'selected' : '' ?>
            data-email="<?= h((string) ($p['email'] ?? '')) ?>"
            data-phone="<?= h(trim((string) (($p['phone'] ?? '') !== '' ? $p['phone'] : ($p['phone2'] ?? '')))) ?>"
          ><?= h($p['name']) ?><?= !empty($p['email']) ? ' · ' . h($p['email']) : '' ?><?= !empty($p['phone']) ? ' · ' . h($p['phone']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div data-mail-email-field <?= $channel === 'whatsapp' ? 'hidden' : '' ?>>
      <label for="to">To (email)</label>
      <input id="to" name="to" type="email" value="<?= h($toPrefill) ?>" placeholder="client@company.ug" data-mail-to>
    </div>
    <div data-mail-phone-field <?= $channel === 'whatsapp' ? '' : 'hidden' ?>>
      <label for="phone">WhatsApp number</label>
      <input id="phone" name="phone" value="<?= h($phonePrefill) ?>" placeholder="e.g. 0748602769" inputmode="tel" data-mail-phone>
      <p class="hint" style="margin:6px 0 0">Uses the number saved on the client. You can edit it for this send.</p>
    </div>
    <div style="grid-column:1 / -1" data-mail-subject-field <?= $channel === 'whatsapp' ? 'hidden' : '' ?>>
      <label for="subject">Subject</label>
      <input id="subject" name="subject" <?= $channel === 'whatsapp' ? '' : 'required' ?> value="<?= h($subjectPrefill) ?>">
    </div>
  </div>
  <label for="message">Message</label>
  <?php render_rich_editor('message', 'message', $messagePrefill, ['rows' => 12, 'required' => true, 'placeholder' => 'Write the message.']); ?>
  <p class="hint" data-mail-email-hint <?= $channel === 'whatsapp' ? 'hidden' : '' ?>>The letter uses your logo on a white background. A copy also goes to <?= h(product_email()) ?> so Vellisys can follow up with the client.</p>
  <p class="hint" data-mail-wa-hint <?= $channel === 'whatsapp' ? '' : 'hidden' ?>>WhatsApp opens in a new chat with this text. Vellisys does not send WhatsApp automatically - you tap Send in WhatsApp.</p>
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit" data-mail-submit-email <?= $channel === 'whatsapp' ? 'hidden' : '' ?> <?= $sendAcct ? '' : 'disabled' ?>><?= icon('send') ?>Send from company mailbox</button>
    <button class="btn" type="submit" data-mail-submit-wa <?= $channel === 'whatsapp' ? '' : 'hidden' ?>><?= icon('whatsapp') ?>Open in WhatsApp</button>
    <?php if ($doc): ?>
      <a class="btn ghost" href="<?= h(url('document_view.php?id=' . (int) $doc['id'])) ?>">Cancel</a>
    <?php endif; ?>
  </div>
</form>

<div class="card" style="margin-top:24px" data-mail-sent-card <?= $channel === 'whatsapp' ? 'hidden' : '' ?>>
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
            <td><span class="pill"><?= h((string) $row['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<script>
(function () {
  var form = document.querySelector('[data-desk-mail]');
  if (!form) return;
  function sync() {
    var ch = (form.querySelector('input[name="channel"]:checked') || {}).value || 'email';
    var wa = ch === 'whatsapp';
    form.querySelectorAll('[data-mail-from-line], [data-mail-email-field], [data-mail-subject-field], [data-mail-email-hint], [data-mail-submit-email], [data-mail-sent-card]').forEach(function (el) {
      el.hidden = wa;
    });
    form.querySelectorAll('[data-wa-from-line], [data-mail-phone-field], [data-mail-wa-hint], [data-mail-submit-wa]').forEach(function (el) {
      el.hidden = !wa;
    });
    var subject = form.querySelector('#subject');
    if (subject) {
      if (wa) subject.removeAttribute('required');
      else subject.setAttribute('required', 'required');
    }
  }
  form.querySelectorAll('[data-mail-channel]').forEach(function (el) {
    el.addEventListener('change', sync);
  });
  var pick = form.querySelector('[data-party-pick]');
  if (pick) {
    pick.addEventListener('change', function () {
      var opt = pick.options[pick.selectedIndex];
      if (!opt) return;
      var email = opt.getAttribute('data-email') || '';
      var phone = opt.getAttribute('data-phone') || '';
      var to = form.querySelector('[data-mail-to]');
      var ph = form.querySelector('[data-mail-phone]');
      if (to && email) to.value = email;
      if (ph && phone) ph.value = phone;
    });
  }
  sync();
})();
</script>
<?php
layout_end();
