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

$recent = db_all(
    "SELECT * FROM emails WHERE document_id IS NULL ORDER BY id DESC LIMIT 30"
);
$cfg = mail_config();

layout_admin_start('Email', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('send') ?>Email</h1>
    <p class="lede">Send a custom Vellisys letter from <strong><?= h(product_email()) ?></strong>. This mailbox also receives questions, sign-ups, onboarding, live desks, and renewal notices, and it sends a confirmation to the person who asked a question or registered.</p>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <button class="btn ghost" name="action" value="test_platform"><?= icon('check', 16) ?>Test mailbox</button>
  </form>
</div>

<div class="stats">
  <div class="card stat"><?= icon('letter', 20) ?><span>From</span><strong><?= h($cfg['from_email']) ?></strong></div>
  <div class="card stat"><?= icon('globe', 20) ?><span>SMTP</span><strong><?= h($cfg['host'] . ':' . $cfg['port']) ?></strong></div>
  <div class="card stat"><?= icon('lock', 20) ?><span>SMTP security</span><strong><?= h(strtoupper((string) $cfg['secure'])) ?></strong></div>
  <div class="card stat"><?= icon('letter', 20) ?><span>POP / IMAP</span><strong><?= h($cfg['pop_host'] . ':' . $cfg['pop_port']) ?></strong></div>
</div>

<div class="card form-wide" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('send', 16) ?>Compose</h2></div>
  <form method="post" class="form-wide" style="padding-top:0">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div>
        <label for="company_id">Company (optional)</label>
        <select id="company_id" name="company_id">
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
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('letter', 16) ?>Platform mail log</h2></div>
  <?php if (!$recent): ?>
    <p class="empty">Nothing sent from <?= h(product_email()) ?> yet. Questions, sign-ups, welcome letters and this tab all land here.</p>
  <?php else: ?>
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
  <?php endif; ?>
</div>
<?php layout_end(); ?>
