<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$error = '';
$clock = sales_today_clock((int) $user['id']);
$clockedIn = sales_is_clocked_in((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'clock_in') {
        $done = sales_clock_in((int) $user['id'], post('location_city'), post('notes'));
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not clock in.');
        } else {
            flash(empty($done['already'])
                ? (!empty($done['resumed']) ? 'Clocked back in. You can log visits again.' : 'Clocked in. You can log visits now.')
                : 'Already clocked in today.');
            redirect(sales_home());
        }
    } elseif ($action === 'clock_out') {
        $done = sales_clock_out((int) $user['id']);
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not clock out.');
        } else {
            $mins = (int) ($done['minutes'] ?? 0);
            flash(empty($done['already'])
                ? ('Clocked out. Field time today: ' . sales_format_hours($mins / 60) . '.')
                : 'Already clocked out.');
            redirect(sales_home());
        }
    }
}

$uid = (int) $user['id'];
$daily = sales_progress($uid, 'daily');
$weekly = sales_progress($uid, 'weekly');
$monthly = sales_progress($uid, 'monthly');
$todayStats = $daily['stats'];
$due = sales_followups_due($uid, 1);
$recent = sales_leads_query(['agent_id' => $uid, 'limit' => 6]);
$daySeries = sales_series($uid, today(), today());
$todayMins = $clock ? sales_clock_minutes($clock) : 0;

sales_layout_start('Home', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('home') ?>Field home</h1>
    <p class="lede">Clock in once a day, then log businesses you reach. Daily goals stay visible after you hit them - keep going.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if (!$clockedIn): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?><?= $clock ? 'Clock back in' : 'Clock in' ?></h2></div>
  <form method="post" class="pad-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clock_in">
    <?php if ($clock): ?>
      <p class="hint" style="margin-top:0">You clocked out earlier. Field time so far today: <strong><?= h(sales_format_hours($todayMins / 60)) ?></strong>.</p>
    <?php endif; ?>
    <label for="location_city">Where are you? (city / area)</label>
    <input id="location_city" name="location_city" required placeholder="e.g. Kampala, Nakawa" autocomplete="address-level2" value="<?= h((string) ($clock['location_city'] ?? '')) ?>">
    <label for="notes">Note (optional)</label>
    <input id="notes" name="notes" placeholder="Territory or route" value="<?= h((string) ($clock['notes'] ?? '')) ?>">
    <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Clock in for today</button></div>
  </form>
</div>
<?php else: ?>
<div class="card sales-clock-card" style="margin-bottom:16px">
  <div class="card-head">
    <h2><?= icon('clock', 16) ?>On the clock</h2>
    <form method="post" class="sales-clock-out-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clock_out">
      <button class="btn ghost sm" type="submit"><?= icon('logout', 14) ?>Clock out</button>
    </form>
  </div>
  <div class="pad-form sales-clock-status">
    <p class="flash" style="margin:0"><?= icon('check', 16) ?>Clocked in · <?= h((string) $clock['location_city']) ?> · <?= h(sales_format_hours($todayMins / 60)) ?> in field today</p>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2><?= icon('flag', 16) ?>Today’s goals</h2>
    <a class="btn ghost sm" href="<?= h(url('sales_performance.php?period=daily')) ?>">All periods</a>
  </div>
  <div class="pad-form">
    <?php sales_render_goal_bars($daily, ['force_reach' => true, 'force_sales' => true]); ?>
  </div>
</div>

<div class="stats">
  <div class="card stat"><?= icon('clients', 20) ?><span>Reached today</span><strong><?= (int) $todayStats['reach'] ?></strong></div>
  <div class="card stat"><?= icon('heart', 20) ?><span>Sales today</span><strong><?= (int) $todayStats['wins'] ?></strong></div>
  <div class="card stat"><?= icon('calendar', 20) ?><span>Follow-ups due</span><strong><?= count($due) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Week sales</span><strong><?= (int) $weekly['sales'] ?>/<?= (int) $weekly['sales_goal'] ?></strong></div>
</div>

<div class="chart-grid equal" style="margin-bottom:16px">
  <div class="card chart-box">
    <div class="card-head"><h2>Today’s mix</h2></div>
    <div class="pad-form" style="height:220px"><canvas id="home-pie"></canvas></div>
  </div>
  <div class="card chart-box">
    <div class="card-head"><h2>Today by status</h2></div>
    <div class="pad-form" style="height:220px"><canvas id="home-bar"></canvas></div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?>Also tracking</h2></div>
  <div class="pad-form desk-grid stock-split">
    <div>
      <p class="lede" style="margin:0 0 8px">This week</p>
      <?php sales_render_goal_bars($weekly, ['compact' => true, 'force_sales' => true]); ?>
    </div>
    <div>
      <p class="lede" style="margin:0 0 8px">This month</p>
      <?php sales_render_goal_bars($monthly, ['compact' => true, 'force_sales' => true]); ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($due): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('bell', 16) ?>Follow up soon</h2></div>
  <div class="work-list">
    <?php foreach ($due as $lead): ?>
      <a class="work-row" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">
        <div>
          <strong><?= h(trim((string) $lead['business_name']) ?: 'Business') ?></strong>
          <span>Due <?= h(format_date($lead['follow_up_date'])) ?><?= $lead['city'] ? ' · ' . h($lead['city']) : '' ?></span>
        </div>
        <b><?= icon('arrow-right', 16) ?></b>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2><?= icon('clients', 16) ?>Recent leads</h2>
    <a class="btn ghost sm" href="<?= h(url('sales_leads.php')) ?>">All</a>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">No visits logged yet.<?= $clockedIn ? ' Add your first lead.' : ' Clock in first.' ?></p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Business</th><th>Status</th><th>City</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recent as $lead): ?>
            <tr>
              <td><?= h(trim((string) $lead['business_name']) ?: '-') ?></td>
              <td><span class="<?= h(sales_status_pill_class((string) $lead['status'])) ?>"><?= h(sales_status_label((string) $lead['status'])) ?></span></td>
              <td><?= h((string) $lead['city']) ?></td>
              <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
$extra = '';
if ($clockedIn) {
    $payload = json_encode([
        'pieLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
        'pieValues' => [(int) $todayStats['interested'], (int) $todayStats['follow_up'], (int) $todayStats['rejected'], (int) $todayStats['onboarded']],
        'barLabels' => ['Interested', 'Follow up', 'Rejected', 'Onboarded'],
        'barValues' => [(int) $todayStats['interested'], (int) $todayStats['follow_up'], (int) $todayStats['rejected'], (int) $todayStats['onboarded']],
        'color' => brand_color(),
    ], JSON_UNESCAPED_UNICODE);
    $extra = '<script src="' . h(asset('js/chart.umd.min.js')) . '" defer></script><script defer>
(function(){
  function go(){
    if(!window.Chart){ setTimeout(go,40); return; }
    var d=' . $payload . ';
    var pie=document.getElementById("home-pie");
    if(pie){ new Chart(pie,{type:"doughnut",data:{labels:d.pieLabels,datasets:[{data:d.pieValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderWidth:0}]},options:{cutout:"58%",plugins:{legend:{position:"bottom"}},maintainAspectRatio:false}}); }
    var bar=document.getElementById("home-bar");
    if(bar){ new Chart(bar,{type:"bar",data:{labels:d.barLabels,datasets:[{data:d.barValues,backgroundColor:[d.color,"#c4a35a","#b42318","#0f766e"],borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}}); }
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",go); else go();
})();
</script>';
}
sales_layout_end($extra);
