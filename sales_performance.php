<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();

if (!isset($_GET['range']) && trim((string) ($_GET['from'] ?? '')) === '') {
    $_GET['range'] = 'this_month';
}
[$from, $to, $period] = sales_period_bounds();
$stats = sales_stats((int) $user['id'], $from, $to);
$series = sales_series((int) $user['id'], $from, $to);
$target = sales_target_for((int) $user['id']);
$reachGoal = max(0, (int) ($target['reach_target'] ?? 0));
$salesGoal = max(0, (int) ($target['sales_target'] ?? 0));
$followed = sales_leads_query(['agent_id' => (int) $user['id'], 'follow_bucket' => 'done', 'from' => $from, 'to' => $to]);
$pending = sales_leads_query(['agent_id' => (int) $user['id'], 'follow_bucket' => 'due']);

sales_layout_start('Performance', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Performance</h1>
    <p class="lede">Your reach, interest mix and follow-ups for the dates you pick.</p>
  </div>
</div>
<?php render_filters('sales_performance.php', [], ['live' => true]); ?>
<p class="hint" style="margin:-8px 0 16px">Showing <?= $period['from'] ? h(format_date($from) . ' - ' . format_date($to)) : 'all dates' ?>.</p>

<div class="stats">
  <div class="card stat"><?= icon('clients', 20) ?><span>Reach</span><strong><?= (int) $stats['reach'] ?><?= $reachGoal ? ' / ' . $reachGoal : '' ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Onboarded</span><strong><?= (int) $stats['onboarded'] ?><?= $salesGoal ? ' / ' . $salesGoal : '' ?></strong></div>
  <div class="card stat"><?= icon('heart', 20) ?><span>Interested</span><strong><?= (int) $stats['interested'] ?></strong></div>
  <div class="card stat"><?= icon('ban', 20) ?><span>Rejected</span><strong><?= (int) $stats['rejected'] ?></strong></div>
</div>

<?php if ($reachGoal || $salesGoal): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?>Target progress</h2></div>
  <div class="pad-form">
    <?php if ($reachGoal): $p = min(100, (int) round(100 * $stats['reach'] / max(1, $reachGoal))); ?>
      <p class="lede">Reach <?= (int) $stats['reach'] ?> / <?= $reachGoal ?></p>
      <div class="savings-bar"><span style="width:<?= $p ?>%"></span></div>
    <?php endif; ?>
    <?php if ($salesGoal): $p = min(100, (int) round(100 * $stats['onboarded'] / max(1, $salesGoal))); ?>
      <p class="lede" style="margin-top:12px">Sales <?= (int) $stats['onboarded'] ?> / <?= $salesGoal ?></p>
      <div class="savings-bar"><span style="width:<?= $p ?>%"></span></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box">
    <div class="card-head"><h2>Status mix</h2></div>
    <div class="pad-form" style="height:220px"><canvas id="chart-pie"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2>Reach over time</h2></div>
    <div class="pad-form" style="height:220px"><canvas id="chart-line"></canvas></div>
  </div>
</div>

<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2>Follow-ups still open</h2></div>
    <?php if (!$pending): ?><p class="empty">None open.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach (array_slice($pending, 0, 8) as $lead): ?>
          <a class="work-row" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">
            <div><strong><?= h(trim((string) $lead['business_name']) ?: 'Business') ?></strong><span><?= h(format_date($lead['follow_up_date'])) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Followed up (updated)</h2></div>
    <?php if (!$followed): ?><p class="empty">No follow-up updates in this period.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach (array_slice($followed, 0, 8) as $lead): ?>
          <a class="work-row" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">
            <div><strong><?= h(trim((string) $lead['business_name']) ?: 'Business') ?></strong><span><?= h(sales_status_label((string) $lead['status'])) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$payload = json_encode([
    'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
    'pieValues' => [(int) $stats['interested'], (int) $stats['follow_up'], (int) $stats['rejected'], (int) $stats['onboarded']],
    'labels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $series),
    'reach' => array_column($series, 'reach'),
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
$extra = '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d=' . $payload . ';
  var pie=document.getElementById("chart-pie");
  if(pie&&window.Chart){ new Chart(pie,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"}},maintainAspectRatio:false}}); }
  var line=document.getElementById("chart-line");
  if(line&&window.Chart){ new Chart(line,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}},maintainAspectRatio:false}}); }
})();
</script>';
sales_layout_end($extra);
