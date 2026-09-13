<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_planner();

$editId = (int) ($_GET['edit'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = planner_note_save([
                'title' => post('title'),
                'body' => post('body', '', 20000),
                'priority' => post('priority'),
                'pinned' => !empty($_POST['pinned']),
            ], (int) post('id'));
            flash('Note saved.');
            redirect('planner_notes.php?edit=' . $id);
        }
        if ($action === 'delete') {
            planner_note_delete((int) post('id'));
            flash('Note deleted.');
            redirect('planner_notes.php');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect('planner_notes.php' . ($editId ? '?edit=' . $editId : ''));
    }
}

$notes = planner_notes($q !== '' ? $q : null);
$editing = null;
if ($editId > 0) {
    foreach ($notes as $n) {
        if ((int) $n['id'] === $editId) {
            $editing = $n;
            break;
        }
    }
    if (!$editing) {
        $editing = db_one('SELECT * FROM planner_notes WHERE id = ? AND company_id = ?', 'ii', [$editId, current_company_id()]);
    }
}

layout_start($editing ? 'Edit note' : 'Notes', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('letter') ?>Notes</h1>
    <p class="lede">Owner notes for the desk. Pin the ones that must stay visible, and mark essentials.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('planner_notes.php')) ?>"><?= icon('plus', 16) ?>New note</a>
  </div>
</div>
<?php render_planner_subnav('planner_notes.php'); ?>

<div class="planner-split">
  <div class="card">
    <form class="filters" method="get" action="<?= h(url('planner_notes.php')) ?>" style="margin:0 0 12px">
      <div style="flex:1;min-width:0">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= h($q) ?>" placeholder="Title or text">
      </div>
      <button class="btn ghost filter-apply" type="submit">Find</button>
    </form>
    <?php if (!$notes): ?>
      <p class="empty">No notes yet.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($notes as $note): ?>
          <a class="work-row<?= $editing && (int) $editing['id'] === (int) $note['id'] ? ' is-on' : '' ?>" href="<?= h(url('planner_notes.php?edit=' . (int) $note['id'] . ($q !== '' ? '&q=' . urlencode($q) : ''))) ?>">
            <div>
              <strong><?= !empty($note['pinned']) ? 'Pinned · ' : '' ?><?= h($note['title']) ?></strong>
              <span class="prio-<?= h($note['priority']) ?>"><?= h(planner_priorities()[$note['priority']] ?? 'Normal') ?> · <?= h(format_date(substr((string) $note['updated_at'], 0, 10))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= $editing ? 'Edit note' : 'New note' ?></h2></div>
    <form class="form" method="post" style="padding:0 18px 18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <label for="title">Title</label>
      <input id="title" name="title" required value="<?= h((string) ($editing['title'] ?? '')) ?>" placeholder="What must not be forgotten">
      <label for="body">Note</label>
      <textarea id="body" name="body" rows="10" placeholder="Details, phone notes, decisions…"><?= h((string) ($editing['body'] ?? '')) ?></textarea>
      <label for="priority">Priority</label>
      <?php render_priority_select('priority', (string) ($editing['priority'] ?? 'normal'), 'priority'); ?>
      <label class="check"><input type="checkbox" name="pinned" value="1" <?= !empty($editing['pinned']) ? 'checked' : '' ?>> Pin to the top of the list</label>
      <div class="actions" style="margin-top:14px">
        <button class="btn" type="submit"><?= icon('check') ?>Save note</button>
        <?php if ($editing): ?>
          <button class="btn danger ghost" type="submit" name="action" value="delete" formaction="<?= h(url('planner_notes.php')) ?>" onclick="return confirm('Delete this note?');"><?= icon('trash', 16) ?>Delete</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php layout_end(); ?>
