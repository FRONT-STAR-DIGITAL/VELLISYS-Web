<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$ms = platform_avg_reload_ms();
$health = platform_health_from_ms($ms);
$online = platform_online_users();
$companies = db_all('SELECT id, status FROM companies');
$live = count(array_filter($companies, static fn ($c) => ($c['status'] ?? '') === 'live'));
$deskUsers = 0;
$branches = 0;
try {
    $u = db_one("SELECT COUNT(*) AS c FROM users WHERE role <> 'platform'");
    $deskUsers = (int) ($u['c'] ?? 0);
} catch (Throwable $e) {
}
try {
    $b = db_one('SELECT COUNT(*) AS c FROM branches');
    $branches = (int) ($b['c'] ?? 0);
} catch (Throwable $e) {
}
$today = desk_now()->format('Y-m-d');
$todayUsd = 0.0;
$allUsd = 0.0;
try {
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger');
    $allUsd = (float) ($row['t'] ?? 0);
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) = ?', 's', [$today]);
    $todayUsd = (float) ($row['t'] ?? 0);
} catch (Throwable $e) {
}

layout_admin_start('Dashboard', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Dashboard</h1>
    <p class="lede">A snapshot of Vellisys right now: reload speed, who is on a desk, and what the system has taken in.</p>
  </div>
</div>

<div class="stats">
  <div class="card stat">
    <?= icon('clock', 20) ?>
    <span>Reload speed</span>
    <strong><?= $ms === null ? 'Measuring' : ((int) round($ms) . ' ms') ?></strong>
    <em class="admin-health is-<?= h($health['key']) ?>"><?= h($health['label']) ?></em>
  </div>
  <div class="card stat">
    <?= icon('user', 20) ?>
    <span>Active now</span>
    <strong><?= count($online) ?></strong>
  </div>
  <div class="card stat">
    <?= icon('building', 20) ?>
    <span>Live desks</span>
    <strong><?= $live ?></strong>
  </div>
  <div class="card stat">
    <?= icon('clients', 20) ?>
    <span>People on desks</span>
    <strong><?= $deskUsers ?></strong>
  </div>
</div>
<div class="stats">
  <div class="card stat">
    <?= icon('pin', 20) ?>
    <span>Named branches</span>
    <strong><?= $branches ?></strong>
  </div>
  <div class="card stat">
    <?= icon('bank', 20) ?>
    <span>Taken in today</span>
    <strong><?= h(platform_money($todayUsd, 'USD')) ?></strong>
  </div>
  <div class="card stat">
    <?= icon('invoice', 20) ?>
    <span>Taken in, all time</span>
    <strong><?= h(platform_money($allUsd, 'USD')) ?></strong>
  </div>
  <div class="card stat">
    <?= icon('check', 20) ?>
    <span>Companies</span>
    <strong><?= count($companies) ?></strong>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('user', 16) ?>On a desk now</h2></div>
  <?php if (!$online): ?>
    <p class="empty">Nobody is signed in on a company desk at the moment.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Person</th>
            <th>Company</th>
            <th>Last seen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($online as $row): ?>
            <tr>
              <td><strong><?= h((string) $row['name']) ?></strong><div class="muted"><?= h((string) $row['email']) ?></div></td>
              <td>
                <?php if ((int) ($row['company_id'] ?? 0) > 0): ?>
                  <a href="<?= h(url('admin_company.php?id=' . (int) $row['company_id'])) ?>"><?= h((string) ($row['company_name'] ?? 'Desk')) ?></a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="mono"><?= h(format_when($row['last_seen_at'] ?? null)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<p class="hint">Figures use the currency set under Settings. Reload speed is the average of recent page loads on this portal and the desks.</p>
<?php layout_end();