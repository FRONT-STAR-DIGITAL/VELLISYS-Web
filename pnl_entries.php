<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_pnl();

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? db_one('SELECT * FROM pnl_entries WHERE id = ? AND company_id = ?', 'ii', [$editId, current_company_id()]) : null;
$showForm = $editing || isset($_GET['new']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'entry');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'delete') {
        pnl_entry_delete((int) post('id'));
        flash('Entry removed.');
        redirect('pnl_entries.php');
    }
    try {
        pnl_entry_save([
            'title' => post('title'),
            'kind' => post('kind'),
            'category' => post('category'),
            'amount' => post('amount'),
            'entry_date' => post('entry_date'),
            'notes' => post('notes'),
        ], (int) post('id'));
        flash('Entry saved.');
        redirect('pnl_entries.php');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
        $showForm = true;
    }
}

$entries = pnl_entries();
$kindPrefill = post('kind') ?: ($editing['kind'] ?? 'income');

layout_start('P&L ledger', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>P&amp;L ledger</h1>
    <p class="lede">Other income and costs that sit beside invoices, expenses, receipts, refunds and returns.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('pnl.php')) ?>"><?= icon('reports', 16) ?>Overview</a>
    <a class="btn" href="<?= h(url('pnl_entries.php?new=1')) ?>"><?= icon('plus', 16) ?>New entry</a>
  </div>
</div>
<?php render_pnl_subnav('pnl_entries.php'); ?>
<?php render_filters('pnl_entries.php'); ?>

<?php if ($showForm): ?>
<form class="card form-grid" method="post" style="margin-bottom:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="entry">
  <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
  <div>
    <label for="kind">Type</label>
    <select id="kind" name="kind">
      <option value="income" <?= $kindPrefill === 'income' ? 'selected' : '' ?>>Income</option>
      <option value="expense" <?= $kindPrefill === 'expense' ? 'selected' : '' ?>>Expense</option>
    </select>
  </div>
  <div>
    <label for="entry_date">Date</label>
    <input id="entry_date" type="date" name="entry_date" required value="<?= h(post('entry_date') ?: ($editing['entry_date'] ?? today())) ?>">
  </div>
  <div>
    <label for="title">Title</label>
    <input id="title" name="title" required value="<?= h(post('title') ?: ($editing['title'] ?? '')) ?>" placeholder="Rent received / Bank charges">
  </div>
  <div>
    <label for="category">Category</label>
    <input id="category" name="category" list="pnl-cats" value="<?= h(post('category') ?: ($editing['category'] ?? '')) ?>" placeholder="General">
    <datalist id="pnl-cats">
      <?php foreach (array_merge(pnl_income_categories(), pnl_expense_categories()) as $c): ?>
        <option value="<?= h($c) ?>">
      <?php endforeach; ?>
    </datalist>
  </div>
  <div>
    <label for="amount">Amount</label>
    <input id="amount" name="amount" inputmode="decimal" required value="<?= h(post('amount') ?: (isset($editing['amount']) ? (string) $editing['amount'] : '')) ?>">
  </div>
  <div style="grid-column:1/-1">
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes" rows="2"><?= h(post('notes') ?: ($editing['notes'] ?? '')) ?></textarea>
  </div>
  <div class="actions" style="grid-column:1/-1">
    <button class="btn" type="submit"><?= icon('check', 16) ?>Save entry</button>
    <a class="btn ghost" href="<?= h(url('pnl_entries.php')) ?>">Cancel</a>
  </div>
</form>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>Entries</h2></div>
  <?php if (!$entries): ?>
    <p class="empty">No ledger lines in this period.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="grid">
        <thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Category</th><th>Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $row): ?>
          <tr>
            <td><?= h(format_date($row['entry_date'])) ?></td>
            <td><?= $row['kind'] === 'income' ? 'Income' : 'Expense' ?></td>
            <td><?= h($row['title']) ?></td>
            <td><?= h($row['category']) ?></td>
            <td><?= h(money((float) $row['amount'])) ?></td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('pnl_entries.php?edit=' . (int) $row['id'])) ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Remove this entry?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn ghost sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
