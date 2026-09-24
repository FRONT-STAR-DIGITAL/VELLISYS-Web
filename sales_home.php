<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$error = '';
$clock = sales_today_clock((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'clock_in') {
        $done = sales_clock_in((int) $user['id'], post('location_city'), post('notes'));
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not clock in.');
        } else {
            flash(empty($done['already']) ? 'Clocked in. You can log visits now.' : 'Already clocked in today.');
            redirect(sales_home());
        }
    }
}

[$from, $to] = sales_period_bounds();
$todayStats = sales_stats((int) $user['id'], today(), today());
$monthStats = sales_stats((int) $user['id'], date('Y-m-01'), today());
$target = sales_target_for((int) $user['id']);
$due = sales_followups_due((int) $user['id'], 1);
$recent = sales_leads_query(['agent_id' => (int) $user['id'], 'limit' => 6]);

sales_layout_start('Home', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('home') ?>Field home</h1>
    <p class="lede">Clock in once a day, then log businesses you reach. Follow-ups due today or tomorrow show in the bell.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if (!$clock): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?>Clock in</h2></div>
  <form method="post" class="pad-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clock_in">
    <label for="location_city">Where are you? (city / area)</label>
    <input id="location_city" name="location_city" required placeholder="e.g. Kampala, Nakawa" autocomplete="address-level2">
    <label for="notes">Note (optional)</label>
    <input id="notes" name="notes" placeholder="Territory or route">
    <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Clock in for today</button></div>
  </form>
</div>
<?php else: ?>
<p class="flash" style="margin:0 0 16px"><?= icon('check', 16) ?>Clocked in at <?= h(format_date($clock['day_date'])) ?> · <?= h($clock['location_city']) ?></p>
<?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('clients', 20) ?><span>Reached today</span><strong><?= (int) $todayStats['reach'] ?></strong></div>
  <div class="card stat"><?= icon('heart', 20) ?><span>Interested today</span><strong><?= (int) $todayStats['interested'] ?></strong></div>
  <div class="card stat"><?= icon('calendar', 20) ?><span>Follow-ups due</span><strong><?= count($due) ?></strong></div>
  <div class="card stat"><?= icon('reports', 20) ?><span>Month reach</span><strong><?= (int) $monthStats['reach'] ?></strong></div>
</div>

<?php if ($target):
    $reachGoal = max(1, (int) $target['reach_target']);
    $salesGoal = max(1, (int) $target['sales_target']);
    $reachPct = min(100, (int) round(100 * $monthStats['reach'] / $reachGoal));
    $salesPct = min(100, (int) round(100 * $monthStats['onboarded'] / $salesGoal));
    ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('flag', 16) ?>Your target</h2></div>
  <div class="pad-form">
    <p class="lede">Reach <?= (int) $monthStats['reach'] ?> / <?= (int) $target['reach_target'] ?></p>
    <div class="savings-bar" role="img" aria-label="Reach progress"><span style="width:<?= $reachPct ?>%"></span></div>
    <p class="lede" style="margin-top:12px">Sales (onboarded) <?= (int) $monthStats['onboarded'] ?> / <?= (int) $target['sales_target'] ?></p>
    <div class="savings-bar" role="img" aria-label="Sales progress"><span style="width:<?= $salesPct ?>%"></span></div>
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
    <p class="empty">No visits logged yet.<?= $clock ? ' Add your first lead.' : ' Clock in first.' ?></p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Business</th><th>Status</th><th>City</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recent as $lead): ?>
            <tr>
              <td><?= h(trim((string) $lead['business_name']) ?: '-') ?></td>
              <td><span class="pill"><?= h(sales_status_label((string) $lead['status'])) ?></span></td>
              <td><?= h((string) $lead['city']) ?></td>
              <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
