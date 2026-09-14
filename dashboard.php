<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$brand = branding();
$cid = current_company_id();
$homeCcy = default_currency();
$base = $homeCcy;
$since = date('Y-m-01', strtotime('-5 months'));
$monthStart = date('Y-m-01');

$invoices = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? AND d.kind = 'invoice' AND d.status = 'issued' ORDER BY d.due_date IS NULL, d.due_date, d.id DESC", 'i', [$cid]));
$open = array_values(array_filter($invoices, static fn ($d) => $d['balance'] > 0));
$overdue = array_values(array_filter($open, static fn ($d) => !empty($d['due_date']) && $d['due_date'] < today()));
$incomeMonth = 0;
$incomeAll = 0;
foreach ($invoices as $d) {
    $total = convert_money($d['totals']['total'], doc_currency($d), $base);
    $incomeAll += $total;
    if ($d['date'] >= $monthStart) {
        $incomeMonth += $total;
    }
}
$expAll = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? AND d.kind = 'expense' AND d.status = 'issued'", 'i', [$cid]));
$expenseMonth = 0;
$expenseAll = 0;
$creditorOpen = 0;
$byCat = [];
foreach ($expAll as $d) {
    $total = convert_money($d['totals']['total'], doc_currency($d), $base);
    $expenseAll += $total;
    if ($d['date'] >= $monthStart) {
        $expenseMonth += $total;
    }
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
    "SELECT d.*, r.kind AS related_kind FROM documents d LEFT JOIN documents r ON r.id = d.related_id WHERE d.company_id = ? AND d.kind = 'receipt' AND d.status = 'issued' AND d.date >= ?",
    'is',
    [$cid, $since]
));
$cashMonth = 0;
$cashHalf = 0;
foreach ($receipts as $d) {
    if (($d['related_kind'] ?? '') === 'expense') {
        continue;
    }
    $amt = convert_money((float) ($d['allocated_amount'] ?: $d['totals']['total']), doc_currency($d), $base);
    $cashHalf += $amt;
    if ($d['date'] >= $monthStart) {
        $cashMonth += $amt;
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

$recent = attach_document_totals(db_all("SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? ORDER BY d.id DESC LIMIT 8", 'i', [$cid]));
$queue = $overdue ?: $open;
$hour = (int) date('G');
$hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim((string) $user['name']))[0];
if ($firstName === '') {
    $firstName = (string) ($brand['name'] ?? 'there');
}
$fxRate = fx_home_per_usd();
if ($homeCcy === 'USD') {
    $fxValue = 'Books already in USD';
} else {
    $prettyRate = rtrim(rtrim(number_format($fxRate, 4, '.', ','), '0'), '.');
    $fxValue = '1 USD = ' . $prettyRate . ' ' . $homeCcy;
}

$openAmt = documents_sum($open, 'balance');
$overdueAmt = documents_sum($overdue, 'balance');
$netMonth = $incomeMonth - $expenseMonth;
$collectRate = $incomeMonth > 0 ? (int) round(100 * min($cashMonth, $incomeMonth) / $incomeMonth) : 0;

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
$canQuote = user_can_kind('quotation');
$canInvoice = user_can_kind('invoice');
$deskCompany = current_company();

layout_start('Desk', $user);
?>
<?php if ($deskCompany): ?>
  <div class="desk-term-wrap">
    <?php render_top_term($deskCompany); ?>
  </div>
<?php endif; ?>

<div class="desk-hero">
  <div class="desk-hello">
    <p class="desk-kicker"><?= h($brand['name']) ?></p>
    <h1 class="desk-hello-title"><?= h($hello) ?> <?= h($firstName) ?></h1>
    <p class="desk-hello-lead">This month at a glance - invoices, collections, spend and who still owes you.</p>
    <div class="desk-fx">
      <div>
        <span>Main currency</span>
        <strong><?= h($homeCcy) ?></strong>
      </div>
      <div>
        <span>USD conversion</span>
        <strong><?= h($fxValue) ?></strong>
      </div>
    </div>
  </div>
  <div class="actions">
    <?php if ($canQuote): ?><a class="btn ghost" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation', 16) ?>Quotation</a><?php endif; ?>
    <?php if ($canInvoice): ?><a class="btn" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice', 16) ?>Invoice</a><?php endif; ?>
    <a class="btn ghost" href="<?= h(url('activities.php')) ?>"><?= icon('clock', 16) ?>Activities</a>
  </div>
</div>

<div class="desk-kpis">
  <a class="desk-kpi is-brand" href="<?= h(url(is_desk_admin($user) ? 'reports.php' : 'documents.php?kind=invoice')) ?>">
    <span>Invoiced this month</span>
    <strong><?= h(money($incomeMonth)) ?></strong>
    <em><?= $netMonth >= 0 ? 'Net ' . money($netMonth) : 'Net −' . money(abs($netMonth)) ?></em>
  </a>
  <a class="desk-kpi" href="<?= h(url('documents.php?kind=receipt')) ?>">
    <span>Collected</span>
    <strong><?= h(money($cashMonth)) ?></strong>
    <em><?= $collectRate ?>% of this month’s invoices</em>
  </a>
  <?php if (user_can_kind('expense')): ?>
  <a class="desk-kpi" href="<?= h(url('documents.php?kind=expense')) ?>">
    <span>Spent this month</span>
    <strong><?= h(money($expenseMonth)) ?></strong>
    <em>Suppliers still due <?= h(money($creditorOpen)) ?></em>
  </a>
  <?php endif; ?>
  <a class="desk-kpi<?= $overdueAmt > 0 ? ' is-warn' : '' ?>" href="<?= h(url('debtors.php')) ?>">
    <span>Outstanding</span>
    <strong><?= h(money($openAmt)) ?></strong>
    <em><?= count($overdue) ?> overdue · <?= h(money($overdueAmt)) ?></em>
  </a>
</div>

<div class="desk-bento">
  <article class="card desk-tile desk-tile-wide">
    <div class="card-head">
      <h2>Six-month performance</h2>
      <?php if (is_desk_admin($user)): ?><a class="btn ghost sm" href="<?= h(url('reports.php')) ?>">Full reports</a><?php endif; ?>
    </div>
    <div class="desk-chart"><canvas id="desk-chart-trend"></canvas></div>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Collections</h2></div>
    <div class="desk-chart desk-chart-sm"><canvas id="desk-chart-collect"></canvas></div>
    <p class="desk-tile-note"><?= $collectRate ?>% collected against invoices raised this month.</p>
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
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">All invoices</a>
    </div>
    <?php if (!$queue): ?>
      <p class="empty">Nothing outstanding. Issue an invoice when you are ready.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach (array_slice($queue, 0, 5) as $doc): ?>
          <a class="work-row" href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>">
            <div>
              <strong><?= h($doc['party_name']) ?></strong>
              <span><?= h($doc['number']) ?> · due <?= h(format_date($doc['due_date'])) ?></span>
            </div>
            <b><?= h(money($doc['balance'], doc_currency($doc))) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Spend mix</h2></div>
    <?php if (!$byCat): ?>
      <p class="empty">No expenses in the last six months to chart.</p>
    <?php else: ?>
      <div class="desk-chart desk-chart-sm"><canvas id="desk-chart-spend"></canvas></div>
    <?php endif; ?>
  </article>
  <article class="card desk-tile">
    <div class="card-head"><h2>Top clients billed</h2></div>
    <?php if (!$topClients): ?>
      <p class="empty">No invoices in the last six months.</p>
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
    'collect' => [$cashMonth, max(0, $incomeMonth - $cashMonth)],
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
          { label: "Invoiced", data: d.invoiced, borderColor: brand, backgroundColor: brand + "33", tension: .35, fill: true, borderWidth: 2, pointRadius: 3 },
          { label: "Expenses", data: d.expenses, borderColor: "#b42318", backgroundColor: "rgba(180,35,24,.12)", tension: .35, fill: true, borderWidth: 2, pointRadius: 3 },
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
