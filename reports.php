<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_desk_admin();

$view = (($_GET['view'] ?? '') === 'annual') ? 'annual' : 'period';
$year = (int) ($_GET['year'] ?? date('Y'));
$year = max(2000, min((int) date('Y'), $year));
if ($view === 'annual') {
    $_GET['from'] = sprintf('%04d-01-01', $year);
    $_GET['to'] = $year === (int) date('Y') ? today() : sprintf('%04d-12-31', $year);
    unset($_GET['range']);
}

$cid = current_company_id();
[$extra, $types, $params] = period_sql('d.date');
$scope = 'd.company_id = ? AND d.status = \'issued\'' . $extra;
$bind = 'i' . $types;
$args = array_merge([$cid], $params);

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'invoice'", $bind, $args));
$expenses = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'expense'", $bind, $args));
$receipts = attach_document_totals(db_all(
    "SELECT d.*, p.name AS party_name, r.kind AS related_kind
     FROM documents d LEFT JOIN parties p ON p.id = d.party_id
     LEFT JOIN documents r ON r.id = d.related_id
     WHERE {$scope} AND d.kind = 'receipt'",
    $bind,
    $args
));

$income = 0;
$debtors = [];
$aging = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$base = default_currency();
foreach ($invoices as $d) {
    $income += convert_money($d['totals']['net'], doc_currency($d), $base);
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
        $aging[$bucket] += convert_money($d['balance'], doc_currency($d), $base);
        $debtors[] = $d + ['bucket' => $bucket, 'age' => max(0, $age)];
    }
}
foreach ($receipts as $d) {
    if (!receipt_is_sale($d)) {
        continue;
    }
    $due = document_due_amount($d);
    if ($due <= 0.009) {
        continue;
    }
    $age = $d['date'] ? (int) floor((time() - strtotime((string) $d['date'])) / 86400) : 0;
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
    $aging[$bucket] += convert_money($due, doc_currency($d), $base);
    $debtors[] = $d + ['bucket' => $bucket, 'age' => max(0, $age), 'balance' => $due];
}

$costs = 0;
$byCat = [];
foreach ($expenses as $d) {
    if (function_exists('stock_is_stock_expense') && stock_is_stock_expense($d)) {
        continue; // Stock purchases sit on creditors / inventory - not operating spend
    }
    $costs += convert_money($d['totals']['net'], doc_currency($d), $base);
    $cat = $d['expense_category'] ?: 'Other';
    $byCat[$cat] = ($byCat[$cat] ?? 0) + convert_money($d['totals']['total'], doc_currency($d), $base);
}
arsort($byCat);

$cashIn = 0;
$cashOut = 0;
foreach ($receipts as $d) {
    $amt = convert_money((float) ($d['allocated_amount'] ?: $d['totals']['total']), doc_currency($d), $base);
    if (($d['related_kind'] ?? '') === 'expense') {
        $cashOut += $amt;
    } else {
        $cashIn += $amt;
    }
}

$outstanding = 0;
foreach ($debtors as $d) {
    $outstanding += convert_money((float) $d['balance'], doc_currency($d), $base);
}

$byClient = [];
foreach ($invoices as $d) {
    $name = trim((string) ($d['party_name'] ?? 'Client')) ?: 'Client';
    $byClient[$name] = ($byClient[$name] ?? 0) + convert_money($d['totals']['total'], doc_currency($d), $base);
}
arsort($byClient);
$topClients = array_slice($byClient, 0, 8, true);

$quotes = db_all(
    "SELECT d.*, p.name AS party_name FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE {$scope} AND d.kind = 'quotation'",
    $bind,
    $args
);
$quoteIds = array_map(static fn ($q) => (int) $q['id'], $quotes);
$convertedIds = [];
if ($quoteIds) {
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    $found = db_all(
        "SELECT related_id FROM documents WHERE company_id = ? AND kind = 'invoice' AND status = 'issued' AND related_id IN ($ph)",
        'i' . str_repeat('i', count($quoteIds)),
        array_merge([$cid], $quoteIds)
    );
    foreach ($found as $row) {
        $convertedIds[(int) $row['related_id']] = true;
    }
}
$quoteConverted = 0;
$quoteOpen = 0;
foreach ($quotes as $q) {
    if (isset($convertedIds[(int) $q['id']])) {
        $quoteConverted++;
    } else {
        $quoteOpen++;
    }
}

$mixRows = db_all("SELECT d.kind, d.date FROM documents d WHERE {$scope}", $bind, $args);
$mixSeries = [];
foreach ($mixRows as $row) {
    $key = substr((string) $row['date'], 0, 10);
    if (!isset($mixSeries[$key])) {
        $mixSeries[$key] = ['quotation' => 0, 'invoice' => 0, 'receipt' => 0, 'expense' => 0, 'letter' => 0];
    }
    $kindKey = (string) ($row['kind'] ?? '');
    if (isset($mixSeries[$key][$kindKey])) {
        $mixSeries[$key][$kindKey]++;
    }
}
ksort($mixSeries);
if (count($mixSeries) > 45) {
    $monthlyMix = [];
    foreach ($mixSeries as $day => $vals) {
        $m = substr($day, 0, 7);
        if (!isset($monthlyMix[$m])) {
            $monthlyMix[$m] = ['quotation' => 0, 'invoice' => 0, 'receipt' => 0, 'expense' => 0, 'letter' => 0];
        }
        foreach ($vals as $k => $v) {
            $monthlyMix[$m][$k] += $v;
        }
    }
    $mixSeries = $monthlyMix;
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
    $series[$key]['invoiced'] += convert_money($d['totals']['total'], doc_currency($d), $base);
}
foreach ($expenses as $d) {
    if (function_exists('stock_is_stock_expense') && stock_is_stock_expense($d)) {
        continue;
    }
    $key = substr((string) $d['date'], 0, 10);
    if (!isset($series[$key])) {
        $series[$key] = ['invoiced' => 0, 'expenses' => 0, 'cash' => 0];
    }
    $series[$key]['expenses'] += convert_money($d['totals']['total'], doc_currency($d), $base);
}
foreach ($receipts as $d) {
    if (($d['related_kind'] ?? '') === 'expense') {
        continue;
    }
    $key = substr((string) $d['date'], 0, 10);
    $series[$key]['cash'] += convert_money((float) ($d['allocated_amount'] ?: $d['totals']['total']), doc_currency($d), $base);
}
$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : '1970-01-01';
$to = $period['to'] !== '' ? $period['to'] : today();
$marginBy = function_exists('report_collection_margin') ? report_collection_margin($from, $to) : ['days' => []];
$marginDays = $marginBy['days'] ?? [];
foreach ($series as $key => $vals) {
    $m = $marginDays[$key] ?? null;
    $profit = $m ? round((float) $m['profit'], 2) : 0.0;
    $series[$key]['profit'] = $profit;
    $series[$key]['net'] = round($profit - (float) ($vals['expenses'] ?? 0), 2);
}
ksort($series);
if ($view === 'annual') {
    $filled = [];
    $mixFilled = [];
    for ($m = 1; $m <= 12; $m++) {
        $key = sprintf('%04d-%02d', $year, $m);
        $filled[$key] = ['invoiced' => 0, 'expenses' => 0, 'cash' => 0, 'profit' => 0, 'net' => 0];
        $mixFilled[$key] = ['quotation' => 0, 'invoice' => 0, 'receipt' => 0, 'expense' => 0, 'letter' => 0];
    }
    foreach ($series as $day => $vals) {
        $k = substr((string) $day, 0, 7);
        if (isset($filled[$k])) {
            $filled[$k]['invoiced'] += $vals['invoiced'];
            $filled[$k]['expenses'] += $vals['expenses'];
            $filled[$k]['cash'] += $vals['cash'];
            $filled[$k]['profit'] += (float) ($vals['profit'] ?? 0);
            $filled[$k]['net'] += (float) ($vals['net'] ?? 0);
        }
    }
    foreach ($mixSeries as $day => $vals) {
        $k = substr((string) $day, 0, 7);
        if (isset($mixFilled[$k])) {
            foreach ($vals as $kindKey => $n) {
                $mixFilled[$k][$kindKey] = ($mixFilled[$k][$kindKey] ?? 0) + $n;
            }
        }
    }
    $series = $filled;
    $mixSeries = $mixFilled;
} elseif (count($series) > 45) {
    $monthly = [];
    foreach ($series as $day => $vals) {
        $m = substr($day, 0, 7);
        if (!isset($monthly[$m])) {
            $monthly[$m] = ['invoiced' => 0, 'expenses' => 0, 'cash' => 0, 'profit' => 0, 'net' => 0];
        }
        $monthly[$m]['invoiced'] += $vals['invoiced'];
        $monthly[$m]['expenses'] += $vals['expenses'];
        $monthly[$m]['cash'] += $vals['cash'];
        $monthly[$m]['profit'] += (float) ($vals['profit'] ?? 0);
        $monthly[$m]['net'] += (float) ($vals['net'] ?? 0);
    }
    $series = $monthly;
}

$margins = function_exists('report_collection_margin') ? report_collection_margin($from, $to) : ['profit' => 0.0, 'cogs' => 0.0, 'collected' => 0.0];
$grossProfit = (float) ($margins['profit'] ?? 0);
$netProfit = round($grossProfit - $costs, 2);
try {
    $performance = report_performance_statement();
} catch (Throwable $e) {
    error_log('reports performance: ' . $e->getMessage());
    $performance = ['income' => [], 'expenses' => [], 'income_total' => 0.0, 'expense_total' => 0.0, 'cogs' => 0.0, 'profit' => 0.0, 'net' => 0.0];
}
$taxReport = report_tax_payable();
$taxName = company_tax_name();
$chartLabels = [];
foreach (array_keys($series) as $key) {
    $key = (string) $key;
    $chartLabels[] = (strlen($key) === 7 && strtotime($key . '-01'))
        ? date('M Y', strtotime($key . '-01'))
        : $key;
}
$chartInvoiced = array_column($series, 'invoiced');
$chartExpenses = array_column($series, 'expenses');
$chartCash = array_column($series, 'cash');
$chartProfit = array_column($series, 'profit');
$chartNet = array_column($series, 'net');
$yearStart = (int) date('Y');
$firstDoc = db_one('SELECT MIN(date) AS d FROM documents WHERE company_id = ?', 'i', [$cid]);
if (!empty($firstDoc['d'])) {
    $yearStart = min($yearStart, (int) substr((string) $firstDoc['d'], 0, 4));
}
$yearStart = max(2018, $yearStart);
$pieLabels = array_keys($byCat);
$pieValues = array_values($byCat);
$barLabels = array_keys($aging);
$barValues = array_values($aging);
$color = brand_color();

layout_start('Reports', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?><?= $view === 'annual' ? 'Annual reports' : 'Reports' ?></h1>
    <p class="lede"><?= $view === 'annual'
        ? 'Yearly performance for ' . (int) $year . ': income, collections, expenses and profit by month. Mixed currencies convert at ' . h(fx_rate_label()) . '.'
        : 'Time series, collections, clients, quotes, aging and ' . h($taxName) . ' payable - for the dates you pick. Mixed currencies convert at ' . h(fx_rate_label()) . '.' ?></p>
  </div>
  <a class="btn ghost" href="<?= h(export_query('reports', $view === 'annual' ? ['view' => 'annual', 'year' => $year] : [])) ?>"><?= icon('download', 16) ?>Export CSV</a>
</div>

<nav class="planner-tabs" aria-label="Report sections">
  <a class="planner-tab<?= $view === 'period' ? ' is-on' : '' ?>" href="<?= h(url('reports.php')) ?>"><?= icon('calendar', 16) ?><span>Period</span></a>
  <a class="planner-tab<?= $view === 'annual' ? ' is-on' : '' ?>" href="<?= h(url('reports.php?view=annual&year=' . (int) $year)) ?>"><?= icon('reports', 16) ?><span>Annual</span></a>
</nav>

<?php if ($view === 'annual'): ?>
  <div class="filters">
    <div class="filter-chips">
      <?php for ($y = (int) date('Y'); $y >= $yearStart; $y--): ?>
        <a class="chip<?= $year === $y ? ' is-on' : '' ?>" href="<?= h(url('reports.php?view=annual&year=' . $y)) ?>"><?= (int) $y ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <p class="hint" style="margin:-8px 0 16px">
    Showing <?= h(format_date($period['from'])) ?> - <?= h(format_date($period['to'])) ?>.
  </p>
<?php else: ?>
  <?php render_filters('reports.php'); ?>
  <p class="hint" style="margin:-8px 0 16px">
    Showing <?= $period['from'] ? h(format_date($period['from']) . ' - ' . format_date($period['to'])) : 'all dates' ?>.
  </p>
<?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income (invoiced, net)</span><strong><?= h(ugx($income)) ?></strong></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Collected</span><strong><?= h(ugx($cashIn)) ?></strong></div>
  <div class="card stat"><?= icon('clients', 20) ?><span>Outstanding</span><strong><?= h(ugx($outstanding)) ?></strong></div>
  <div class="card stat"><?= icon('package', 20) ?><span>Profit</span><strong><?= h(ugx($grossProfit)) ?></strong><em>On collections</em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Net profit</span><strong><?= h(ugx($netProfit)) ?></strong><em>Profit minus expenses</em></div>
</div>
<div class="stats">
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenses (net)</span><strong><?= h(ugx($costs)) ?></strong></div>
  <div class="card stat"><?= icon('hash', 20) ?><span><?= h($taxName) ?> payable</span><strong><?= h(ugx($taxReport['payable'])) ?></strong></div>
  <div class="card stat"><?= icon('quotation', 20) ?><span>Quotes converted</span><strong><?= (int) $quoteConverted ?> / <?= count($quotes) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Supplier payments</span><strong><?= h(ugx($cashOut)) ?></strong></div>
</div>

<?php
$perfTitle = ($period['preset'] ?? '') === 'today' ? "Today's performance" : "This period's performance";
$kindLabel = static fn (string $k): string => match ($k) {
    'product' => 'Product',
    'service' => 'Service',
    'expense' => 'Expense',
    default => 'Other',
};
?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('reports', 16) ?><?= h($perfTitle) ?></h2></div>
  <p class="hint" style="margin:0 22px 12px">Amount collected in this period. Products take selling price minus buying price on the share that was paid. Services use the amount received - no buying price. Net profit is that profit minus expenses (stock purchases are excluded).</p>
  <?php if (!$performance['income'] && !$performance['expenses']): ?>
    <p class="empty">Nothing sold or spent in this period.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Source</th>
          <th>Kind</th>
          <th class="right">Qty</th>
          <th class="right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="4"><strong>Income</strong></td>
        </tr>
        <?php if (!$performance['income']): ?>
          <tr><td colspan="4" class="muted">No products or services sold.</td></tr>
        <?php else: ?>
          <?php foreach ($performance['income'] as $row): ?>
            <tr>
              <td><?= h($row['name']) ?></td>
              <td><?= h($kindLabel((string) ($row['kind'] ?? 'other'))) ?></td>
              <td class="right mono"><?= h(format_qty($row['qty'])) ?></td>
              <td class="right mono"><?= h(ugx($row['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr>
          <td colspan="3"><strong>Income total</strong></td>
          <td class="right mono"><strong><?= h(ugx($performance['income_total'])) ?></strong></td>
        </tr>
        <tr>
          <td colspan="3">Cost of goods (buy)</td>
          <td class="right mono"><?= h(ugx((float) ($performance['cogs'] ?? 0))) ?></td>
        </tr>
        <tr>
          <td colspan="3"><strong>Profit</strong></td>
          <td class="right mono"><strong><?= h(ugx((float) ($performance['profit'] ?? 0))) ?></strong></td>
        </tr>
        <tr>
          <td colspan="4"><strong>Expenses</strong></td>
        </tr>
        <?php if (!$performance['expenses']): ?>
          <tr><td colspan="4" class="muted">No expenses.</td></tr>
        <?php else: ?>
          <?php foreach ($performance['expenses'] as $row): ?>
            <tr>
              <td><?= h($row['name']) ?></td>
              <td>Expense</td>
              <td class="right mono"><?= h(format_qty($row['qty'])) ?></td>
              <td class="right mono"><?= h(ugx($row['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr>
          <td colspan="3"><strong>Expenses total</strong></td>
          <td class="right mono"><strong><?= h(ugx($performance['expense_total'])) ?></strong></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3">Net profit</td>
          <td class="right mono"><?= h(ugx($performance['net'])) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($view === 'annual'): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Yearly performance</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Month</th>
        <th class="right">Invoiced</th>
        <th class="right">Expenses</th>
        <th class="right">Cash in</th>
        <th class="right">Profit</th>
        <th class="right">Net profit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($series as $ym => $vals):
          $stamp = strtotime((string) $ym . '-01');
          ?>
        <tr>
          <td><?= h($stamp ? date('F Y', $stamp) : (string) $ym) ?></td>
          <td class="right mono"><?= h(ugx($vals['invoiced'])) ?></td>
          <td class="right mono"><?= h(ugx($vals['expenses'])) ?></td>
          <td class="right mono"><?= h(ugx($vals['cash'])) ?></td>
          <td class="right mono"><?= h(ugx($vals['profit'] ?? 0)) ?></td>
          <td class="right mono"><?= h(ugx($vals['net'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td>Year</td>
        <td class="right mono"><?= h(ugx(array_sum(array_column($series, 'invoiced')))) ?></td>
        <td class="right mono"><?= h(ugx(array_sum(array_column($series, 'expenses')))) ?></td>
        <td class="right mono"><?= h(ugx(array_sum(array_column($series, 'cash')))) ?></td>
        <td class="right mono"><?= h(ugx($grossProfit)) ?></td>
        <td class="right mono"><?= h(ugx($netProfit)) ?></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="chart-grid">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('reports', 16) ?><?= $view === 'annual' ? 'Months in ' . (int) $year : 'Activity over time' ?></h2></div>
    <?php if (!$series): ?>
      <p class="empty">Nothing in this period to plot.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-series"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('expense', 16) ?>Expenses by category</h2></div>
    <?php if (!$byCat): ?>
      <p class="empty">No expenses recorded.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-pie"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="card chart-box" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Debtors aging</h2></div>
  <div class="chart-frame"><canvas id="chart-bar"></canvas></div>
</div>

<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('receipt', 16) ?>Collections vs outstanding</h2></div>
    <?php if ($cashIn <= 0 && $outstanding <= 0): ?>
      <p class="empty">No collections or open balances in this period.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-collect"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('quotation', 16) ?>Quote conversion</h2></div>
    <?php if (!$quotes): ?>
      <p class="empty">No quotations in this period.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-quotes"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('clients', 16) ?>Top clients billed</h2></div>
    <?php if (!$topClients): ?>
      <p class="empty">No invoices in this period.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-clients"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Documents issued</h2></div>
    <?php if (!$mixSeries): ?>
      <p class="empty">Nothing issued in this period.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="chart-mix"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($topClients): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Top clients</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead><tr><th>Client</th><th class="right">Billed</th></tr></thead>
    <tbody>
      <?php foreach ($topClients as $name => $amt): ?>
        <tr>
          <td><?= h($name) ?></td>
          <td class="right mono"><?= h(ugx($amt)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Open debtors</h2></div>
  <?php if (!$debtors): ?>
    <p class="empty">No open invoices in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
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
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('hash', 16) ?><?= h($taxName) ?> payable</h2></div>
  <p class="hint" style="margin:0 22px 12px">Tax on issued sales, less tax on expenses. Receipt is the collection against that sheet.</p>
  <?php if (!$taxReport['lines']): ?>
    <p class="empty">No taxed lines in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Date</th>
          <th>Sheet</th>
          <th>Receipt</th>
          <th>Client</th>
          <th>Item</th>
          <th class="right">Taxable</th>
          <th class="right"><?= h($taxName) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($taxReport['lines'] as $line):
            $kindLabel = match ($line['kind']) {
                'expense' => 'Expense',
                'receipt' => 'Receipt',
                default => 'Invoice',
            };
            $receipts = $line['receipts'];
            ?>
          <tr>
            <td><?= h(format_date($line['date'])) ?></td>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $line['id'])) ?>"><?= h($line['number']) ?></a><span class="hint"> <?= h($kindLabel) ?></span></td>
            <td class="mono">
              <?php if (!$receipts): ?>
                <span class="hint">Not collected</span>
              <?php else: ?>
                <?php foreach ($receipts as $i => $rcpt): ?>
                  <?= $i > 0 ? ', ' : '' ?><a href="<?= h(url('document_view.php?id=' . $rcpt['id'])) ?>"><?= h($rcpt['number']) ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($line['party_id'] > 0): ?>
                <a href="<?= h(url('client_view.php?id=' . $line['party_id'])) ?>"><?= h($line['party_name']) ?></a>
              <?php else: ?>
                <?= h($line['party_name']) ?>
              <?php endif; ?>
            </td>
            <td><?= h($line['item']) ?></td>
            <td class="right mono"><?= h(ugx($line['taxable'])) ?></td>
            <td class="right mono"><?= $line['side'] === 'input' ? '(' . h(ugx($line['tax'])) . ')' : h(ugx($line['tax'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6">Output <?= h($taxName) ?></td>
          <td class="right mono"><?= h(ugx($taxReport['output'])) ?></td>
        </tr>
        <tr>
          <td colspan="6">Input <?= h($taxName) ?> on expenses</td>
          <td class="right mono"><?= h(ugx($taxReport['input'])) ?></td>
        </tr>
        <tr>
          <td colspan="6"><strong><?= h($taxName) ?> payable</strong></td>
          <td class="right mono"><strong><?= h(ugx($taxReport['payable'])) ?></strong></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= icon('receipt', 16) ?>Cash movement</h2></div>
  <p class="empty" style="margin-bottom:0">Money in <?= h(ugx($cashIn)) ?> · Supplier payments <?= h(ugx($cashOut)) ?>.</p>
  <?php if ($receipts): ?>
    <div class="table-scroll">
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
    </div>
  <?php endif; ?>
</div>
<?php
$payload = json_encode([
    'labels' => $chartLabels,
    'invoiced' => $chartInvoiced,
    'expenses' => $chartExpenses,
    'cash' => $chartCash,
    'profit' => $chartProfit,
    'net' => $chartNet,
    'pieLabels' => $pieLabels,
    'pieValues' => $pieValues,
    'barLabels' => $barLabels,
    'barValues' => $barValues,
    'collectLabels' => ['Collected', 'Outstanding'],
    'collectValues' => [$cashIn, $outstanding],
    'quoteLabels' => ['Converted', 'Still open'],
    'quoteValues' => [$quoteConverted, $quoteOpen],
    'clientLabels' => array_keys($topClients),
    'clientValues' => array_values($topClients),
    'mixLabels' => array_keys($mixSeries),
    'mixQuotes' => array_column($mixSeries, 'quotation'),
    'mixInvoices' => array_column($mixSeries, 'invoice'),
    'mixReceipts' => array_column($mixSeries, 'receipt'),
    'mixExpenses' => array_column($mixSeries, 'expense'),
    'mixLetters' => array_column($mixSeries, 'letter'),
    'color' => $color,
    'currency' => default_currency(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($payload) || $payload === '') {
    $payload = '{}';
}
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  function start(){
  var d = ' . $payload . ';
  if (!d || typeof d !== "object" || !window.Chart) return;
  var brand = d.color || "#82B440";
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  function money(v){
    var n = Number(v);
    if (!isFinite(n)) return "";
    var cur = d.currency || "USD";
    var sign = n < 0 ? "-" : "";
    var a = Math.abs(n);
    var unit = "";
    var x = a;
    if (a >= 1e12) { x = a / 1e12; unit = "T"; }
    else if (a >= 1e9) { x = a / 1e9; unit = "B"; }
    else if (a >= 1e6) { x = a / 1e6; unit = "M"; }
    var num = unit ? (x >= 100 ? String(Math.round(x)) : (Math.round(x * 10) / 10).toFixed(1).replace(/\\.0$/, "")) : Math.round(a).toLocaleString("en-US");
    return cur + " " + sign + num + unit;
  }
  var palette = ["#82B440","#1f3a12","#c4a35a","#4a6fa5","#b42318","#6b7c5e","#8d6e63","#546e7a"];
  function safeLabels(arr){ return (arr && arr.length) ? arr : ["-"]; }
  function safeData(arr, n){
    if (arr && arr.length) return arr;
    var z = []; for (var i = 0; i < (n || 1); i++) z.push(0); return z;
  }
  var line = document.getElementById("chart-series");
  if (line) {
    var labs = safeLabels(d.labels);
    new Chart(line, {
      type: "line",
      data: {
        labels: labs,
        datasets: [
          { label: "Invoiced", data: safeData(d.invoiced, labs.length), borderColor: brand, backgroundColor: brand + "33", tension: .25, fill: true },
          { label: "Expenses", data: safeData(d.expenses, labs.length), borderColor: "#b42318", backgroundColor: "rgba(180,35,24,.12)", tension: .25, fill: true },
          { label: "Cash in", data: safeData(d.cash, labs.length), borderColor: "#1f3a12", backgroundColor: "rgba(31,58,18,.08)", tension: .25, fill: false },
          { label: "Profit", data: safeData(d.profit, labs.length), borderColor: "#4a6fa5", backgroundColor: "rgba(74,111,165,.10)", tension: .25, fill: false },
          { label: "Net profit", data: safeData(d.net, labs.length), borderColor: "#1E4EFF", backgroundColor: "transparent", tension: .25, fill: false }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { beginAtZero: true, ticks: { callback: money } } } }
    });
  }
  var pie = document.getElementById("chart-pie");
  if (pie) {
    new Chart(pie, {
      type: "pie",
      data: { labels: safeLabels(d.pieLabels), datasets: [{ data: safeData(d.pieValues), backgroundColor: palette }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var bar = document.getElementById("chart-bar");
  if (bar) {
    new Chart(bar, {
      type: "bar",
      data: { labels: safeLabels(d.barLabels), datasets: [{ label: "Balance", data: safeData(d.barValues), backgroundColor: brand }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: money } } } }
    });
  }
  var collect = document.getElementById("chart-collect");
  if (collect) {
    new Chart(collect, {
      type: "doughnut",
      data: { labels: d.collectLabels || ["Collected", "Outstanding"], datasets: [{ data: safeData(d.collectValues, 2), backgroundColor: [brand, "#b42318"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var quotes = document.getElementById("chart-quotes");
  if (quotes) {
    new Chart(quotes, {
      type: "pie",
      data: { labels: d.quoteLabels || ["Converted", "Still open"], datasets: [{ data: safeData(d.quoteValues, 2), backgroundColor: [brand, "#c4a35a"] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var clients = document.getElementById("chart-clients");
  if (clients) {
    new Chart(clients, {
      type: "bar",
      data: { labels: safeLabels(d.clientLabels), datasets: [{ label: "Billed", data: safeData(d.clientValues), backgroundColor: brand }] },
      options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: money } } } }
    });
  }
  var mix = document.getElementById("chart-mix");
  if (mix) {
    var mixLabs = safeLabels(d.mixLabels);
    new Chart(mix, {
      type: "bar",
      data: {
        labels: mixLabs,
        datasets: [
          { label: "Quotations", data: safeData(d.mixQuotes, mixLabs.length), backgroundColor: "#4a6fa5" },
          { label: "Invoices", data: safeData(d.mixInvoices, mixLabs.length), backgroundColor: brand },
          { label: "Receipts", data: safeData(d.mixReceipts, mixLabs.length), backgroundColor: "#1f3a12" },
          { label: "Expenses", data: safeData(d.mixExpenses, mixLabs.length), backgroundColor: "#b42318" },
          { label: "Letters", data: safeData(d.mixLetters, mixLabs.length), backgroundColor: "#c4a35a" }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
</script>';
layout_end($script);
?>
