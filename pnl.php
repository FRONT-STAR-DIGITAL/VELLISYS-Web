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
  <div class="card stat"><?= icon('reports', 20) ?><span>Net profit</span><strong class="<?= $summary['net'] < 0 ? 'neg' : 'pos' ?>"><?= h(money($summary['net'], $ccy)) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Cash net</span><strong><?= h(money($summary['cash_net'], $ccy)) ?></strong><em>in <?= h(money($summary['cash_in'], $ccy)) ?> · out <?= h(money($summary['cash_out'], $ccy)) ?></em></div>
</div>

<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Income, costs and profit</h2></div>
    <canvas id="pnl-chart-trend" height="120"></canvas>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('bank', 16) ?>Cash movement</h2></div>
    <canvas id="pnl-chart-cash" height="120"></canvas>
  </div>
</div>
<div class="chart-grid equal">
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('invoice', 16) ?>Income mix</h2></div>
    <?php if (empty($charts['incomeCatValues'])): ?>
      <p class="empty">No income in this period to chart.</p>
    <?php else: ?>
      <canvas id="pnl-chart-income" height="120"></canvas>
    <?php endif; ?>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= icon('wallet', 16) ?>Cost mix</h2></div>
    <?php if (empty($charts['expenseCatValues'])): ?>
      <p class="empty">No costs in this period to chart.</p>
    <?php else: ?>
      <canvas id="pnl-chart-expense" height="120"></canvas>
    <?php endif; ?>
  </div>
</div>

<div class="desk-grid">
  <div class="card">
    <div class="card-head"><h2><?= icon('invoice', 16) ?>Income</h2></div>
    <div class="work-list">
      <div class="work-row"><div><strong>Sales invoices</strong><span>Accrual net</span></div><b><?= h(money($summary['sales'], $ccy)) ?></b></div>
      <div class="work-row"><div><strong>Other income</strong><span>Manual ledger</span></div><b><?= h(money($summary['manual_income'], $ccy)) ?></b></div>
      <div class="work-row"><div><strong>Supplier refunds</strong><span>Money back in</span></div><b><?= h(money($summary['refund_in'], $ccy)) ?></b></div>
    </div>
    <?php if ($summary['by_income_cat']): ?>
      <div class="pnl-cats">
        <?php foreach (array_slice($summary['by_income_cat'], 0, 6, true) as $cat => $amt): ?>
          <div><span><?= h((string) $cat) ?></span><strong><?= h(money((float) $amt, $ccy)) ?></strong></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('wallet', 16) ?>Costs</h2></div>
    <div class="work-list">
      <div class="work-row"><div><strong>Expenses</strong><span>Bills and costs</span></div><b><?= h(money($summary['costs'], $ccy)) ?></b></div>
      <div class="work-row"><div><strong>Other costs</strong><span>Manual ledger</span></div><b><?= h(money($summary['manual_expense'], $ccy)) ?></b></div>
      <div class="work-row"><div><strong>Customer refunds</strong><span>Money out</span></div><b><?= h(money($summary['refund_out'], $ccy)) ?></b></div>
    </div>
    <?php if ($summary['by_expense_cat']): ?>
      <div class="pnl-cats">
        <?php foreach (array_slice($summary['by_expense_cat'], 0, 6, true) as $cat => $amt): ?>
          <div><span><?= h((string) $cat) ?></span><strong><?= h(money((float) $amt, $ccy)) ?></strong></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="desk-grid" style="margin-top:16px">
  <div class="card">
    <div class="card-head">
      <h2><?= icon('wallet', 16) ?>Recent refunds</h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=refund')) ?>">All</a>
    </div>
    <?php if (!$summary['refunds']): ?>
      <p class="empty">No refunds in this period. <a href="<?= h(url('document_new.php?kind=refund')) ?>">Record a refund</a>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="grid">
          <thead><tr><th>Date</th><th>Party</th><th>Direction</th><th>Amount</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($summary['refunds'], 0, 8) as $d): ?>
            <tr>
              <td><?= h(format_date($d['date'])) ?></td>
              <td><a href="<?= h(url('document_view.php?id=' . (int) $d['id'])) ?>"><?= h($d['party_name']) ?> · <?= h($d['number']) ?></a></td>
              <td><?= pnl_refund_direction($d) === 'in' ? 'In' : 'Out' ?></td>
              <td><?= h(money(pnl_doc_amount($d), $ccy)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head">
      <h2><?= icon('truck', 16) ?>Return notes</h2>
      <a class="btn ghost sm" href="<?= h(url('documents.php?kind=return_note')) ?>">All</a>
    </div>
    <?php if (!$summary['returns']): ?>
      <p class="empty">No return notes in this period. <a href="<?= h(url('document_new.php?kind=return_note')) ?>">Add a return note</a>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="grid">
          <thead><tr><th>Date</th><th>Party</th><th>Direction</th><th>No.</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($summary['returns'], 0, 8) as $d): ?>
            <tr>
              <td><?= h(format_date($d['date'])) ?></td>
              <td><a href="<?= h(url('document_view.php?id=' . (int) $d['id'])) ?>"><?= h($d['party_name']) ?></a></td>
              <td><?= pnl_return_direction($d) === 'in' ? 'To supplier' : 'From customer' ?></td>
              <td><?= h($d['number']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$script = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d = ' . $chartPayload . ';
  if (!window.Chart || !d) return;
  Chart.defaults.font.family = "Montserrat, sans-serif";
  Chart.defaults.color = "#66705f";
  var brand = getComputedStyle(document.documentElement).getPropertyValue("--brand").trim() || "#82B440";
  var deep = getComputedStyle(document.documentElement).getPropertyValue("--brand-3").trim() || "#1f3a12";
  var accent = getComputedStyle(document.documentElement).getPropertyValue("--brand-2").trim() || "#c4a35a";
  var palette = [brand, deep, accent, "#4a6fa5", "#b42318", "#6b7c5e", "#8d6e63", "#546e7a"];
  function money(v){ return (d.currency || "") + " " + Number(v).toLocaleString("en-US"); }
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
</script>';
layout_end($script);
?>
