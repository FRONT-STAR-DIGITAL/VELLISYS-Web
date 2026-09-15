<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$period = period_range();
$from = $period['from'] !== '' ? $period['from'] : desk_now()->modify('-30 days')->format('Y-m-d');
$to = $period['to'] !== '' ? $period['to'] : desk_now()->format('Y-m-d');
if ($period['preset'] === 'all' && $period['from'] === '') {
    $from = '2000-01-01';
}

$ms = platform_avg_reload_ms();
$health = platform_health_from_ms($ms);
$presence = platform_company_presence();
$deskUsers = 0;
$namedBranches = 0;
try {
    $u = db_one("SELECT COUNT(*) AS c FROM users WHERE role <> 'platform'");
    $deskUsers = (int) ($u['c'] ?? 0);
} catch (Throwable $e) {
}
try {
    $b = db_one('SELECT COUNT(*) AS c FROM branches');
    $namedBranches = (int) ($b['c'] ?? 0);
} catch (Throwable $e) {
}

$docsPeriod = 0;
$actsPeriod = 0;
try {
    $d = db_one("SELECT COUNT(*) AS c FROM documents WHERE status = 'issued' AND date BETWEEN ? AND ?", 'ss', [$from, $to]);
    $docsPeriod = (int) ($d['c'] ?? 0);
} catch (Throwable $e) {
}
try {
    $a = db_one('SELECT COUNT(*) AS c FROM company_activities WHERE created_at BETWEEN ? AND ?', 'ss', [$from . ' 00:00:00', $to . ' 23:59:59']);
    $actsPeriod = (int) ($a['c'] ?? 0);
} catch (Throwable $e) {
}

$signupsPeriod = 0;
try {
    $s = db_one('SELECT COUNT(*) AS c FROM signups WHERE DATE(created_at) BETWEEN ? AND ?', 'ss', [$from, $to]);
    $signupsPeriod = (int) ($s['c'] ?? 0);
} catch (Throwable $e) {
}

foreach ($presence as &$row) {
    $use = platform_usage_counts((int) $row['id'], $from, $to);
    $row['use_docs'] = $use['documents'];
    $row['use_acts'] = $use['activities'];
    $row['use_score'] = $use['score'];
    $row['desk_health'] = platform_desk_health($row);
}
unset($row);
usort($presence, static fn ($a, $b) => ((int) $b['use_score'] <=> (int) $a['use_score']) ?: strcasecmp((string) $a['name'], (string) $b['name']));

$onlineNow = array_sum(array_map(static fn ($r) => (int) ($r['online_users'] ?? 0), $presence));

layout_admin_start('System', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('settings') ?>System</h1>
    <p class="lede">How the platform is running: speed, people on desks, branches, and which companies use it most. Filter the counts by date.</p>
  </div>
</div>
<?php render_filters('admin_system.php', [], ['live' => true]); ?>

<div class="stats">
  <div class="card stat">
    <?= icon('clock', 20) ?>
    <span>System</span>
    <strong><?= h($health['label']) ?></strong>
    <em class="admin-health is-<?= h($health['key']) ?>"><?= $ms === null ? 'Waiting for samples' : ((int) round($ms) . ' ms average') ?></em>
  </div>
  <div class="card stat"><?= icon('user', 20) ?><span>Users onboard</span><strong><?= $deskUsers ?></strong></div>
  <div class="card stat"><?= icon('pin', 20) ?><span>Named branches</span><strong><?= $namedBranches ?></strong></div>
  <div class="card stat"><?= icon('clients', 20) ?><span>Active now</span><strong><?= $onlineNow ?></strong></div>
</div>
<div class="stats">
  <div class="card stat"><?= icon('file', 20) ?><span>Sheets in range</span><strong><?= $docsPeriod ?></strong></div>
  <div class="card stat"><?= icon('clock', 20) ?><span>Desk events in range</span><strong><?= $actsPeriod ?></strong></div>
  <div class="card stat"><?= icon('letter', 20) ?><span>Sign-ups in range</span><strong><?= $signupsPeriod ?></strong></div>
  <div class="card stat"><?= icon('building', 20) ?><span>Companies</span><strong><?= count($presence) ?></strong></div>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('building', 16) ?>Companies, last sign-in, and use</h2></div>
  <?php if (!$presence): ?>
    <p class="empty">No companies yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Company</th>
            <th>Online</th>
            <th>Last sign-in</th>
            <th>Last seen</th>
            <th>People</th>
            <th>Branches</th>
            <th>Desk</th>
            <th class="right">Use in range</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($presence as $c): ?>
            <tr>
              <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
              <td>
                <?php if ((int) ($c['online_users'] ?? 0) > 0): ?>
                  <span class="pill"><?= (int) $c['online_users'] ?> online</span>
                <?php else: ?>
                  <span class="muted">Off</span>
                <?php endif; ?>
              </td>
              <td class="mono"><?= h(format_when($c['last_login_at'] ?? null)) ?></td>
              <td class="mono"><?= h(format_when($c['last_seen_at'] ?? null)) ?></td>
              <td class="mono"><?= (int) ($c['users'] ?? 0) ?></td>
              <td class="mono"><?= (int) ($c['branches'] ?? 0) ?></td>
              <td><span class="admin-health is-<?= h($c['desk_health']['key']) ?>"><?= h($c['desk_health']['label']) ?></span></td>
              <td class="right mono"><?= (int) $c['use_score'] ?> <span class="muted">(<?= (int) $c['use_docs'] ?> sheets)</span></td>
              <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<p class="hint">Heavier use is sheets issued plus desk events in the selected dates. Online means a sign-in was seen in the last <?= (int) platform_online_window_minutes() ?> minutes.</p>
<?php layout_end();