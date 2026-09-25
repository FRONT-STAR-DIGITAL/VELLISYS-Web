<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$error = '';

$admins = db_all("SELECT id, name, email FROM users WHERE role = 'platform' AND COALESCE(status,'live') = 'live' ORDER BY id ASC, name");
$primary = sales_primary_admin_id();
$with = (int) ($_GET['with'] ?? 0);
if ($with < 1 || !array_filter($admins, static fn ($a) => (int) $a['id'] === $with)) {
    $with = $primary ?: (int) ($admins[0]['id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = (int) post('to_user_id') ?: $with ?: $primary;
    $sent = sales_message_send((int) $user['id'], $to, post('body'));
    if (empty($sent['ok'])) {
        $error = (string) ($sent['error'] ?? 'Could not send.');
    } else {
        flash('Message sent to super admin.');
        redirect('sales_messages.php?with=' . (int) ($sent['to_user_id'] ?? $to));
    }
}

if ($with > 0) {
    sales_messages_mark_read((int) $user['id'], $with);
}
$thread = $with ? sales_messages_for((int) $user['id'], $with) : [];
$thread = array_reverse($thread);

sales_layout_start('Messages', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('mail') ?>Messages</h1>
    <p class="lede">Messages go to Vellisys super admin by default and show as notifications. New replies land in your bell.</p>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<?php if (!$admins): ?>
  <p class="empty">No admin mailbox is ready yet.</p>
<?php else: ?>
<div class="filter-chips" style="margin:0 0 12px">
  <?php foreach ($admins as $a): ?>
    <a class="chip<?= $with === (int) $a['id'] ? ' is-on' : '' ?>" href="<?= h(url('sales_messages.php?with=' . (int) $a['id'])) ?>"><?= h($a['name']) ?><?= (int) $a['id'] === $primary ? ' · default' : '' ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="pad-form sales-chat">
    <?php if (!$thread): ?>
      <p class="empty">No messages yet. Say hello to super admin.</p>
    <?php else: ?>
      <div class="sales-chat-log">
        <?php foreach ($thread as $m):
            $mine = (int) $m['from_user_id'] === (int) $user['id'];
            ?>
          <div class="sales-chat-bubble<?= $mine ? ' is-mine' : '' ?>">
            <strong><?= h($mine ? 'You' : (string) $m['from_name']) ?></strong>
            <p><?= nl2br(h((string) $m['body'])) ?></p>
            <span><?= h(format_date(substr((string) $m['created_at'], 0, 10))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="to_user_id" value="<?= (int) ($with ?: $primary) ?>">
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="3" required placeholder="Write to super admin"></textarea>
      <div class="actions" style="margin-top:10px"><button class="btn" type="submit"><?= icon('send', 16) ?>Send</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php sales_layout_end(); ?>
