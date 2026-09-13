<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_planner();

$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $month = preg_match('/^\d{4}-\d{2}$/', post('month_key')) ? post('month_key') : $month;
    try {
        if ($action === 'save') {
            $id = planner_budget_save([
                'title' => post('title'),
                'category' => post('category') ?: 'General',
                'kind' => post('kind'),
                'amount' => post('amount'),
                'month_key' => $month,
                'notes' => post('notes', '', 2000),
            ], (int) post('id'));
            flash('Budget line saved.');
            redirect('planner_budget.php?month=' . urlencode($month) . '&edit=' . $id);
        }
        if ($action === 'delete') {
            planner_budget_delete((int) post('id'));
            flash('Budget line removed.');
            redirect('planner_budget.php?month=' . urlencode($month));
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        redirect('planner_budget.php?month=' . urlencode($month));
    }
}

$items = planner_budget_items($month);
$actuals = planner_budget_actuals($month);
$editing = null;
foreach ($items as $row) {
    if ((int) $row['id'] === $editId) {
        $editing = $row;
        break;
    }
}
$targetIncome = 0.0;
$targetExpense = 0.0;
foreach ($items as $row) {
    if ($row['kind'] === 'income') {
        $targetIncome += (float) $row['amount'];
    } else {
        $targetExpense += (float) $row['amount'];
    }
}
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$monthLabel = date('F Y', strtotime($month . '-01'));

layout_start('Budget', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Budget</h1>
    <p class="lede">Set income and spend targets for the month, then compare them with invoices and expenses already on the desk.</p>
  </div>
  <div class="actions page-actions month-nav">
    <a class="btn ghost" href="<?= h(url('planner_budget.php?month=' . urlencode($prev))) ?>"><?= icon('arrow-left', 16) ?></a>
    <a class="btn ghost" href="<?= h(url('planner_budget.php?month=' . urlencode(date('Y-m')))) ?>">This month</a>
    <a class="btn ghost" href="<?= h(url('planner_budget.php?month=' . urlencode($next))) ?>"><?= icon('arrow-right', 16) ?></a>
  </div>
</div>
<?php render_planner_subnav('planner_budget.php'); ?>

<p class="planner-month-label"><?= h($monthLabel) ?></p>
<div class="stats">
  <div class="card stat"><span>Income target</span><strong><?= h(money($targetIncome)) ?></strong></div>
  <div class="card stat"><span>Invoiced (actual)</span><strong><?= h(money($actuals['income'])) ?></strong><em><?= (int) $actuals['invoice_count'] ?> invoices</em></div>
  <div class="card stat"><span>Spend budget</span><strong><?= h(money($targetExpense)) ?></strong></div>
  <div class="card stat"><span>Expenses (actual)</span><strong><?= h(money($actuals['expense'])) ?></strong><em><?= (int) $actuals['expense_count'] ?> expenses</em></div>
</div>

<div class="planner-split">
  <div class="card">
    <div class="card-head">
      <h2>Budget lines</h2>
      <a class="btn ghost sm" href="<?= h(url('planner_budget.php?month=' . urlencode($month))) ?>"><?= icon('plus', 14) ?>Add</a>
    </div>
    <?php if (!$items): ?>
      <p class="empty">No targets for this month yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>Line</th><th>Kind</th><th>Category</th><th class="right">Amount</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($items as $row): ?>
              <tr>
                <td><a href="<?= h(url('planner_budget.php?month=' . urlencode($month) . '&edit=' . (int) $row['id'])) ?>"><strong><?= h($row['title']) ?></strong></a></td>
                <td><?= h(planner_budget_kinds()[$row['kind']] ?? $row['kind']) ?></td>
                <td><?= h($row['category']) ?></td>
                <td class="right mono"><?= h(money((float) $row['amount'])) ?></td>
                <td class="row-actions">
                  <a class="btn ghost sm" href="<?= h(url('planner_budget.php?month=' . urlencode($month) . '&edit=' . (int) $row['id'])) ?>"><?= icon('pencil', 14) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="hint" style="padding:12px 16px 16px;margin:0">Actuals pull from issued invoices and expenses in <?= h($monthLabel) ?>. <a href="<?= h(url('documents.php?kind=expense')) ?>">Open expenses</a> · <a href="<?= h(url('documents.php?kind=invoice')) ?>">Open invoices</a></p>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= $editing ? 'Edit line' : 'Add line' ?></h2></div>
    <form class="form" method="post" style="padding:0 18px 18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <input type="hidden" name="month_key" value="<?= h($month) ?>">
      <label for="title">Name</label>
      <input id="title" name="title" required value="<?= h((string) ($editing['title'] ?? '')) ?>" placeholder="Rent, sales target, fuel…">
      <label for="kind">Kind</label>
      <select id="kind" name="kind">
        <?php foreach (planner_budget_kinds() as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= (($editing['kind'] ?? 'expense') === $key) ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="category">Category</label>
      <input id="category" name="category" value="<?= h((string) ($editing['category'] ?? 'General')) ?>">
      <label for="amount">Amount</label>
      <input id="amount" name="amount" inputmode="decimal" required value="<?= h($editing ? (string) $editing['amount'] : '') ?>" placeholder="0">
      <label for="notes">Note</label>
      <textarea id="notes" name="notes" rows="3"><?= h((string) ($editing['notes'] ?? '')) ?></textarea>
      <div class="actions" style="margin-top:14px">
        <button class="btn" type="submit"><?= icon('check') ?>Save</button>
        <?php if ($editing): ?>
          <button class="btn danger ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this budget line?');"><?= icon('trash', 16) ?>Delete</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php layout_end(); ?>
