<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$brand = branding();

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'invoice' ORDER BY d.date DESC, d.id DESC"));
$open = array_values(array_filter($invoices, static fn ($d) => $d['status'] !== 'void' && $d['balance'] > 0));
$overdue = array_values(array_filter($open, static fn ($d) => !empty($d['due_date']) && $d['due_date'] < today()));
$monthStart = date('Y-m-01');
$incomeMonth = 0;
foreach ($invoices as $d) {
    if ($d['status'] !== 'void' && $d['date'] >= $monthStart) {
        $incomeMonth += $d['totals']['total'];
    }
}
$expMonth = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'expense' AND d.status = 'issued' AND d.date >= ?", 's', [$monthStart]));
$expenseMonth = 0;
foreach ($expMonth as $d) {
    $expenseMonth += $d['totals']['total'];
}
$quotes = (int) (db_one("SELECT COUNT(*) c FROM documents WHERE kind='quotation' AND status='issued'")['c'] ?? 0);
$recent = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id ORDER BY d.id DESC LIMIT 8"));

layout_start('Desk', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('desk') ?>Desk</h1>
    <p class="lede"><?= h($brand['name']) ?> · <?= h(ucfirst($brand['plan'])) ?> plan. Brand colour drives the bar on the left — change it under Branding.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice') ?>New invoice</a>
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation') ?>New quotation</a>
    <a class="btn ghost" href="<?= h(url('client_edit.php')) ?>"><?= icon('clients') ?>New client</a>
  </div>
</div>

<div class="stats">
  <a class="card stat" href="<?= h(url('documents.php?kind=invoice')) ?>">
    <?= icon('invoice', 20) ?>
    <span>Open invoices</span>
    <strong><?= h(ugx(array_sum(array_column($open, 'balance')))) ?></strong>
    <em><?= count($open) ?> unpaid</em>
  </a>
  <a class="card stat" href="<?= h(url('documents.php?kind=invoice')) ?>">
    <?= icon('alert', 20) ?>
    <span>Overdue</span>
    <strong><?= h(ugx(array_sum(array_map(static fn ($d) => $d['balance'], $overdue)))) ?></strong>
    <em><?= count($overdue) ?> past due</em>
  </a>
  <a class="card stat" href="<?= h(url('reports.php')) ?>">
    <?= icon('reports', 20) ?>
    <span>Invoiced this month</span>
    <strong><?= h(ugx($incomeMonth)) ?></strong>
    <em>Expenses <?= h(ugx($expenseMonth)) ?></em>
  </a>
  <a class="card stat" href="<?= h(url('documents.php?kind=quotation')) ?>">
    <?= icon('quotation', 20) ?>
    <span>Open quotations</span>
    <strong><?= $quotes ?></strong>
    <em>Waiting to convert</em>
  </a>
</div>

<div class="card">
  <div class="card-head">
    <h2><?= icon('invoice', 16) ?>Recent documents</h2>
    <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">All invoices</a>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">Nothing issued yet. Use Quick add or New invoice.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
          <th>Type</th>
          <th>Client</th>
          <th>Date</th>
          <th class="right">Amount</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $doc): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
            <td><?= h(kind_meta($doc['kind'])['singular']) ?></td>
            <td><a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a></td>
            <td><?= h(format_date($doc['date'])) ?></td>
            <td class="right mono"><?= h(ugx($doc['totals']['total'])) ?></td>
            <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
