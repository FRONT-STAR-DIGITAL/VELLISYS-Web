<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_planner();

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : today();
$editId = (int) ($_GET['edit'] ?? 0);
$year = (int) substr($date, 0, 4);
$month = (int) substr($date, 5, 2);
$monthKey = sprintf('%04d-%02d', $year, $month);
$monthStart = $monthKey . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = planner_event_save([
                'title' => post('title'),
                'body' => post('body', '', 8000),
                'event_date' => post('event_date'),
                'event_time' => post('event_time'),
                'end_date' => post('end_date'),
                'kind' => post('kind'),
                'priority' => post('priority'),
                'party_id' => (int) post('party_id'),
                'document_id' => (int) post('document_id'),
                'done' => !empty($_POST['done']),
            ], (int) post('id'));
            $saved = db_one('SELECT event_date FROM planner_events WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
            flash('Event saved.');
            redirect('planner_calendar.php?date=' . urlencode((string) ($saved['event_date'] ?? $date)) . '&edit=' . $id);
        }
        if ($action === 'toggle') {
            planner_event_toggle_done((int) post('id'));
            flash('Event updated.');
            redirect('planner_calendar.php?date=' . urlencode(post('event_date') ?: $date));
        }
        if ($action === 'delete') {
            $d = post('event_date') ?: $date;
            planner_event_delete((int) post('id'));
            flash('Event deleted.');
            redirect('planner_calendar.php?date=' . urlencode($d));
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect('planner_calendar.php?date=' . urlencode($date));
    }
}

$monthEvents = planner_events_between($monthStart, $monthEnd);
$byDay = [];
foreach ($monthEvents as $ev) {
    $byDay[$ev['event_date']][] = $ev;
}
$dayEvents = planner_events_on($date);
$editing = null;
foreach ($dayEvents as $ev) {
    if ((int) $ev['id'] === $editId) {
        $editing = $ev;
        break;
    }
}
if ($editId && !$editing) {
    $editing = db_one(
        'SELECT e.*, p.name AS party_name FROM planner_events e LEFT JOIN parties p ON p.id = e.party_id WHERE e.id = ? AND e.company_id = ?',
        'ii',
        [$editId, current_company_id()]
    );
    if ($editing) {
        $date = (string) $editing['event_date'];
    }
}
$parties = db_all('SELECT id, name FROM parties WHERE company_id = ? ORDER BY name LIMIT 400', 'i', [current_company_id()]);
$cells = planner_month_matrix($year, $month);
$prevMonth = date('Y-m-d', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($monthStart . ' +1 month'));
$prefTitle = trim((string) ($_GET['title'] ?? ''));
$prefKind = planner_normalize_event_kind((string) ($_GET['kind'] ?? 'appointment'));
$prefParty = (int) ($_GET['party'] ?? 0);
$prefDoc = (int) ($_GET['document'] ?? 0);

layout_start('Calendar', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('calendar') ?>Calendar</h1>
    <p class="lede">Pick a day, set programmes, appointments and deadlines, and mark what is essential.</p>
  </div>
  <div class="actions page-actions month-nav" role="group" aria-label="Month">
    <a class="btn ghost" href="<?= h(url('planner_calendar.php?date=' . urlencode($prevMonth))) ?>" aria-label="Previous month"><?= icon('arrow-left', 16) ?></a>
    <a class="btn ghost" href="<?= h(url('planner_calendar.php?date=' . urlencode(today()))) ?>">Today</a>
    <a class="btn ghost" href="<?= h(url('planner_calendar.php?date=' . urlencode($nextMonth))) ?>" aria-label="Next month"><?= icon('arrow-right', 16) ?></a>
  </div>
</div>
<?php render_planner_subnav('planner_calendar.php'); ?>

<div class="planner-cal-layout">
  <div class="card planner-cal-card">
    <div class="card-head">
      <h2><?= h(date('F Y', strtotime($monthStart))) ?></h2>
    </div>
    <div class="planner-cal-grid">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?>
        <div class="planner-cal-dow"><?= $dow ?></div>
      <?php endforeach; ?>
      <?php foreach ($cells as $cell): ?>
        <?php if ($cell === null): ?>
          <div class="planner-cal-day is-empty"></div>
        <?php else:
            $count = count($byDay[$cell] ?? []);
            $hasEssential = false;
            foreach ($byDay[$cell] ?? [] as $ev) {
                if (in_array($ev['priority'], ['essential', 'high'], true)) {
                    $hasEssential = true;
                    break;
                }
            }
            ?>
          <a class="planner-cal-day<?= $cell === $date ? ' is-on' : '' ?><?= $cell === today() ? ' is-today' : '' ?><?= $hasEssential ? ' has-essential' : '' ?>" href="<?= h(url('planner_calendar.php?date=' . urlencode($cell))) ?>">
            <span class="planner-cal-num"><?= (int) substr($cell, 8, 2) ?></span>
            <?php if ($count): ?><span class="planner-cal-dots" title="<?= $count ?> event<?= $count === 1 ? '' : 's' ?>"><?= str_repeat('•', min(3, $count)) ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="planner-cal-side">
    <div class="card">
      <div class="card-head">
        <h2><?= h(format_date($date)) ?></h2>
        <a class="btn ghost sm" href="<?= h(url('planner_calendar.php?date=' . urlencode($date))) ?>"><?= icon('plus', 14) ?>New</a>
      </div>
      <?php if (!$dayEvents): ?>
        <p class="empty">No events on this day.</p>
      <?php else: ?>
        <div class="work-list">
          <?php foreach ($dayEvents as $ev): ?>
            <div class="work-row planner-event-row<?= !empty($ev['done']) ? ' is-done' : '' ?>">
              <a href="<?= h(url('planner_calendar.php?date=' . urlencode($date) . '&edit=' . (int) $ev['id'])) ?>">
                <strong><?= h($ev['title']) ?></strong>
                <span><?= $ev['event_time'] ? h(substr((string) $ev['event_time'], 0, 5)) . ' · ' : '' ?><?= h(planner_event_kinds()[$ev['kind']] ?? $ev['kind']) ?> · <?= h(planner_priorities()[$ev['priority']] ?? '') ?><?= !empty($ev['party_name']) ? ' · ' . h($ev['party_name']) : '' ?></span>
              </a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                <input type="hidden" name="event_date" value="<?= h($date) ?>">
                <button class="btn ghost sm" type="submit" title="<?= !empty($ev['done']) ? 'Mark open' : 'Mark done' ?>"><?= icon(!empty($ev['done']) ? 'check' : 'clock', 14) ?></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= $editing ? 'Edit event' : 'Set event' ?></h2></div>
      <form class="form" method="post" style="padding:0 18px 18px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <input type="hidden" name="document_id" value="<?= (int) ($editing['document_id'] ?? $prefDoc) ?>">
        <label for="title">Title</label>
        <input id="title" name="title" required value="<?= h((string) ($editing['title'] ?? $prefTitle)) ?>" placeholder="Client visit, tax filing, staff meeting…">
        <div class="form-grid two">
          <div>
            <label for="event_date">Date</label>
            <input id="event_date" name="event_date" type="date" required value="<?= h((string) ($editing['event_date'] ?? $date)) ?>">
          </div>
          <div>
            <label for="event_time">Time</label>
            <input id="event_time" name="event_time" type="time" value="<?= h($editing && $editing['event_time'] ? substr((string) $editing['event_time'], 0, 5) : '') ?>">
          </div>
          <div>
            <label for="end_date">Ends</label>
            <input id="end_date" name="end_date" type="date" value="<?= h((string) ($editing['end_date'] ?? '')) ?>">
          </div>
          <div>
            <label for="kind">Type</label>
            <select id="kind" name="kind">
              <?php foreach (planner_event_kinds() as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= (($editing['kind'] ?? $prefKind) === $key) ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="priority">Priority</label>
            <?php render_priority_select('priority', (string) ($editing['priority'] ?? 'normal'), 'priority'); ?>
          </div>
          <div>
            <label for="party_id">Client / payee</label>
            <select id="party_id" name="party_id">
              <option value="0">None</option>
              <?php foreach ($parties as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= ((int) ($editing['party_id'] ?? $prefParty) === (int) $p['id']) ? 'selected' : '' ?>><?= h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <label for="body">Details</label>
        <textarea id="body" name="body" rows="4"><?= h((string) ($editing['body'] ?? '')) ?></textarea>
        <label class="check"><input type="checkbox" name="done" value="1" <?= !empty($editing['done']) ? 'checked' : '' ?>> Marked done</label>
        <div class="actions" style="margin-top:14px">
          <button class="btn" type="submit"><?= icon('check') ?>Save event</button>
          <?php if ($editing): ?>
            <button class="btn danger ghost" type="submit" name="action" value="delete" onclick="return confirm('Delete this event?');"><?= icon('trash', 16) ?>Delete</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<?php layout_end(); ?>
