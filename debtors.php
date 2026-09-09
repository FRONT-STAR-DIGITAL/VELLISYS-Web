<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$rows = array_values(array_filter(list_documents('invoice'), static fn ($d) => $d['status'] !== 'void' && ($d['balance'] ?? 0) > 0));
$total = documents_sum($rows, 'balance');
$overdue = array_sum(array_map(static fn ($d) => (!empty($d['due_date']) && $d['due_date'] < today()) ? $d['balance'] : 0, $rows));

layout_start('Debtors', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Debtors</h1>
    <p class="lede">Clients who still owe you. Take a receipt, send a reminder, or print the invoice. Totals sit at the foot of the table.</p>
  </div>
  <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice') ?>New invoice</a>
  <a class="btn ghost" href="<?= h(export_query('debtors')) ?>"><?= icon('download', 16) ?>Export CSV</a>
</div>

<?php render_filters('debtors.php'); ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Open invoices</span><strong><?= count($rows) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Amount owed</span><strong><?= h(ugx($total)) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Overdue</span><strong><?= h(ugx($overdue)) ?></strong></div>
  <div class="card stat"><?= icon('send', 20) ?><span>Action</span><strong>Receipt or remind</strong></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No outstanding invoices in this period.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Client</th>
          <th>Date</th>
          <th>Due</th>
          <th class="right">Amount</th>
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
            <td><?= h(format_date($doc['due_date'])) ?></td>
            <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <td><span class="pill<?= invoice_status_label($doc) === 'Overdue' ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Totals</td>
          <td class="right mono"><?= h(ugx(documents_sum($rows))) ?></td>
          <td class="right mono"><?= h(ugx($total)) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
