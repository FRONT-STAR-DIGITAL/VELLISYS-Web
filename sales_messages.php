<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$error = '';

$primary = sales_primary_admin_id();
$hasAdmin = $primary > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sent = sales_message_send((int) $user['id'], $primary, post('body'));
    if (empty($sent['ok'])) {
        $error = (string) ($sent['error'] ?? 'Could not send.');
    } else {
        flash('Message sent to Vellisys admin.');
        redirect('sales_messages.php');
    }
}

if ($hasAdmin) {
    // Clear unread admin replies for this agent.
    $adminIds = sales_platform_admin_ids();
    if ($adminIds) {
        $place = implode(',', array_fill(0, count($adminIds), '?'));
        $types = 'i' . str_repeat('i', count($adminIds));
        $params = array_merge([(int) $user['id']], $adminIds);
        db_exec(
            "UPDATE sales_messages SET read_at = NOW()
             WHERE to_user_id = ? AND read_at IS NULL AND from_user_id IN ({$place})",
            $types,
            $params
        );
    }
}

$thread = $hasAdmin ? array_reverse(sales_messages_agent_thread((int) $user['id'])) : [];

sales_layout_start('Messages', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('mail') ?>Messages</h1>
    <p class="lede">Chat with Vellisys admin. Your note reaches every super admin as a notification.</p>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<?php if (!$hasAdmin): ?>
  <p class="empty">No admin mailbox is ready yet.</p>
<?php else: ?>
<div class="card">
  <div class="card-head"><h2><?= icon('user', 16) ?>Vellisys admin</h2></div>
  <div class="pad-form sales-chat">
    <?php if (!$thread): ?>
      <p class="empty">No messages yet. Say hello.</p>
    <?php else: ?>
      <div class="sales-chat-log">
        <?php foreach ($thread as $m):
            $mine = (int) $m['from_user_id'] === (int) $user['id'];
            ?>
          <div class="sales-chat-bubble<?= $mine ? ' is-mine' : '' ?>">
            <strong><?= h($mine ? 'You' : 'Vellisys admin') ?></strong>
            <p><?= nl2br(h((string) $m['body'])) ?></p>
            <span><?= h(format_date(substr((string) $m['created_at'], 0, 10))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?>
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="3" required placeholder="Write to Vellisys admin"></textarea>
      <div class="actions" style="margin-top:10px"><button class="btn" type="submit"><?= icon('send', 16) ?>Send</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php sales_layout_end(); ?>
