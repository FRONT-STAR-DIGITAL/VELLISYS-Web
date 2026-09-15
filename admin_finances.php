<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : '2000-01-01';
$to = $period['to'] !== '' ? $period['to'] : desk_now()->format('Y-m-d');

$allUsd = 0.0;
$periodUsd = 0.0;
$ledger = [];
try {
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger');
    $allUsd = (float) ($row['t'] ?? 0);
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) BETWEEN ? AND ?', 'ss', [$from, $to]);
    $periodUsd = (float) ($row['t'] ?? 0);
    $ledger = db_all(
        'SELECT l.*, c.name AS company_name
         FROM platform_fee_ledger l
         LEFT JOIN companies c ON c.id = l.company_id
         WHERE DATE(l.occurred_at) BETWEEN ? AND ?
         ORDER BY l.occurred_at DESC, l.id DESC
         LIMIT 200',
        'ss',
        [$from, $to]
    );
} catch (Throwable $e) {
    $ledger = [];
}

$companies = db_all('SELECT * FROM companies ORDER BY name');
$feeBalance = 0.0;
foreach ($companies as $c) {
    $feeBalance += platform_convert(company_fee_balance($c), company_fee_currency($c), 'USD');
}

$months = month_axis(12);
$monthSum = array_fill_keys($months, 0.0);
try {
    $series = db_all('SELECT DATE_FORMAT(occurred_at, "%Y-%m") AS ym, SUM(amount_usd) AS t FROM platform_fee_ledger GROUP BY ym');
    foreach ($series as $s) {
        $ym = (string) ($s['ym'] ?? '');
        if (isset($monthSum[$ym])) {
            $monthSum[$ym] = (float) $s['t'];
        }
    }
} catch (Throwable $e) {
}

$ccy = platform_currency();
$chart = [];
foreach ($months as $ym) {
    $chart[] = round(platform_convert($monthSum[$ym], 'USD', $ccy), 2);
}

layout_admin_start('Finances', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('bank') ?>Finances</h1>
    <p class="lede">What Vellisys has taken in for desks: package payments and paid terms. This is not the books on a company desk.</p>
  </div>
</div>
<?php render_filters('admin_finances.php', [], ['live' => true]); ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>This period</span><strong><?= h(platform_money($periodUsd, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>All time</span><strong><?= h(platform_money($allUsd, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('receipt', 20) ?><span>Still due on terms</span><strong><?= h(platform_money($feeBalance, 'USD')) ?></strong></div>
  <div class="card stat"><?= icon('building', 20) ?><span>Paying companies</span><strong><?= count(array_filter($companies, static fn ($c) => company_fee_paid($c) > 0)) ?></strong></div>
</div>

<div class="card chart-box" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('reports', 16) ?>Taken in over time</h2></div>
  <?php if (array_sum($chart) <= 0): ?>
    <p class="empty">When a package is paid or you record a paid term, it plots here in <?= h($ccy) ?>.</p>
  <?php else: ?>
    <canvas id="chart-finance"></canvas>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Renewals and what they pay</h2></div>
  <?php if (!$companies): ?>
    <p class="empty">No companies yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Company</th>
            <th>Term</th>
            <th>Renews</th>
            <th class="right">They pay</th>
            <th class="right">Paid</th>
            <th class="right">Still due</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $c): ?>
            <tr>
              <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
              <td><?= h(company_term_label($c)) ?></td>
              <td><?= !empty($c['expires_at']) ? h(format_date((string) $c['expires_at'])) : '-' ?></td>
              <td class="right mono"><?= h(platform_money_company_fee($c, 'amount')) ?></td>
              <td class="right mono"><?= h(platform_money_company_fee($c, 'paid')) ?></td>
              <td class="right mono"><?= h(platform_money_company_fee($c, 'balance')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('receipt', 16) ?>Money in</h2></div>
  <?php if (!$ledger): ?>
    <p class="empty">No payments in this date range.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>When</th>
            <th>Company</th>
            <th>Source</th>
            <th class="right">Amount</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledger as $row): ?>
            <tr>
              <td class="mono"><?= h(format_when($row['occurred_at'] ?? null)) ?></td>
              <td><?= h((string) ($row['company_name'] ?? 'Vellisys')) ?></td>
              <td><?= h((string) ($row['source'] ?? '')) ?></td>
              <td class="right mono"><?= h(platform_money((float) $row['amount'], (string) $row['currency'])) ?></td>
              <td><?= h((string) ($row['note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (array_sum($chart) > 0): ?>
<script src="<?= h(asset('js/chart.umd.min.js')) ?>"></script>
<script>
(function () {
  var el = document.getElementById('chart-finance');
  if (!el || !window.Chart) return;
  new Chart(el, {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{ label: <?= json_encode('Taken in (' . $ccy . ')') ?>, data: <?= json_encode($chart) ?>, borderColor: '#1E4EFF', tension: 0.25, fill: false }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
})();
</script>
<?php endif; ?>
<?php layout_end();