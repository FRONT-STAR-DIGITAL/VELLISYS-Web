<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'invoice' AND d.status = 'issued'"));
$expenses = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'expense' AND d.status = 'issued'"));
$receipts = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = 'receipt' AND d.status = 'issued'"));

$income = 0;
$outputVat = 0;
$debtors = [];
foreach ($invoices as $d) {
    $income += $d['totals']['net'];
    $outputVat += $d['totals']['vat'];
    if ($d['balance'] > 0) {
        $age = $d['due_date'] ? (int) floor((time() - strtotime($d['due_date'])) / 86400) : 0;
        $bucket = 'Current';
        if ($age > 90) {
            $bucket = '90+';
        } elseif ($age > 60) {
            $bucket = '61–90';
        } elseif ($age > 30) {
            $bucket = '31–60';
        } elseif ($age > 0) {
            $bucket = '1–30';
        }
        $debtors[] = $d + ['bucket' => $bucket, 'age' => max(0, $age)];
    }
}

$costs = 0;
$inputVat = 0;
$byCat = [];
foreach ($expenses as $d) {
    $costs += $d['totals']['net'];
    $inputVat += $d['totals']['vat'];
    $cat = $d['expense_category'] ?: 'Other';
    $byCat[$cat] = ($byCat[$cat] ?? 0) + $d['totals']['total'];
}
arsort($byCat);

$cashIn = 0;
foreach ($receipts as $d) {
    $cashIn += $d['allocated_amount'] ?: $d['totals']['total'];
}

layout_start('Reports', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Reports</h1>
    <p class="lede">A short set: profit and loss, VAT, debtors, expenses, cash. Not forty reports.</p>
  </div>
</div>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income (invoiced, net)</span><strong><?= h(ugx($income)) ?></strong></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenses (net)</span><strong><?= h(ugx($costs)) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Profit</span><strong><?= h(ugx($income - $costs)) ?></strong></div>
  <div class="card stat"><?= icon('hash', 20) ?><span>VAT due (output − input)</span><strong><?= h(ugx($outputVat - $inputVat)) ?></strong></div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Debtors aging</h2></div>
  <?php if (!$debtors): ?>
    <p class="empty">No open invoices.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Invoice</th><th>Client</th><th>Due</th><th>Bucket</th><th class="right">Balance</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($debtors as $d): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $d['id'])) ?>"><?= h($d['number']) ?></a></td>
            <td><a href="<?= h(url('client_view.php?id=' . $d['party_id'])) ?>"><?= h($d['party_name']) ?></a></td>
            <td><?= h(format_date($d['due_date'])) ?></td>
            <td><span class="pill"><?= h($d['bucket']) ?></span></td>
            <td class="right mono"><?= h(ugx($d['balance'])) ?></td>
            <td class="row-actions"><?php render_doc_actions($d); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses by category</h2></div>
  <?php if (!$byCat): ?>
    <p class="empty">No expenses recorded.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Category</th><th class="right">Total</th></tr></thead>
      <tbody>
        <?php foreach ($byCat as $cat => $amt): ?>
          <tr><td><?= h($cat) ?></td><td class="right mono"><?= h(ugx($amt)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('receipt', 16) ?>Cash book (receipts)</h2></div>
  <p class="empty" style="margin-bottom:0">Money in <?= h(ugx($cashIn)) ?> from <?= count($receipts) ?> receipts. Open a receipt to email or print it.</p>
  <?php if ($receipts): ?>
    <table class="grid">
      <thead><tr><th>Receipt</th><th>Client</th><th>Date</th><th class="right">Amount</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($receipts as $d): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $d['id'])) ?>"><?= h($d['number']) ?></a></td>
            <td><?= h($d['party_name']) ?></td>
            <td><?= h(format_date($d['date'])) ?></td>
            <td class="right mono"><?= h(ugx($d['allocated_amount'] ?: $d['totals']['total'])) ?></td>
            <td class="row-actions"><?php render_doc_actions($d); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
