<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) post('id');
    $action = post('action');
    $row = $id ? desk_feedback_one($id) : null;
    if (!$row) {
        flash('That feedback was not found.', 'err');
        redirect('admin_feedback.php');
    }
    if ($action === 'read' && ($row['status'] ?? '') === 'new') {
        db_exec("UPDATE desk_feedback SET status = 'read', updated_at = NOW() WHERE id = ?", 'i', [$id]);
        flash('Marked as read.');
        redirect('admin_feedback.php?id=' . $id);
    }
    if ($action === 'reply') {
        $result = desk_feedback_reply($id, (string) post('reply_body'), (int) $user['id']);
        if (!$result['ok']) {
            flash($result['error'] ?? 'Could not send reply.', 'err');
            redirect('admin_feedback.php?id=' . $id);
        }
        notify_desk_feedback_reply($result['row'] ?? desk_feedback_one($id));
        flash('Reply sent to the desk and by email.');
        redirect('admin_feedback.php?id=' . $id);
    }
    if ($action === 'delete') {
        db_exec('DELETE FROM desk_feedback WHERE id = ?', 'i', [$id]);
        flash('Feedback deleted.');
        redirect('admin_feedback.php');
    }
    redirect('admin_feedback.php');
}

if ($id > 0) {
    $row = desk_feedback_one($id);
    if (!$row) {
        flash('That feedback was not found.', 'err');
        redirect('admin_feedback.php');
    }
    if (($row['status'] ?? '') === 'new') {
        db_exec("UPDATE desk_feedback SET status = 'read', updated_at = NOW() WHERE id = ?", 'i', [$id]);
        $row['status'] = 'read';
    }

    layout_admin_start('Desk feedback', $user);
    ?>
<div class="page-head">
  <div>
    <p class="desk-kicker"><a href="<?= h(url('admin_feedback.php')) ?>">Feedback</a> / <?= h((string) ($row['company_name'] ?? 'Company')) ?></p>
    <h1><?= icon('help') ?>Desk help</h1>
    <p class="lede">Reply here. The desk user sees it on Feedback and gets an email.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('admin_feedback.php')) ?>">Inbox</a>
  </div>
</div>

<div class="card q-detail">
  <div class="q-detail-meta">
    <p><span>Company</span><strong><?= h((string) ($row['company_name'] ?? '')) ?></strong></p>
    <p><span>From</span><strong><?= h((string) ($row['user_name'] ?? '')) ?></strong></p>
    <p><span>Email</span><?php if (!empty($row['user_email'])): ?><a href="mailto:<?= h((string) $row['user_email']) ?>"><?= h((string) $row['user_email']) ?></a><?php else: ?>Not given<?php endif; ?></p>
    <p><span>Received</span><?= h(substr((string) $row['created_at'], 0, 16)) ?></p>
    <p><span>Status</span><span class="pill<?= in_array((string) $row['status'], ['new', 'read'], true) ? ' warn' : '' ?>"><?= h((string) $row['status']) ?></span></p>
  </div>
  <p class="q-body"><?= nl2br(h((string) $row['message'])) ?></p>

  <?php if (($row['status'] ?? '') === 'replied' && trim((string) ($row['reply_body'] ?? '')) !== ''): ?>
    <div style="margin-top:16px;padding:14px;border:1px solid var(--line,#d8dde8);border-radius:12px;background:var(--panel-soft,#f7f8fc)">
      <strong>Your reply</strong>
      <p style="margin:8px 0 0;white-space:pre-wrap"><?= h((string) $row['reply_body']) ?></p>
      <?php if (!empty($row['replied_at'])): ?>
        <p class="hint" style="margin:8px 0 0"><?= h(substr((string) $row['replied_at'], 0, 16)) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="pad-form" style="margin-top:18px;padding:0">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="reply">
    <label for="reply_body">Reply to desk
      <textarea id="reply_body" name="reply_body" required minlength="2" maxlength="4000" rows="5" placeholder="Clear next step for the client…"><?= h((string) ($row['reply_body'] ?? '')) ?></textarea>
    </label>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('send', 16) ?>Send reply</button>
    </div>
  </form>
  <form method="post" style="margin-top:12px" onsubmit="return confirm('Delete this feedback?');">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn ghost" name="action" value="delete"><?= icon('trash', 16) ?>Delete</button>
  </form>
</div>
    <?php
    layout_end();
    exit;
}

$open = desk_feedback_open(80);
$done = desk_feedback_done(40);

layout_admin_start('Feedback', $user);

$pill = static function (string $status): string {
    $class = match ($status) {
        'new', 'read' => ' warn',
        default => '',
    };
    return '<span class="pill' . $class . '">' . h($status) . '</span>';
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('help') ?>Desk feedback</h1>
    <p class="lede">Help requests from company desks. Open a row, write a reply, and the client sees it on Feedback.</p>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <h2 style="margin:4px 0 12px">Waiting on you</h2>
  <?php if (!$open): ?>
    <p class="empty">No open desk feedback.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Company</th>
          <th>From</th>
          <th>Message</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open as $f): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $f['created_at'], 0, 16)) ?></td>
            <td><strong><?= h((string) ($f['company_name'] ?? '')) ?></strong></td>
            <td>
              <?= h((string) ($f['user_name'] ?? '')) ?>
              <?php if (!empty($f['user_email'])): ?><div><a href="mailto:<?= h((string) $f['user_email']) ?>"><?= h((string) $f['user_email']) ?></a></div><?php endif; ?>
            </td>
            <td><a href="<?= h(url('admin_feedback.php?id=' . (int) $f['id'])) ?>"><?= h(clip_text((string) $f['message'], 110)) ?></a></td>
            <td><?= $pill((string) $f['status']) ?></td>
            <td><a class="btn sm" href="<?= h(url('admin_feedback.php?id=' . (int) $f['id'])) ?>"><?= icon('eye', 14) ?>Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:4px 0 12px">Replied</h2>
  <?php if (!$done): ?>
    <p class="empty">Replies you send will list here.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Company</th>
          <th>From</th>
          <th>Message</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $f): ?>
          <tr>
            <td class="mono"><?= h(substr((string) ($f['replied_at'] ?? $f['created_at']), 0, 16)) ?></td>
            <td><?= h((string) ($f['company_name'] ?? '')) ?></td>
            <td><?= h((string) ($f['user_name'] ?? '')) ?></td>
            <td><a href="<?= h(url('admin_feedback.php?id=' . (int) $f['id'])) ?>"><?= h(clip_text((string) $f['message'], 110)) ?></a></td>
            <td><a class="btn ghost sm" href="<?= h(url('admin_feedback.php?id=' . (int) $f['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
