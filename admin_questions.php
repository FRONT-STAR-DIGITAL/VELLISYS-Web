<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) post('id');
    $row = $id ? db_one('SELECT * FROM questions WHERE id = ?', 'i', [$id]) : null;
    if (!$row) {
        flash('That question was not found.', 'err');
        redirect('admin_questions.php');
    }
    $action = post('action');
    if ($action === 'read' && $row['status'] === 'new') {
        db_exec("UPDATE questions SET status = 'read' WHERE id = ?", 'i', [$id]);
        flash('Marked as read.');
    } elseif ($action === 'replied') {
        db_exec("UPDATE questions SET status = 'replied' WHERE id = ?", 'i', [$id]);
        flash('Marked as replied.');
    }
    redirect('admin_questions.php');
}

$open = db_all("SELECT * FROM questions WHERE status IN ('new','read') ORDER BY FIELD(status,'new','read'), id DESC");
$done = db_all("SELECT * FROM questions WHERE status = 'replied' ORDER BY id DESC LIMIT 40");

layout_admin_start('Questions', $user);

$pill = static function (string $status): string {
    $class = match ($status) {
        'new' => ' warn',
        'replied' => '',
        default => '',
    };
    return '<span class="pill' . $class . '">' . h($status) . '</span>';
};

$rowActions = static function (array $q): void {
    ?>
    <div class="actions">
      <a class="btn sm" href="<?= h(url('admin_question.php?id=' . $q['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
      <a class="btn ghost sm" href="mailto:<?= h($q['email']) ?>?subject=<?= h(rawurlencode('Re: your question to Vellisys')) ?>"><?= icon('letter', 14) ?>Reply</a>
      <?php if ($q['status'] !== 'replied'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <button class="btn ghost sm" name="action" value="replied"><?= icon('send', 14) ?>Replied</button>
      </form>
      <?php endif; ?>
    </div>
    <?php
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('help') ?>Questions</h1>
    <p class="lede">People write from Have a Question on the landing page. Open a row for the full message. A copy is also emailed to <?= h(product_email()) ?>.</p>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <h2 style="margin:4px 0 12px">Waiting on you</h2>
  <?php if (!$open): ?>
    <p class="empty">No open questions. New notes from the website land here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>From</th>
          <th>Question</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open as $q): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $q['created_at'], 0, 16)) ?></td>
            <td>
              <strong><?= h($q['name']) ?></strong>
              <div><a href="mailto:<?= h($q['email']) ?>"><?= h($q['email']) ?></a></div>
              <?php if ($q['phone'] !== ''): ?><div class="mono"><?= h($q['phone']) ?></div><?php endif; ?>
            </td>
            <td><a href="<?= h(url('admin_question.php?id=' . $q['id'])) ?>"><?= h(clip_text((string) $q['message'], 110)) ?></a></td>
            <td><?= $pill($q['status']) ?></td>
            <td class="row-actions"><?php $rowActions($q); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:4px 0 12px">Replied</h2>
  <?php if (!$done): ?>
    <p class="empty">Questions you mark as replied will list here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>From</th>
          <th>Question</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $q): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $q['created_at'], 0, 16)) ?></td>
            <td>
              <strong><?= h($q['name']) ?></strong>
              <div><?= h($q['email']) ?></div>
            </td>
            <td><a href="<?= h(url('admin_question.php?id=' . $q['id'])) ?>"><?= h(clip_text((string) $q['message'], 110)) ?></a></td>
            <td><?= $pill($q['status']) ?></td>
            <td class="row-actions">
              <a class="btn sm" href="<?= h(url('admin_question.php?id=' . $q['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
