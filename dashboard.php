<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$brand = branding();
$homeCcy = default_currency();
$canQuote = user_can_kind('quotation');
$canInvoice = user_can_kind('invoice');
$deskCompany = current_company();
$stockOn = function_exists('company_stock_enabled') && company_stock_enabled();
$dayError = '';

if ($stockOn) {
    if (!isset($_GET['range']) && trim((string) ($_GET['from'] ?? '')) === '') {
        $_GET['range'] = 'today';
    }
    if (isset($_GET['ajax'])) {
        desk_day_json_exit();
    }
    layout_start('Desk', $user);
    ?>
    <?php if ($deskCompany): ?>
      <div class="desk-term-wrap">
        <?php render_top_term($deskCompany); ?>
      </div>
    <?php endif; ?>
    <?php
    render_desk_company_card($deskCompany, [
        'can_quote' => $canQuote,
        'can_invoice' => $canInvoice,
        'can_receipt' => user_can_kind('receipt'),
        'stock' => true,
        'reports' => is_desk_admin($user),
        'user_name' => (string) ($user['name'] ?? ''),
    ]);
    $extraJs = render_desk_day('');
    layout_end($extraJs);
    return;
}

if (!isset($_GET['range']) && trim((string) ($_GET['from'] ?? '')) === '') {
    $_GET['range'] = 'this_month';
}

$cid = current_company_id();
$base = $homeCcy;
$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : date('Y-m-01');
$to = $period['to'] !== '' ? $period['to'] : today();
[$prevFrom, $prevTo] = desk_period_shift($from, $to);
$since = date('Y-m-01', strtotime('-5 months'));

$invoices = attach_document_totals(db_all(
    "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
     WHERE d.company_id = ? AND d.kind = 'invoice' AND d.status = 'issued' ORDER BY d.due_date IS NULL, d.due_date, d.id DESC",
    'i',
    [$cid]
));
$open = array_values(array_filter($invoices, static fn ($d) => $d['balance'] > 0));
$saleOpen = array_values(array_filter(
    attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
         WHERE d.company_id = ? AND d.kind = 'receipt' AND d.status = 'issued' AND COALESCE(d.related_id, 0) = 0
         ORDER BY d.date DESC, d.id DESC",
        'i',
        [$cid]
    )),
    static fn ($d) => document_due_amount($d) > 0.009
));
$overdue = array_values(array_filter($open, static fn ($d) => !empty($d['due_date']) && $d['due_date'] < today()));

$sumInRange = static function (array $docs) use ($from, $to, $base): float {
    $n = 0.0;
    foreach ($docs as $d) {
        $date = (string) ($d['date'] ?? '');
        if ($date < $from || $date > $to) {
            continue;
        }
        $n += convert_money($d['totals']['total'], doc_currency($d), $base);
    }
    return $n;
};
$sumInWindow = static function (array $docs, string $a, string $b) use ($base): float {
    $n = 0.0;
    foreach ($docs as $d) {
        $date = (string) ($d['date'] ?? '');
        if ($date < $a || $date > $b) {
            continue;
        }
        $n += convert_money($d['totals']['total'], doc_currency($d), $base);
    }
    return $n;
};

$incomePeriod = $sumInRange($invoices);
$incomePrev = $sumInWindow($invoices, $prevFrom, $prevTo);

$expAll = attach_document_totals(db_all(
    "SELECT d.*, p.name AS party_name FROM documents d LEFT JOIN parties p ON p.id = d.party_id
     WHERE d.company_id = ? AND d.kind = 'expense' AND d.status = 'issued'",
    'i',
    [$cid]
));
$expensePeriod = $sumInRange($expAll);
$expensePrev = $sumInWindow($expAll, $prevFrom, $prevTo);
$creditorOpen = 0.0;
$byCat = [];
foreach ($expAll as $d) {
    $total = convert_money($d['totals']['total'], doc_currency($d), $base);
    if ((float) ($d['balance'] ?? 0) > 0.009) {
        $creditorOpen += convert_money((float) $d['balance'], doc_currency($d), $base);
    }
    if ($d['date'] >= $since) {
        $cat = trim((string) ($d['expense_category'] ?? '')) ?: 'Other';
        $byCat[$cat] = ($byCat[$cat] ?? 0) + $total;
    }
}
arsort($byCat);
$byCat = array_slice($byCat, 0, 6, true);

$receipts = attach_document_totals(db_all(
    "SELECT d.*, r.kind AS related_kind FROM documents d LEFT JOIN documents r ON r.id = d.related_id
     WHERE d.company_id = ? AND d.kind = 'receipt' AND d.status = 'issued' AND d.date >= ?",
    'is',
    [$cid, $since]
));
$cashPeriod = 0.0;
$cashPrev = 0.0;
$cashHalf = 0.0;
foreach ($receipts as $d) {
    if (($d['related_kind'] ?? '') === 'expense') {
        continue;
    }
    $amt = convert_money((float) ($d['allocated_amount'] ?: $d['totals']['total']), doc_currency($d), $base);
    $date = (string) ($d['date'] ?? '');
    $cashHalf += $amt;
    if ($date >= $from && $date <= $to) {
        $cashPeriod += $amt;
    }
    if ($date >= $prevFrom && $date <= $prevTo) {
        $cashPrev += $amt;
    }
}

$quotesOpen = (int) (db_one("SELECT COUNT(*) c FROM documents WHERE company_id = ? AND kind='quotation' AND status='issued'", 'i', [$cid])['c'] ?? 0);
$quotesAll = (int) (db_one("SELECT COUNT(*) c FROM documents WHERE company_id = ? AND kind='quotation' AND status='issued' AND date >= ?", 'is', [$cid, $since])['c'] ?? 0);
$quotesConverted = (int) (db_one(
    "SELECT COUNT(*) c FROM documents q INNER JOIN documents i ON i.related_id = q.id AND i.kind = 'invoice' AND i.status = 'issued' AND i.company_id = q.company_id WHERE q.company_id = ? AND q.kind = 'quotation' AND q.status = 'issued' AND q.date >= ?",
    'is',
    [$cid, $since]
)['c'] ?? 0);
$quoteRate = $quotesAll > 0 ? (int) round(100 * $quotesConverted / $quotesAll) : 0;

$invoiceCount = 0;
foreach ($invoices as $d) {
    $date = (string) ($d['date'] ?? '');
    if ($date >= $from && $date <= $to) {
        $invoiceCount++;
    }
}

$recent = attach_document_totals(db_all(
    "SELECT d.*, p.name AS party_name FROM documents d LEFT JOIN parties p ON p.id = d.party_id
     WHERE d.company_id = ? ORDER BY d.id DESC LIMIT 8",
    'i',
    [$cid]
));
$queue = $overdue ?: array_merge($open, $saleOpen);

$openAmt = documents_sum($open, 'balance') + array_sum(array_map(static fn ($d) => convert_money(document_due_amount($d), doc_currency($d), $base), $saleOpen));
$overdueAmt = documents_sum($overdue, 'balance');
$netPeriod = $incomePeriod - $expensePeriod;
$netPrev = $incomePrev - $expensePrev;
$collectRate = $incomePeriod > 0 ? (int) round(100 * min($cashPeriod, $incomePeriod) / $incomePeriod) : 0;

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('-' . $i . ' months'));
    $months[$key] = [
        'label' => date('M', strtotime($key . '-01')),
        'invoiced' => 0.0,
        'expenses' => 0.0,
        'cash' => 0.0,
    ];
}
foreach ($invoices as $d) {
    $key = substr((string) $d['date'], 0, 7);
    if (isset($months[$key])) {
        $months[$key]['invoiced'] += convert_money($d['totals']['total'], doc_currency($d), $base);
    }
}
foreach ($expAll as $d) {
    $key = substr((string) $d['date'], 0, 7);
    if (isset($months[$key])) {
        $months[$key]['expenses'] += convert_money($d['totals']['total'], doc_currency($d), $base);
    }
}
foreach ($receipts as $d) {
    if (($d['related_kind'] ?? '') === 'expense') {
        continue;
    }
    $key = substr((string) $d['date'], 0, 7);
    if (isset($months[$key])) {
        $months[$key]['cash'] += convert_money((float) ($d['allocated_amount'] ?: $d['totals']['total']), doc_currency($d), $base);
    }
}

$byClient = [];
foreach ($invoices as $d) {
    if ($d['date'] < $since) {
        continue;
    }
    $name = trim((string) ($d['party_name'] ?? 'Client')) ?: 'Client';
    $byClient[$name] = ($byClient[$name] ?? 0) + convert_money($d['totals']['total'], doc_currency($d), $base);
}
arsort($byClient);
$topClients = array_slice($byClient, 0, 5, true);

$color = parse_hex_color((string) ($brand['brand_color'] ?? ''), '#1E4EFF');
$incomeTrend = desk_pct_trend($incomePeriod, $incomePrev);
$expenseTrend = desk_pct_trend($expensePeriod, $expensePrev);
$netTrend = desk_pct_trend($netPeriod, $netPrev);
$cashTrend = desk_pct_trend($cashPeriod, $cashPrev);
$incomeSeries = array_values(array_column($months, 'invoiced'));
$expenseSeries = array_values(array_column($months, 'expenses'));
$cashSeries = array_values(array_column($months, 'cash'));
$netSeries = [];
foreach ($months as $row) {
    $netSeries[] = (float) $row['invoiced'] - (float) $row['expenses'];
}

$margins = function_exists('report_collection_margin')
    ? report_collection_margin($from, $to)
    : ['profit' => max(0, $cashPeriod - $expensePeriod), 'collected' => $cashPeriod];
$grossProfit = (float) ($margins['profit'] ?? 0);
$opExpense = 0.0;
foreach ($expAll as $d) {
    $date = (string) ($d['date'] ?? '');
    if ($date < $from || $date > $to) {
        continue;
    }
    if (function_exists('stock_is_stock_expense') && stock_is_stock_expense($d)) {
        continue;
    }
    $opExpense += convert_money($d['totals']['net'] ?? $d['totals']['total'], doc_currency($d), $base);
}
$tabNet = round($grossProfit - $opExpense, 2);
$docsIssued = desk_docs_issued_count($from, $to);
$activeClients = desk_active_clients_count();
$showProfit = !function_exists('user_can_see_profit') || user_can_see_profit();
$profitHref = (function_exists('user_can_open') && user_can_open('reports.php'))
    ? url('reports.php')
    : '#desk-charts';

layout_start('Desk', $user);
?>
<?php if ($deskCompany): ?>
  <div class="desk-term-wrap">
    <?php render_top_term($deskCompany); ?>
  </div>
<?php endif; ?>

<?php
render_desk_company_card($deskCompany, [
    'can_quote' => $canQuote,
    'can_invoice' => $canInvoice,
    'can_receipt' => user_can_kind('receipt'),
    'stock' => false,
    'reports' => is_desk_admin($user),
    'user_name' => (string) ($user['name'] ?? ''),
]);
render_filters('dashboard.php', [], ['no_all' => true]);
render_desk_metric_tabs([
    'income' => $cashPeriod,
    'expense' => $opExpense > 0.009 ? $opExpense : $expensePeriod,
    'profit' => $grossProfit,
    'net' => $tabNet,
    'debtors' => $openAmt,
    'docs' => $docsIssued,
    'clients' => $activeClients,
    'show_profit' => $showProfit,
    'income_href' => url('documents.php?kind=receipt'),
    'expense_href' => url('documents.php?kind=expense'),
    'profit_href' => $profitHref,
    'net_href' => $profitHref,
    'debtors_href' => url('debtors.php'),
    'docs_href' => url('documents.php'),
    'clients_href' => url('clients.php?status=active'),
    'income_trend' => $cashTrend,
    'expense_trend' => $expenseTrend,
    'profit_trend' => $netTrend,
    'net_trend' => $netTrend,
]);
?>

<div class="desk-bento" id="desk-charts">
  <article class="card desk-tile desk-tile-wide">
    <div class="card-head">
      <h2>Performance</h2>
      <?php if (is_desk_admin($user)): ?><a class="btn ghost sm" href="<?= h(url('reports.php')) ?>">Reports</a><?php endif; ?>
    </div>
    <div class="desk-chart"><canvas id="desk-chart-trend"></canvas></div>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Collections</h2></div>
    <div class="desk-chart desk-chart-sm"><canvas id="desk-chart-collect"></canvas></div>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Analysis</h2></div>
    <ul class="desk-metrics">
      <li><span>Quote conversion</span><b><?= $quoteRate ?>%</b></li>
      <li><span>Open quotations</span><b><?= $quotesOpen ?></b></li>
      <li><span>Open invoices</span><b><?= count($open) ?></b></li>
      <li><span>Overdue</span><b><?= count($overdue) ?></b></li>
      <li><span>Creditors</span><b><?= h(money($creditorOpen)) ?></b></li>
    </ul>
  </article>
  <article class="card desk-tile desk-tile-mid">
    <div class="card-head">
      <h2><?= $overdue ? 'Overdue' : 'Open invoices' ?></h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">All</a>
    </div>
    <?php if (!$queue): ?>
      <p class="empty">Nothing outstanding.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach (array_slice($queue, 0, 5) as $doc): ?>
          <a class="work-row" href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>">
            <div>
              <strong><?= h($doc['party_name']) ?></strong>
              <span><?= h($doc['number']) ?><?php if (!empty($doc['due_date'])): ?> · due <?= h(format_date($doc['due_date'])) ?><?php endif; ?></span>
            </div>
            <b><?= h(money(document_due_amount($doc), doc_currency($doc))) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Spend mix</h2></div>
    <?php if (!$byCat): ?>
      <p class="empty">No expenses yet.</p>
    <?php else: ?>
      <div class="desk-chart desk-chart-sm"><canvas id="desk-chart-spend"></canvas></div>
    <?php endif; ?>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Top clients</h2></div>
    <?php if (!$topClients): ?>
      <p class="empty">No invoices yet.</p>
    <?php else: ?>
      <div class="desk-chart desk-chart-sm"><canvas id="desk-chart-clients"></canvas></div>
    <?php endif; ?>
  </article>
</div>

<div class="card desk-recent">
  <div class="card-head">
    <h2>Recent</h2>
    <a class="btn ghost sm" href="<?= h(url('clients.php')) ?>">Clients</a>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">Nothing issued yet.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
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
            <td><a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a></td>
            <td class="date-cell"><?= h(format_date($doc['date'])) ?></td>
            <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <td><span class="pill<?= invoice_status_label($doc) === 'Overdue' ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php
$payload = json_encode([
    'labels' => array_column($months, 'label'),
    'invoiced' => array_values(array_column($months, 'invoiced')),
    'expenses' => array_values(array_column($months, 'expenses')),
    'cash' => array_values(array_column($months, 'cash')),
    'collect' => [$cashPeriod, max(0, $incomePeriod - $cashPeriod)],
    'cats' => array_keys($byCat),
    'catVals' => array_values($byCat),
    'clients' => array_keys($topClients),
    'clientVals' => array_values($topClients),
    'color' => $color,
    'currency' => $homeCcy,
], JSON_UNESCAPED_UNICODE);
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d = ' . $payload . ';
  var brand = d.color || "#1E4EFF";
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#5c6780";
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
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
  var trend = document.getElementById("desk-chart-trend");
  if (trend) {
    new Chart(trend, {
      type: "line",
      data: {
        labels: d.labels,
        datasets: [
          { label: "Income", data: d.invoiced, borderColor: brand, backgroundColor: brand + "33", tension: .35, fill: true, borderWidth: 2, pointRadius: 3 },
          { label: "Expenditure", data: d.expenses, borderColor: "#3b82f6", backgroundColor: "rgba(59,130,246,.12)", tension: .35, fill: false, borderWidth: 2, pointRadius: 3 },
          { label: "Cash in", data: d.cash, borderColor: "#0f766e", backgroundColor: "transparent", tension: .35, fill: false, borderWidth: 2, pointRadius: 3 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { beginAtZero: true, ticks: { callback: money }, grid: { color: "rgba(8,20,58,.06)" } }, x: { grid: { display: false } } } }
    });
  }
  var collect = document.getElementById("desk-chart-collect");
  if (collect) {
    new Chart(collect, {
      type: "doughnut",
      data: { labels: ["Collected", "Still open"], datasets: [{ data: d.collect, backgroundColor: [brand, "rgba(8,20,58,.12)"], borderWidth: 0 }] },
      options: { cutout: "68%", responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var spend = document.getElementById("desk-chart-spend");
  if (spend && d.catVals && d.catVals.length) {
    new Chart(spend, {
      type: "doughnut",
      data: { labels: d.cats, datasets: [{ data: d.catVals, backgroundColor: [brand, "#08143A", "#c4a35a", "#0f766e", "#b42318", "#64748b"], borderWidth: 0 }] },
      options: { cutout: "58%", responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var clients = document.getElementById("desk-chart-clients");
  if (clients && d.clientVals && d.clientVals.length) {
    new Chart(clients, {
      type: "bar",
      data: { labels: d.clients, datasets: [{ label: "Billed", data: d.clientVals, backgroundColor: brand, borderRadius: 8 }] },
      options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { callback: money }, grid: { color: "rgba(8,20,58,.06)" } }, y: { grid: { display: false } } } }
    });
  }
})();
</script>';
layout_end($script);
