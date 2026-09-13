<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_planner();

$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$today = today();
$weekEnd = date('Y-m-d', strtotime($today . ' +6 days'));
$upcoming = planner_events_between($today, $weekEnd);
$essentials = array_values(array_filter(
    planner_notes(),
    static fn (array $n): bool => in_array($n['priority'], ['essential', 'high'], true) || !empty($n['pinned'])
));
$essentials = array_slice($essentials, 0, 6);
$budget = planner_budget_items($month);
$actuals = planner_budget_actuals($month);
$budgetIncome = 0.0;
$budgetExpense = 0.0;
foreach ($budget as $row) {
    if ($row['kind'] === 'income') {
        $budgetIncome += (float) $row['amount'];
    } else {
        $budgetExpense += (float) $row['amount'];
    }
}
$dueInvoices = array_values(array_filter(
    planner_due_invoices(10),
    static fn (array $d): bool => (float) ($d['balance'] ?? 0) > 0.009
));
$dueInvoices = array_slice($dueInvoices, 0, 5);

layout_start('Planner', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('calendar') ?>Planner</h1>
    <p class="lede">Notes, budget targets and the calendar for programmes, appointments and deadlines — with priority when it matters.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('planner_notes.php')) ?>"><?= icon('letter', 16) ?>Note</a>
    <a class="btn ghost" href="<?= h(url('planner_budget.php?month=' . urlencode($month))) ?>"><?= icon('bank', 16) ?>Budget</a>
    <a class="btn" href="<?= h(url('planner_calendar.php?date=' . urlencode($today))) ?>"><?= icon('plus', 16) ?>Event</a>
  </div>
</div>
<?php render_planner_subnav('planner.php'); ?>

<div class="stats">
  <div class="card stat"><?= icon('calendar', 20) ?><span>This week</span><strong><?= count($upcoming) ?></strong></div>
  <div class="card stat"><?= icon('flag', 20) ?><span>Essentials</span><strong><?= count($essentials) ?></strong></div>
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income vs target</span><strong><?= h(money($actuals['income'])) ?></strong><em>of <?= h(money($budgetIncome)) ?></em></div>
  <div class="card stat"><?= icon('wallet', 20) ?><span>Spend vs budget</span><strong><?= h(money($actuals['expense'])) ?></strong><em>of <?= h(money($budgetExpense)) ?></em></div>
</div>

<div class="desk-grid">
  <div class="card">
    <div class="card-head">
      <h2><?= icon('calendar', 16) ?>Next 7 days</h2>
      <a class="btn ghost sm" href="<?= h(url('planner_calendar.php')) ?>">Calendar</a>
    </div>
    <?php if (!$upcoming): ?>
      <p class="empty">Nothing scheduled. <a href="<?= h(url('planner_calendar.php?date=' . urlencode($today))) ?>">Add an appointment or deadline</a>.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($upcoming as $ev): ?>
          <a class="work-row" href="<?= h(url('planner_calendar.php?date=' . urlencode((string) $ev['event_date']) . '&edit=' . (int) $ev['id'])) ?>">
            <div>
              <strong><?= h($ev['title']) ?></strong>
              <span><?= h(format_date($ev['event_date'])) ?><?= $ev['event_time'] ? ' · ' . h(substr((string) $ev['event_time'], 0, 5)) : '' ?> · <?= h(planner_event_kinds()[$ev['kind']] ?? $ev['kind']) ?><?= in_array($ev['priority'], ['essential', 'high'], true) ? ' · ' . h(planner_priorities()[$ev['priority']]) : '' ?></span>
            </div>
            <b class="prio-<?= h($ev['priority']) ?>"><?= $ev['event_date'] === $today ? 'Today' : h(format_date($ev['event_date'])) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <h2><?= icon('flag', 16) ?>Priority notes</h2>
      <a class="btn ghost sm" href="<?= h(url('planner_notes.php')) ?>">All notes</a>
    </div>
    <?php if (!$essentials): ?>
      <p class="empty">Pin a note or mark one essential. <a href="<?= h(url('planner_notes.php')) ?>">Write a note</a>.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($essentials as $note): ?>
          <a class="work-row" href="<?= h(url('planner_notes.php?edit=' . (int) $note['id'])) ?>">
            <div>
              <strong><?= !empty($note['pinned']) ? 'Pinned · ' : '' ?><?= h($note['title']) ?></strong>
              <span><?= h(planner_priorities()[$note['priority']] ?? 'Normal') ?> · <?= h(format_date(substr((string) $note['updated_at'], 0, 10))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2><?= icon('invoice', 16) ?>Invoice deadlines from the books</h2>
    <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">Invoices</a>
  </div>
  <?php if (!$dueInvoices): ?>
    <p class="empty">No open invoice dues in the next 10 days.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Client</th><th>Number</th><th>Due</th><th class="right">Balance</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($dueInvoices as $doc): ?>
            <tr>
              <td><?= h($doc['party_name']) ?></td>
              <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
              <td class="date-cell"><?= h(format_date($doc['due_date'])) ?><?= $doc['due_date'] < $today ? ' · overdue' : '' ?></td>
              <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
              <td class="row-actions">
                <a class="btn ghost sm" href="<?= h(url('planner_calendar.php?date=' . urlencode((string) $doc['due_date']) . '&title=' . urlencode('Collect ' . $doc['number']) . '&kind=deadline&party=' . (int) $doc['party_id'] . '&document=' . (int) $doc['id'])) ?>"><?= icon('plus', 14) ?>Deadline</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
