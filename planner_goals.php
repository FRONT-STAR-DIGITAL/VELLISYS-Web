<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_planner();

$editId = (int) ($_GET['edit'] ?? 0);
$filter = (string) ($_GET['status'] ?? 'open');
if (!in_array($filter, ['open', 'hit', 'all'], true)) {
    $filter = 'open';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = planner_goal_save([
                'title' => post('title'),
                'body' => post('body', '', 20000),
                'priority' => post('priority'),
                'due_date' => post('due_date'),
                'status' => !empty($_POST['hit']) ? 'hit' : 'open',
            ], (int) post('id'));
            flash('Task saved.');
            redirect('planner_goals.php?edit=' . $id);
        }
        if ($action === 'delete') {
            planner_goal_delete((int) post('id'));
            flash('Task deleted.');
            redirect('planner_goals.php');
        }
        if ($action === 'hit') {
            $gid = (int) post('id');
            $row = db_one('SELECT * FROM planner_goals WHERE id = ? AND company_id = ?', 'ii', [$gid, current_company_id()]);
            if ($row) {
                planner_goal_save([
                    'title' => $row['title'],
                    'body' => $row['body'] ?? '',
                    'priority' => $row['priority'],
                    'due_date' => $row['due_date'] ?? '',
                    'status' => 'hit',
                ], $gid);
                flash('Marked as hit.');
            }
            redirect('planner_goals.php');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect('planner_goals.php' . ($editId ? '?edit=' . $editId : ''));
    }
}

$listFilter = $filter === 'all' ? null : $filter;
$goals = planner_goals($listFilter);
$editing = null;
if ($editId > 0) {
    $editing = db_one('SELECT * FROM planner_goals WHERE id = ? AND company_id = ?', 'ii', [$editId, current_company_id()]);
}

layout_start($editing ? 'Edit task' : 'Tasks', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('flag') ?>Tasks and goals</h1>
    <p class="lede">What this desk still has to hit. Mark a task done when the goal is met. Due dates also appear in the bell.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('planner_goals.php')) ?>"><?= icon('plus', 16) ?>New task</a>
  </div>
</div>
<?php render_planner_subnav('planner_goals.php'); ?>

<div class="filter-chips" style="margin:0 0 14px">
  <a class="chip<?= $filter === 'open' ? ' is-on' : '' ?>" href="<?= h(url('planner_goals.php')) ?>">Open</a>
  <a class="chip<?= $filter === 'hit' ? ' is-on' : '' ?>" href="<?= h(url('planner_goals.php?status=hit')) ?>">Hit</a>
  <a class="chip<?= $filter === 'all' ? ' is-on' : '' ?>" href="<?= h(url('planner_goals.php?status=all')) ?>">All</a>
</div>

<div class="planner-split">
  <div class="card">
    <?php if (!$goals): ?>
      <p class="empty"><?= $filter === 'hit' ? 'No completed goals yet.' : 'No open tasks. Add one to the right.' ?></p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($goals as $goal): ?>
          <div class="planner-event-row<?= ($goal['status'] ?? '') === 'hit' ? ' is-done' : '' ?>">
            <a href="<?= h(url('planner_goals.php?edit=' . (int) $goal['id'] . ($filter !== 'open' ? '&status=' . urlencode($filter) : ''))) ?>">
              <div>
                <strong><?= h($goal['title']) ?></strong>
                <span class="prio-<?= h($goal['priority']) ?>"><?= h(planner_priorities()[$goal['priority']] ?? 'Normal') ?><?php if (!empty($goal['due_date'])): ?> · Due <?= h(format_date($goal['due_date'])) ?><?php endif; ?><?= ($goal['status'] ?? '') === 'hit' ? ' · Hit' : '' ?></span>
              </div>
            </a>
            <?php if (($goal['status'] ?? '') !== 'hit'): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="hit">
                <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
                <button class="btn ghost sm" type="submit">Hit</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= $editing ? 'Edit task' : 'New task' ?></h2></div>
    <form class="form" method="post" style="padding:0 18px 18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <label for="title">Goal</label>
      <input id="title" name="title" required value="<?= h((string) ($editing['title'] ?? '')) ?>" placeholder="Collect overdue invoices this week">
      <label for="body">How you will hit it</label>
      <textarea id="body" name="body" rows="6" placeholder="Steps, owners, or a target figure"><?= h((string) ($editing['body'] ?? '')) ?></textarea>
      <label for="due_date">Due</label>
      <input id="due_date" name="due_date" type="date" value="<?= h((string) ($editing['due_date'] ?? '')) ?>">
      <label for="priority">Priority</label>
      <?php render_priority_select('priority', (string) ($editing['priority'] ?? 'normal'), 'priority'); ?>
      <label class="check"><input type="checkbox" name="hit" value="1" <?= ($editing['status'] ?? '') === 'hit' ? 'checked' : '' ?>> Already hit</label>
      <div class="actions" style="margin-top:14px">
        <button class="btn" type="submit"><?= icon('check') ?>Save task</button>
        <?php if ($editing): ?>
          <button class="btn danger ghost" type="submit" name="action" value="delete" formaction="<?= h(url('planner_goals.php')) ?>" onclick="return confirm('Delete this task?');"><?= icon('trash', 16) ?>Delete</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php layout_end(); ?>
