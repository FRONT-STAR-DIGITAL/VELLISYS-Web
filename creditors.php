<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$rows = array_values(array_filter(list_documents('expense'), static fn ($d) => $d['status'] !== 'void' && ($d['balance'] ?? 0) > 0));
$total = documents_sum($rows, 'balance');

layout_start('Creditors', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Creditors</h1>
    <p class="lede">Suppliers you still need to pay. Record a payment against the bill. Totals sit at the foot of the table.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(export_query('creditors')) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn" href="<?= h(url('document_new.php?kind=expense')) ?>"><?= icon('expense') ?>Record expense</a>
  </div>
</div>

<?php render_filters('creditors.php'); ?>

<div class="stats">
  <div class="card stat"><?= icon('expense', 20) ?><span>Open bills</span><strong><?= count($rows) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Amount owing</span><strong><?= h(ugx($total)) ?></strong></div>
  <div class="card stat"><?= icon('hash', 20) ?><span>Paid so far</span><strong><?= h(ugx(documents_sum($rows, 'paid'))) ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Action</span><strong>Pay supplier</strong></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No unpaid bills in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Bill</th>
          <th>Supplier</th>
          <th>Date</th>
          <th>Category</th>
          <th class="right">Amount</th>
          <th class="right">Paid</th>
          <th class="right">Balance</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $doc): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
            <td><a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a></td>
            <td><?= h(format_date($doc['date'])) ?></td>
            <td><?= h($doc['expense_category'] ?: 'Other') ?></td>
            <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <td class="right mono"><?= h(money($doc['paid'], doc_currency($doc))) ?></td>
            <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Totals</td>
          <td class="right mono"><?= h(ugx(documents_sum($rows))) ?></td>
          <td class="right mono"><?= h(ugx(documents_sum($rows, 'paid'))) ?></td>
          <td class="right mono"><?= h(ugx($total)) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
