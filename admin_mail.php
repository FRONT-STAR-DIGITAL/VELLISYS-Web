<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$companies = db_all('SELECT id, name, mail_email, status FROM companies ORDER BY name');
$pick = (int) ($_GET['company'] ?? post('company_id'));
$picked = $pick ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$pick]) : null;
$toPrefill = post('to');
$namePrefill = post('to_name');
if ($picked && $toPrefill === '') {
    $brand = branding_for($pick) ?: [];
    $members = db_all('SELECT name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$pick]);
    $contact = company_notice_email($pick, $brand, $members);
    $toPrefill = $contact['email'] ?: (string) ($picked['mail_email'] ?? '');
    if ($namePrefill === '') {
        $namePrefill = $contact['name'] ?: (string) $picked['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'test_platform') {
        $copy = custom_vellisys_message(
            product_email(),
            'Vellisys',
            'Vellisys mailbox test',
            "This is a test from the super-admin Email tab. The Hostinger mailbox " . product_email() . " is signing in and sending.",
            (int) $user['id']
        );
        flash($copy['ok'] ? 'Test received at ' . product_email() . '.' : ('Test queued: ' . ($copy['error'] ?? 'mail not accepted')), $copy['ok'] ? 'ok' : 'err');
        redirect('admin_mail.php');
    }
    if ($action === 'retry_queued') {
        $retry = retry_queued_platform_mail();
        if ($retry['total'] === 0) {
            flash('No queued platform letters to retry.');
        } elseif ($retry['fail'] === 0) {
            flash('Sent ' . $retry['ok'] . ' queued letter' . ($retry['ok'] === 1 ? '' : 's') . ' from ' . product_email() . '.');
        } else {
            flash('Sent ' . $retry['ok'] . ', still queued ' . $retry['fail'] . '. Check the mailbox password.', 'err');
        }
        redirect('admin_mail.php');
    }
    if ($action === 'payment_receipt') {
        $companyId = (int) post('company_id');
        $co = $companyId ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$companyId]) : null;
        if (!$co) {
            flash('Pick a company to send a payment receipt.', 'err');
            redirect('admin_mail.php');
        }
        $sent = send_payment_receipt($co, $user);
        $pickQ = '?company=' . $companyId;
        if (!empty($sent['ok'])) {
            flash('Payment receipt sent to ' . ($sent['contact']['email'] ?? '') . ' from ' . product_email() . '.');
        } elseif (($sent['status'] ?? '') === 'queued') {
            flash('Payment receipt queued for ' . ($sent['contact']['email'] ?? '') . '. ' . ($sent['error'] ?? ''), 'err');
        } else {
            flash($sent['error'] ?? 'Could not send the payment receipt.', 'err');
        }
        redirect('admin_mail.php' . $pickQ);
    }
    $to = strtolower(post('to'));
    $companyId = (int) post('company_id');
    $name = post('to_name');
    if ($companyId && ($co = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$companyId]))) {
        $brand = branding_for($companyId) ?: [];
        $members = db_all('SELECT name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$companyId]);
        $contact = company_notice_email($companyId, $brand, $members);
        if ($to === '') {
            $to = $contact['email'];
        }
        if ($name === '') {
            $name = $contact['name'] ?: $co['name'];
        }
    }
    $subject = post('subject') ?: 'A note from Vellisys';
    $message = post('message');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid recipient email, or pick a company that has one on file.', 'err');
        redirect('admin_mail.php');
    }
    if (mb_strlen($message) < 8) {
        flash('Write a little more in the message.', 'err');
        redirect('admin_mail.php?company=' . $companyId);
    }
    $sent = custom_vellisys_message($to, $name, $subject, $message, (int) $user['id']);
    if ($sent['ok']) {
        flash('Sent from ' . product_email() . ' to ' . $to . '.');
    } else {
        flash('Queued for ' . $to . '. ' . ($sent['error'] ?? 'The Hostinger mailbox did not accept the message.'), 'err');
    }
    redirect('admin_mail.php' . ($companyId ? '?company=' . $companyId : ''));
}

$q = trim((string) ($_GET['q'] ?? ''));
$viewId = (int) ($_GET['id'] ?? 0);
$view = null;
if ($viewId > 0) {
    $view = db_one(
        'SELECT * FROM emails WHERE id = ? AND (from_email = ? OR to_email = ? OR (from_email = \'\' AND document_id IS NULL))',
        'iss',
        [$viewId, product_email(), product_email()]
    );
}

$recentSql = 'SELECT * FROM emails WHERE from_email = ? OR to_email = ? OR (from_email = \'\' AND document_id IS NULL)';
$recentTypes = 'ss';
$recentArgs = [product_email(), product_email()];
if ($q !== '') {
    $like = '%' . $q . '%';
    $recentSql .= ' AND (to_email LIKE ? OR subject LIKE ? OR body LIKE ?)';
    $recentTypes .= 'sss';
    $recentArgs[] = $like;
    $recentArgs[] = $like;
    $recentArgs[] = $like;
}
$recentSql .= ' ORDER BY id DESC LIMIT 200';
$recent = db_all($recentSql, $recentTypes, $recentArgs);
$cfg = mail_config();

layout_admin_start('Email', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('send') ?>Email</h1>
    <p class="lede">Send a custom Vellisys letter from <strong><?= h(product_email()) ?></strong>. Open any letter that left this mailbox. A copy of every letter that leaves Vellisys or a company desk also arrives here, with Reply-To set to the client so you can answer from the copy. Clients receive from this mailbox when they submit a question, register, pay (receipt), go live, or get a renewal reminder. Pick a company to send the payment receipt template: term started, expiry, currency, amount received, thanks, and a wait for onboarding credentials when the desk is not live yet.</p>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <button class="btn ghost" name="action" value="test_platform"><?= icon('check', 16) ?>Test mailbox</button>
    <button class="btn ghost" name="action" value="retry_queued"><?= icon('send', 16) ?>Retry queued</button>
  </form>
</div>

<div class="stats">
  <div class="card stat"><?= icon('letter', 20) ?><span>From</span><strong><?= h($cfg['from_email']) ?></strong></div>
  <div class="card stat"><?= icon('globe', 20) ?><span>SMTP</span><strong><?= h($cfg['host'] . ':' . $cfg['port']) ?></strong></div>
  <div class="card stat"><?= icon('lock', 20) ?><span>SMTP security</span><strong><?= h(strtoupper((string) $cfg['secure'])) ?></strong></div>
  <div class="card stat"><?= icon('letter', 20) ?><span>POP / IMAP</span><strong><?= h($cfg['pop_host'] . ':' . $cfg['pop_port']) ?></strong></div>
</div>

<?php if ($view): ?>
<div class="card form-wide" style="margin-bottom:24px">
  <div class="card-head">
    <h2><?= icon('letter', 16) ?>Letter</h2>
    <a class="btn ghost sm" href="<?= h(url('admin_mail.php')) ?>">Back to log</a>
  </div>
  <div style="padding:0 22px 22px">
    <p class="hint" style="margin:0 0 8px">From <?= h(($view['from_email'] ?? '') !== '' ? $view['from_email'] : product_email()) ?> · <?= h(substr((string) $view['created_at'], 0, 16)) ?> · <?= h($view['status']) ?></p>
    <p style="margin:0 0 6px"><strong>To</strong> <?= h($view['to_email']) ?></p>
    <p style="margin:0 0 14px"><strong>Subject</strong> <?= h($view['subject']) ?></p>
    <?php if (!empty($view['error'])): ?>
      <p class="flash flash-err"><?= h($view['error']) ?></p>
    <?php endif; ?>
    <div class="mail-preview">
      <div class="mail-preview-head"><?= h($view['subject']) ?></div>
      <div class="mail-body"><?= nl2br(h((string) ($view['body'] ?? ''))) ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card form-wide" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('send', 16) ?>Compose</h2></div>
  <form method="post" class="form-wide" style="padding-top:0">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div>
        <label for="company_id">Company (optional)</label>
        <select id="company_id" name="company_id" onchange="location.href=this.value?('<?= h(url('admin_mail.php')) ?>?company='+this.value):'<?= h(url('admin_mail.php')) ?>'">
          <option value="">Choose a desk…</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $pick === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="to">To</label>
        <input id="to" name="to" type="email" value="<?= h($toPrefill) ?>" placeholder="accounts@company.ug">
      </div>
      <div>
        <label for="to_name">Name</label>
        <input id="to_name" name="to_name" value="<?= h($namePrefill) ?>">
      </div>
      <div>
        <label for="subject">Subject</label>
        <input id="subject" name="subject" required value="<?= h(post('subject') ?: 'A note from Vellisys') ?>">
      </div>
    </div>
    <label for="message">Message</label>
    <textarea id="message" name="message" rows="10" required placeholder="Write as Vellisys. The letter is wrapped in our stationery."><?= h(post('message')) ?></textarea>
    <p class="hint">Hostinger SMTP <?= h($cfg['host']) ?> port <?= (int) $cfg['port'] ?> (<?= h($cfg['secure']) ?>). POP <?= h($cfg['pop_host']) ?>:<?= (int) $cfg['pop_port'] ?>. IMAP <?= h($cfg['imap_host']) ?>:<?= (int) $cfg['imap_port'] ?>.</p>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('send') ?>Send from Vellisys</button>
    </div>
  </form>
  <?php if ($picked): ?>
    <form method="post" style="padding:0 22px 18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="payment_receipt">
      <input type="hidden" name="company_id" value="<?= (int) $picked['id'] ?>">
      <p class="hint" style="margin:0 0 10px">Payment receipt for <strong><?= h($picked['name']) ?></strong>: term <?= h(company_term_label($picked)) ?><?php if (company_expires_on($picked)): ?>, <?= h(format_date((string) $picked['paid_from'])) ?> to <?= h(format_date((string) $picked['expires_at'])) ?><?php endif; ?>, <?= h(money(company_fee_paid($picked) > 0 ? company_fee_paid($picked) : company_fee_amount($picked), company_fee_currency($picked))) ?>.</p>
      <button class="btn ghost" type="submit"><?= icon('receipt', 16) ?>Send payment receipt</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('letter', 16) ?>Letters from <?= h(product_email()) ?></h2></div>
  <form method="get" class="form-wide" style="padding:0 22px 12px">
    <label for="mail_q">Find a letter</label>
    <div class="actions" style="align-items:center;gap:8px;flex-wrap:wrap">
      <input id="mail_q" name="q" value="<?= h($q) ?>" placeholder="To, subject or words in the letter" style="flex:1;min-width:180px">
      <button class="btn ghost sm" type="submit"><?= icon('letter', 14) ?>Search</button>
    </div>
  </form>
  <?php if (!$recent): ?>
    <p class="empty">Nothing sent from <?= h(product_email()) ?> yet. Questions, sign-ups, welcome letters and this tab all land here.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>To</th>
          <th>Subject</th>
          <th>Status</th>
          <th></th>
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
            <td><a class="btn ghost sm" href="<?= h(url('admin_mail.php?id=' . (int) $row['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
