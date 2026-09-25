<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();

$periodKind = (string) ($_GET['period'] ?? 'daily');
if (!in_array($periodKind, ['daily', 'weekly', 'monthly'], true)) {
    $periodKind = 'daily';
}
$uid = (int) $user['id'];
$progress = sales_progress($uid, $periodKind);
$from = $progress['from'];
$to = $progress['stat_to'];
$stats = $progress['stats'];
$series = sales_series($uid, $from, $to);
$hoursSeries = sales_hours_series($uid, $from, $to);
$hoursTotal = sales_hours_total($uid, $from, $to);
$rejectionReport = sales_rejection_breakdown($uid, $from, $to);
$followed = sales_leads_query(['agent_id' => $uid, 'follow_bucket' => 'done', 'from' => $from, 'to' => $to]);
$pending = sales_leads_query(['agent_id' => $uid, 'follow_bucket' => 'due']);
$periodLabels = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

sales_layout_start('Performance', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Performance</h1>
    <p class="lede">Your daily, weekly and monthly goals with charts that match what admin sees.</p>
  </div>
</div>

<nav class="sales-period-tabs" aria-label="Performance period">
  <?php foreach ($periodLabels as $k => $label): ?>
    <a class="chip<?= $periodKind === $k ? ' is-on' : '' ?>" href="<?= h(url('sales_performance.php?period=' . $k)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
<p class="hint" style="margin:-4px 0 16px"><?= h($periodLabels[$periodKind]) ?> · <?= h(format_date($from)) ?><?= $from !== $to ? ' - ' . h(format_date($to)) : '' ?></p>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?><?= h($periodLabels[$periodKind]) ?> goals</h2></div>
  <div class="pad-form">
    <?php sales_render_goal_bars($progress, ['force_reach' => $periodKind === 'daily', 'force_sales' => true]); ?>
  </div>
</div>

<div class="stats">
  <div class="card stat"><?= icon('clients', 20) ?><span>Reach</span><strong><?= (int) $stats['reach'] ?><?= $progress['reach_goal'] ? ' / ' . (int) $progress['reach_goal'] : '' ?></strong></div>
  <div class="card stat"><?= icon('flag', 20) ?><span>Sales</span><strong><?= (int) $stats['wins'] ?><?= $progress['sales_goal'] ? ' / ' . (int) $progress['sales_goal'] : '' ?></strong></div>
  <div class="card stat"><?= icon('heart', 20) ?><span>Interested</span><strong><?= (int) $stats['interested'] ?></strong></div>
  <div class="card stat"><?= icon('clock', 20) ?><span>Field hours</span><strong><?= h(sales_format_hours($hoursTotal)) ?></strong></div>
</div>

<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box">
    <div class="card-head"><h2>Status mix</h2></div>
    <div class="pad-form" style="height:220px"><canvas id="chart-pie"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2><?= $periodKind === 'daily' ? 'Today by status' : 'Reach & sales over time' ?></h2></div>
    <div class="pad-form" style="height:220px"><canvas id="chart-line"></canvas></div>
  </div>
</div>

<div class="card chart-box" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?>Hours in the field</h2></div>
  <div class="pad-form" style="height:240px"><canvas id="chart-hours"></canvas></div>
</div>
<?php sales_render_rejection_report($rejectionReport); ?>

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
    'wins' => array_map(static fn ($r) => (int) $r['interested'] + (int) $r['onboarded'], $series),
    'barLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
    'barValues' => [(int) $stats['interested'], (int) $stats['follow_up'], (int) $stats['rejected'], (int) $stats['onboarded']],
    'hourLabels' => array_map(static fn ($r) => date('j M', strtotime((string) $r['date'])), $hoursSeries),
    'hours' => array_map(static fn ($r) => (float) $r['hours'], $hoursSeries),
    'daily' => $periodKind === 'daily',
    'color' => brand_color(),
], JSON_UNESCAPED_UNICODE);
$extra = '<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script defer>
(function(){
  function go(){
    if(!window.Chart){ setTimeout(go,40); return; }
    var d=' . $payload . ';
    var pie=document.getElementById("chart-pie");
    if(pie){ new Chart(pie,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"}},maintainAspectRatio:false}}); }
    var line=document.getElementById("chart-line");
    if(line){
      if(d.daily){
        new Chart(line,{type:"bar",data:{labels:d.barLabels,datasets:[{data:d.barValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});
      } else {
        new Chart(line,{type:"line",data:{labels:d.labels,datasets:[{label:"Reach",data:d.reach,borderColor:d.color,tension:.3,fill:false},{label:"Sales",data:d.wins,borderColor:"#0f766e",tension:.3,fill:false}]},options:{plugins:{legend:{position:"bottom"}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});
      }
    }
    var hours=document.getElementById("chart-hours");
    if(hours){
      new Chart(hours,{type:"line",data:{labels:d.hourLabels,datasets:[{label:"Hours in field",data:d.hours,borderColor:d.color,backgroundColor:d.color+"33",tension:.35,fill:true,pointRadius:3}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,title:{display:true,text:"Hours"}}},maintainAspectRatio:false}});
    }
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",go); else go();
})();
</script>';
sales_layout_end($extra);
