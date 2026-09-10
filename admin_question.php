<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$id = (int) ($_GET['id'] ?? post('id'));
$q = $id ? db_one('SELECT * FROM questions WHERE id = ?', 'i', [$id]) : null;
if (!$q) {
    flash('That question was not found.', 'err');
    redirect('admin_questions.php');
}

if ($q['status'] === 'new' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    db_exec("UPDATE questions SET status = 'read' WHERE id = ?", 'i', [$id]);
    $q['status'] = 'read';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'read') {
        db_exec("UPDATE questions SET status = 'read' WHERE id = ?", 'i', [$id]);
        flash('Marked as read.');
    } elseif ($action === 'replied') {
        db_exec("UPDATE questions SET status = 'replied' WHERE id = ?", 'i', [$id]);
        flash('Marked as replied.');
    }
    redirect('admin_question.php?id=' . $id);
}

$q = db_one('SELECT * FROM questions WHERE id = ?', 'i', [$id]);
$mailto = 'mailto:' . rawurlencode((string) $q['email'])
    . '?subject=' . rawurlencode('Re: your question to Vellisys')
    . '&body=' . rawurlencode("Hello " . $q['name'] . ",\n\nThank you for writing to Vellisys.\n\n");

layout_admin_start($q['name'], $user);
?>
<div class="page-head">
  <div>
    <p class="desk-kicker"><a href="<?= h(url('admin_questions.php')) ?>">Questions</a> / <?= h($q['name']) ?></p>
    <h1><?= icon('help') ?>Question</h1>
    <p class="lede">Full message and contact details. Opening a new question marks it as read.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= h($mailto) ?>"><?= icon('letter', 16) ?>Reply by email</a>
    <a class="btn ghost" href="<?= h(url('admin_questions.php')) ?>">Inbox</a>
  </div>
</div>

<div class="card q-detail">
  <div class="q-detail-meta">
    <p><span>From</span><strong><?= h($q['name']) ?></strong></p>
    <p><span>Email</span><a href="mailto:<?= h($q['email']) ?>"><?= h($q['email']) ?></a></p>
    <p><span>Phone</span><?= $q['phone'] !== '' ? h($q['phone']) : 'Not given' ?></p>
    <p><span>Received</span><?= h(substr((string) $q['created_at'], 0, 16)) ?></p>
    <p><span>Status</span><span class="pill<?= $q['status'] === 'new' ? ' warn' : '' ?>"><?= h($q['status']) ?></span></p>
  </div>
  <p class="q-body"><?= nl2br(h($q['message'])) ?></p>
  <div class="actions" style="margin-top:16px">
    <a class="btn" href="<?= h($mailto) ?>"><?= icon('letter', 16) ?>Reply by email</a>
    <?php if ($q['status'] !== 'replied'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn ghost" name="action" value="replied"><?= icon('send', 16) ?>Mark replied</button>
    </form>
    <?php endif; ?>
    <?php if ($q['status'] === 'new'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn ghost" name="action" value="read"><?= icon('check', 16) ?>Mark read</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php layout_end(); ?>
