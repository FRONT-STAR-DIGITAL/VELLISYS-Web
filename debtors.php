<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$rows = array_values(array_filter(list_documents('invoice'), static fn ($d) => $d['status'] !== 'void' && ($d['balance'] ?? 0) > 0));
$total = documents_sum($rows, 'balance');
$overdue = array_sum(array_map(static fn ($d) => (!empty($d['due_date']) && $d['due_date'] < today()) ? $d['balance'] : 0, $rows));
$byClient = [];
foreach ($rows as $doc) {
    $pid = (int) $doc['party_id'];
    if (!isset($byClient[$pid])) {
        $byClient[$pid] = ['id' => $pid, 'name' => $doc['party_name'], 'invoices' => 0, 'balance' => 0.0];
    }
    $byClient[$pid]['invoices']++;
    $byClient[$pid]['balance'] += convert_money((float) $doc['balance'], doc_currency($doc), default_currency());
}
uasort($byClient, static fn ($a, $b) => $b['balance'] <=> $a['balance']);

layout_start('Debtors', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Debtors</h1>
    <p class="lede">Clients who still owe you, including balances left after part payments. Mixed currencies convert at your UGX / USD rate. Take a receipt, email a reminder from the company mailbox, or print the invoice.</p>
  </div>
  <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice') ?>New invoice</a>
  <a class="btn ghost" href="<?= h(export_query('debtors')) ?>"><?= icon('download', 16) ?>Export CSV</a>
</div>

<?php render_filters('debtors.php'); ?>

<?php if ($byClient): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>By client</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Client</th>
        <th class="right">Open invoices</th>
        <th class="right">Balance</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byClient as $c): ?>
        <tr>
          <td><a href="<?= h(url('client_view.php?id=' . $c['id'])) ?>"><?= h($c['name']) ?></a></td>
          <td class="right mono"><?= (int) $c['invoices'] ?></td>
          <td class="right mono"><?= h(ugx($c['balance'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Open invoices</span><strong><?= count($rows) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Amount owed</span><strong><?= h(ugx($total)) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Overdue</span><strong><?= h(ugx($overdue)) ?></strong></div>
  <div class="card stat"><?= icon('send', 20) ?><span>Action</span><strong>Receipt or email</strong></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No outstanding invoices in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
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
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
