<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_pnl();
$summary = pnl_summary();
$charts = pnl_chart_data($summary);
$ccy = $summary['currency'];
$chartPayload = json_encode($charts, JSON_UNESCAPED_UNICODE);

layout_start('Profit & Loss', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Profit &amp; Loss</h1>
    <p class="lede">Income, expenses, refunds and returns for the dates you pick, with net profit on one desk.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('pnl_entries.php')) ?>"><?= icon('bank', 16) ?>Ledger</a>
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=refund')) ?>"><?= icon('wallet', 16) ?>Refund</a>
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=return_note')) ?>"><?= icon('truck', 16) ?>Return</a>
    <a class="btn" href="<?= h(url('pnl_entries.php?new=1')) ?>"><?= icon('plus', 16) ?>Entry</a>
  </div>
</div>
<?php render_pnl_subnav('pnl.php'); ?>
<?php render_filters('pnl.php'); ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income</span><strong><?= h(money($summary['income_total'], $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('wallet', 20) ?><span>Expenses</span><strong><?= h(money($summary['expense_total'], $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('package', 20) ?><span>Profit</span><strong><?= h(money($summary['profit'] ?? ($summary['sales'] - ($summary['cogs'] ?? 0)), $ccy)) ?></strong><em>Sell minus buy</em></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Net profit</span><strong class="<?= $summary['net'] < 0 ? 'neg' : 'pos' ?>"><?= h(money($summary['net'], $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Cash net</span><strong><?= h(money($summary['cash_net'], $ccy)) ?></strong><em>in <?= h(money($summary['cash_in'], $ccy)) ?> · out <?= h(money($summary['cash_out'], $ccy)) ?></em></div>
</div>

<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('reports', 16) ?>Income, costs and profit</h2>
      <?php render_chart_download('pnl-chart-trend', 'pnl-trend.png'); ?>
    </div>
    <div class="chart-frame"><canvas id="pnl-chart-trend"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('bank', 16) ?>Cash movement</h2>
      <?php render_chart_download('pnl-chart-cash', 'pnl-cash.png'); ?>
    </div>
    <div class="chart-frame"><canvas id="pnl-chart-cash"></canvas></div>
  </div>
</div>
<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('invoice', 16) ?>Income mix</h2>
      <?php if (!empty($charts['incomeCatValues'])): render_chart_download('pnl-chart-income', 'pnl-income.png'); endif; ?>
    </div>
    <?php if (empty($charts['incomeCatValues'])): ?>
      <p class="empty">No income in this period to chart.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="pnl-chart-income"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head">
      <h2><?= icon('wallet', 16) ?>Cost mix</h2>
      <?php if (!empty($charts['expenseCatValues'])): render_chart_download('pnl-chart-expense', 'pnl-costs.png'); endif; ?>
    </div>
    <?php if (empty($charts['expenseCatValues'])): ?>
      <p class="empty">No costs in this period to chart.</p>
    <?php else: ?>
      <div class="chart-frame"><canvas id="pnl-chart-expense"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="pnl-split">
  <div class="card pnl-card">
    <div class="card-head"><h2><?= icon('invoice', 16) ?>Income</h2></div>
    <div class="pnl-sheet-wrap">
      <table class="pnl-sheet">
        <thead>
          <tr>
            <th scope="col">Line</th>
            <th scope="col" class="pnl-note">Note</th>
            <th scope="col" class="right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td data-label="Line"><span class="pnl-line">Sales invoices</span><span class="pnl-note-mobile">Accrual net</span></td>
            <td class="pnl-note" data-label="Note">Accrual net</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['sales'], $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Other income</span><span class="pnl-note-mobile">Manual ledger</span></td>
            <td class="pnl-note" data-label="Note">Manual ledger</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['manual_income'], $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Supplier refunds</span><span class="pnl-note-mobile">Money back in</span></td>
            <td class="pnl-note" data-label="Note">Money back in</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['refund_in'], $ccy)) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th scope="row" colspan="2">Total income</th>
            <th class="right mono"><?= h(money($summary['income_total'], $ccy)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php if ($summary['by_income_cat']): ?>
      <div class="pnl-breakdown">
        <h3>By category</h3>
        <table class="pnl-sheet pnl-sheet-sub">
          <tbody>
          <?php foreach (array_slice($summary['by_income_cat'], 0, 8, true) as $cat => $amt): ?>
            <tr>
              <td><span class="pnl-line"><?= h((string) $cat) ?></span></td>
              <td class="right mono"><?= h(money((float) $amt, $ccy)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card pnl-card">
    <div class="card-head"><h2><?= icon('wallet', 16) ?>Costs</h2></div>
    <div class="pnl-sheet-wrap">
      <table class="pnl-sheet">
        <thead>
          <tr>
            <th scope="col">Line</th>
            <th scope="col" class="pnl-note">Note</th>
            <th scope="col" class="right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td data-label="Line"><span class="pnl-line">Cost of goods</span><span class="pnl-note-mobile">Buying price of items sold</span></td>
            <td class="pnl-note" data-label="Note">Buying price of items sold</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['cogs'] ?? 0, $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Profit</span><span class="pnl-note-mobile">Selling minus buying</span></td>
            <td class="pnl-note" data-label="Note">Selling minus buying</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['profit'] ?? 0, $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Expenses</span><span class="pnl-note-mobile">Bills, not stock purchases</span></td>
            <td class="pnl-note" data-label="Note">Bills, not stock purchases</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['operating_costs'] ?? $summary['costs'], $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Other costs</span><span class="pnl-note-mobile">Manual ledger</span></td>
            <td class="pnl-note" data-label="Note">Manual ledger</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['manual_expense'], $ccy)) ?></td>
          </tr>
          <tr>
            <td data-label="Line"><span class="pnl-line">Customer refunds</span><span class="pnl-note-mobile">Money out</span></td>
            <td class="pnl-note" data-label="Note">Money out</td>
            <td class="right mono" data-label="Amount"><?= h(money($summary['refund_out'], $ccy)) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th scope="row" colspan="2">Total costs</th>
            <th class="right mono"><?= h(money($summary['expense_total'], $ccy)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php if ($summary['by_expense_cat']): ?>
      <div class="pnl-breakdown">
        <h3>By category</h3>
        <table class="pnl-sheet pnl-sheet-sub">
          <tbody>
          <?php foreach (array_slice($summary['by_expense_cat'], 0, 8, true) as $cat => $amt): ?>
            <tr>
              <td><span class="pnl-line"><?= h((string) $cat) ?></span></td>
              <td class="right mono"><?= h(money((float) $amt, $ccy)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="pnl-split" style="margin-top:16px">
  <div class="card pnl-card">
    <div class="card-head">
      <h2><?= icon('wallet', 16) ?>Recent refunds</h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=refund')) ?>">All</a>
    </div>
    <?php if (!$summary['refunds']): ?>
      <p class="empty">No refunds in this period. <a href="<?= h(url('document_new.php?kind=refund')) ?>">Record a refund</a>.</p>
    <?php else: ?>
      <div class="pnl-sheet-wrap">
        <table class="pnl-sheet pnl-ledger">
          <thead><tr><th>Date</th><th>Party</th><th>Direction</th><th class="right">Amount</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($summary['refunds'], 0, 8) as $d): ?>
            <tr>
              <td data-label="Date" class="mono"><?= h(format_date($d['date'])) ?></td>
              <td data-label="Party"><a href="<?= h(url('document_view.php?id=' . (int) $d['id'])) ?>"><?= h($d['party_name']) ?> · <?= h($d['number']) ?></a></td>
              <td data-label="Direction"><?= pnl_refund_direction($d) === 'in' ? 'In' : 'Out' ?></td>
              <td data-label="Amount" class="right mono"><?= h(money(pnl_doc_amount($d), $ccy)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <div class="card pnl-card">
    <div class="card-head">
      <h2><?= icon('truck', 16) ?>Return notes</h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=return_note')) ?>">All</a>
    </div>
    <?php if (!$summary['returns']): ?>
      <p class="empty">No return notes in this period. <a href="<?= h(url('document_new.php?kind=return_note')) ?>">Add a return note</a>.</p>
    <?php else: ?>
      <div class="pnl-sheet-wrap">
        <table class="pnl-sheet pnl-ledger">
          <thead><tr><th>Date</th><th>Party</th><th>Direction</th><th class="right">No.</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($summary['returns'], 0, 8) as $d): ?>
            <tr>
              <td data-label="Date" class="mono"><?= h(format_date($d['date'])) ?></td>
              <td data-label="Party"><a href="<?= h(url('document_view.php?id=' . (int) $d['id'])) ?>"><?= h($d['party_name']) ?></a></td>
              <td data-label="Direction"><?= pnl_return_direction($d) === 'in' ? 'To supplier' : 'From customer' ?></td>
              <td data-label="No." class="right mono"><?= h($d['number']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script>
document.addEventListener("DOMContentLoaded", function () {
(function(){
  var d = ' . $chartPayload . ';
  if (!window.Chart || !d) return;
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  var brand = getComputedStyle(document.documentElement).getPropertyValue("--brand").trim() || "#82B440";
  var deep = getComputedStyle(document.documentElement).getPropertyValue("--brand-3").trim() || "#1f3a12";
  var accent = getComputedStyle(document.documentElement).getPropertyValue("--brand-2").trim() || "#c4a35a";
  var palette = [brand, deep, accent, "#4a6fa5", "#b42318", "#6b7c5e", "#8d6e63", "#546e7a"];
  function money(v){
    var n = Number(v);
    if (!isFinite(n)) return "";
    var cur = d.currency || "";
    var sign = n < 0 ? "-" : "";
    var a = Math.abs(n);
    var unit = "";
    var x = a;
    if (a >= 1e12) { x = a / 1e12; unit = "T"; }
    else if (a >= 1e9) { x = a / 1e9; unit = "B"; }
    else if (a >= 1e6) { x = a / 1e6; unit = "M"; }
    var num = unit ? (x >= 100 ? String(Math.round(x)) : (Math.round(x * 10) / 10).toFixed(1).replace(/\\.0$/, "")) : Math.round(a).toLocaleString("en-US");
    return (cur ? cur + " " : "") + sign + num + unit;
  }
  var trend = document.getElementById("pnl-chart-trend");
  if (trend) {
    new Chart(trend, {
      type: "line",
      data: {
        labels: d.months,
        datasets: [
          { label: "Income", data: d.income, borderColor: brand, backgroundColor: "rgba(130,180,64,.14)", tension: .3, fill: true },
          { label: "Costs", data: d.expense, borderColor: "#b42318", backgroundColor: "rgba(180,35,24,.08)", tension: .3, fill: true },
          { label: "Net", data: d.net, borderColor: deep, tension: .3, fill: false }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
  var cash = document.getElementById("pnl-chart-cash");
  if (cash) {
    new Chart(cash, {
      type: "bar",
      data: {
        labels: d.months,
        datasets: [{ label: "Cash net", data: d.cash, backgroundColor: d.cash.map(function(v){ return v < 0 ? "#b42318" : brand; }) }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: money } } } }
    });
  }
  var income = document.getElementById("pnl-chart-income");
  if (income && d.incomeCatValues && d.incomeCatValues.length) {
    new Chart(income, {
      type: "doughnut",
      data: { labels: d.incomeCatLabels, datasets: [{ data: d.incomeCatValues, backgroundColor: palette }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
  var expense = document.getElementById("pnl-chart-expense");
  if (expense && d.expenseCatValues && d.expenseCatValues.length) {
    new Chart(expense, {
      type: "doughnut",
      data: { labels: d.expenseCatLabels, datasets: [{ data: d.expenseCatValues, backgroundColor: palette.slice().reverse() }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
    });
  }
})();
});
</script>';
layout_end($script);
?>
