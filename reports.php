<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$cid = current_company_id();
[$extra, $types, $params] = period_sql('d.date');
$scope = 'd.company_id = ? AND d.status = \'issued\'' . $extra;
$bind = 'i' . $types;
$args = array_merge([$cid], $params);

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'invoice'", $bind, $args));
$expenses = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'expense'", $bind, $args));
$receipts = attach_document_totals(db_all(
    "SELECT d.*, p.name AS party_name, r.kind AS related_kind
     FROM documents d JOIN parties p ON p.id = d.party_id
     LEFT JOIN documents r ON r.id = d.related_id
     WHERE {$scope} AND d.kind = 'receipt'",
    $bind,
    $args
));

$income = 0;
$outputVat = 0;
$debtors = [];
$aging = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
foreach ($invoices as $d) {
    $income += $d['totals']['net'];
    $outputVat += $d['totals']['vat'];
    if ($d['balance'] > 0) {
        $age = $d['due_date'] ? (int) floor((time() - strtotime($d['due_date'])) / 86400) : 0;
        $bucket = 'Current';
        if ($age > 90) {
            $bucket = '90+';
        } elseif ($age > 60) {
            $bucket = '61-90';
        } elseif ($age > 30) {
            $bucket = '31-60';
        } elseif ($age > 0) {
            $bucket = '1-30';
        }
        $aging[$bucket] += $d['balance'];
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
$cashOut = 0;
foreach ($receipts as $d) {
    $amt = (float) ($d['allocated_amount'] ?: $d['totals']['total']);
    if (($d['related_kind'] ?? '') === 'expense') {
        $cashOut += $amt;
    } else {
        $cashIn += $amt;
    }
}

$series = [];
foreach (array_merge($invoices, $expenses, $receipts) as $d) {
    $key = substr((string) $d['date'], 0, 10);
    if (!isset($series[$key])) {
        $series[$key] = ['invoiced' => 0, 'expenses' => 0, 'cash' => 0];
    }
}
foreach ($invoices as $d) {
    $key = substr((string) $d['date'], 0, 10);
    $series[$key]['invoiced'] += $d['totals']['total'];
}
foreach ($expenses as $d) {
    $key = substr((string) $d['date'], 0, 10);
    $series[$key]['expenses'] += $d['totals']['total'];
}
foreach ($receipts as $d) {
    if (($d['related_kind'] ?? '') === 'expense') {
        continue;
    }
    $key = substr((string) $d['date'], 0, 10);
    $series[$key]['cash'] += (int) ($d['allocated_amount'] ?: $d['totals']['total']);
}
ksort($series);
if (count($series) > 45) {
    $monthly = [];
    foreach ($series as $day => $vals) {
        $m = substr($day, 0, 7);
        if (!isset($monthly[$m])) {
            $monthly[$m] = ['invoiced' => 0, 'expenses' => 0, 'cash' => 0];
        }
        $monthly[$m]['invoiced'] += $vals['invoiced'];
        $monthly[$m]['expenses'] += $vals['expenses'];
        $monthly[$m]['cash'] += $vals['cash'];
    }
    $series = $monthly;
}

$period = period_range();
$chartLabels = array_keys($series);
$chartInvoiced = array_column($series, 'invoiced');
$chartExpenses = array_column($series, 'expenses');
$chartCash = array_column($series, 'cash');
$pieLabels = array_keys($byCat);
$pieValues = array_values($byCat);
$barLabels = array_keys($aging);
$barValues = array_values($aging);
$color = brand_color();

layout_start('Reports', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Reports</h1>
    <p class="lede">Time series, mix of spend, and aging - for the dates you pick.</p>
  </div>
  <a class="btn ghost" href="<?= h(export_query('reports')) ?>"><?= icon('download', 16) ?>Export CSV</a>
</div>

<?php render_filters('reports.php'); ?>
<p class="hint" style="margin:-8px 0 16px">
  Showing <?= $period['from'] ? h(format_date($period['from']) . ' - ' . format_date($period['to'])) : 'all dates' ?>.
</p>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income (invoiced, net)</span><strong><?= h(ugx($income)) ?></strong></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenses (net)</span><strong><?= h(ugx($costs)) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Profit</span><strong><?= h(ugx($income - $costs)) ?></strong></div>
  <div class="card stat"><?= icon('hash', 20) ?><span>VAT due (output - input)</span><strong><?= h(ugx($outputVat - $inputVat)) ?></strong></div>
</div>

<div class="chart-grid">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Activity over time</h2></div>
    <?php if (!$series): ?>
      <p class="empty">Nothing in this period to plot.</p>
    <?php else: ?>
      <canvas id="chart-series"></canvas>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses by category</h2></div>
    <?php if (!$byCat): ?>
      <p class="empty">No expenses recorded.</p>
    <?php else: ?>
      <canvas id="chart-pie"></canvas>
    <?php endif; ?>
  </div>
</div>

<div class="card chart-box" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Debtors aging</h2></div>
  <canvas id="chart-bar" height="90"></canvas>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Open debtors</h2></div>
  <?php if (!$debtors): ?>
    <p class="empty">No open invoices in this period.</p>
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
      <tfoot>
        <tr>
          <td colspan="4">Totals</td>
          <td class="right mono"><?= h(ugx(array_sum(array_column($debtors, 'balance')))) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('receipt', 16) ?>Cash movement</h2></div>
  <p class="empty" style="margin-bottom:0">Money in <?= h(ugx($cashIn)) ?> · Supplier payments <?= h(ugx($cashOut)) ?>.</p>
  <?php if ($receipts): ?>
    <table class="grid">
      <thead><tr><th>Number</th><th>Party</th><th>Date</th><th>Kind</th><th class="right">Amount</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($receipts as $d): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $d['id'])) ?>"><?= h($d['number']) ?></a></td>
            <td><?= h($d['party_name']) ?></td>
            <td><?= h(format_date($d['date'])) ?></td>
            <td><?= ($d['related_kind'] ?? '') === 'expense' ? 'Supplier payment' : 'Receipt' ?></td>
            <td class="right mono"><?= h(ugx($d['allocated_amount'] ?: $d['totals']['total'])) ?></td>
            <td class="row-actions"><?php render_doc_actions($d); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Money in / supplier payments</td>
          <td class="right mono"><?= h(ugx($cashIn)) ?> / <?= h(ugx($cashOut)) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>
</div>
<?php
$payload = json_encode([
    'labels' => $chartLabels,
    'invoiced' => $chartInvoiced,
    'expenses' => $chartExpenses,
    'cash' => $chartCash,
    'pieLabels' => $pieLabels,
    'pieValues' => $pieValues,
    'barLabels' => $barLabels,
    'barValues' => $barValues,
    'color' => $color,
    'currency' => default_currency(),
], JSON_UNESCAPED_UNICODE);
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d = ' . $payload . ';
  var brand = d.color || "#82B440";
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  function money(v){ return (d.currency || "UGX") + " " + Number(v).toLocaleString("en-UG"); }
  var line = document.getElementById("chart-series");
  if (line) {
    new Chart(line, {
      type: "line",
      data: {
        labels: d.labels,
        datasets: [
          { label: "Invoiced", data: d.invoiced, borderColor: brand, backgroundColor: brand + "33", tension: .25, fill: true },
          { label: "Expenses", data: d.expenses, borderColor: "#b42318", backgroundColor: "rgba(180,35,24,.12)", tension: .25, fill: true },
          { label: "Cash in", data: d.cash, borderColor: "#1f3a12", backgroundColor: "rgba(31,58,18,.08)", tension: .25, fill: false }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
  var pie = document.getElementById("chart-pie");
  if (pie) {
    new Chart(pie, {
      type: "pie",
      data: { labels: d.pieLabels, datasets: [{ data: d.pieValues, backgroundColor: ["#82B440","#1f3a12","#c4a35a","#4a6fa5","#b42318","#6b7c5e","#8d6e63","#546e7a"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var bar = document.getElementById("chart-bar");
  if (bar) {
    new Chart(bar, {
      type: "bar",
      data: { labels: d.barLabels, datasets: [{ label: "Balance", data: d.barValues, backgroundColor: brand }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
})();
</script>';
layout_end($script);
?>
