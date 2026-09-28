<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$now = desk_now();
$today = $now->format('Y-m-d');
$yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
$monthStart = $now->format('Y-m-01');
$prevMonthStart = (clone $now)->modify('first day of last month')->format('Y-m-01');
$prevMonthEnd = (clone $now)->modify('last day of last month')->format('Y-m-d');

$ms = platform_avg_reload_ms();
$health = platform_health_from_ms($ms);
$online = platform_online_users();
$onlineCount = count($online);
$deviceStats = function_exists('platform_device_install_stats')
    ? platform_device_install_stats()
    : ['installs' => 0, 'live' => 0, 'users' => 0];
$deviceInstalls = (int) ($deviceStats['installs'] ?? 0);
$deviceLive = (int) ($deviceStats['live'] ?? 0);

$companies = [];
try {
    $companies = db_all('SELECT id, name, status, created_at FROM companies ORDER BY name');
} catch (Throwable $e) {
    $companies = [];
}
$live = count(array_filter($companies, static fn ($c) => ($c['status'] ?? '') === 'live'));
$livePrev = count(array_filter(
    $companies,
    static function ($c) use ($yesterday): bool {
        if (($c['status'] ?? '') !== 'live') {
            return false;
        }
        $created = substr((string) ($c['created_at'] ?? ''), 0, 10);
        return $created === '' || $created <= $yesterday;
    }
));
$companyCount = count($companies);
$companiesPrevMonth = 0;
foreach ($companies as $c) {
    $created = substr((string) ($c['created_at'] ?? ''), 0, 10);
    if ($created !== '' && $created <= $prevMonthEnd) {
        $companiesPrevMonth++;
    }
}

$deskUsers = 0;
$deskUsersYesterday = 0;
try {
    $u = db_one("SELECT COUNT(*) AS c FROM users WHERE role <> 'platform'");
    $deskUsers = (int) ($u['c'] ?? 0);
    $u2 = db_one(
        "SELECT COUNT(*) AS c FROM users WHERE role <> 'platform' AND DATE(COALESCE(created_at, first_login_at, last_login_at)) <= ?",
        's',
        [$yesterday]
    );
    $deskUsersYesterday = (int) ($u2['c'] ?? $deskUsers);
} catch (Throwable $e) {
}

$todayTaken = 0.0;
$yesterdayTaken = 0.0;
$allTaken = 0.0;
$prevMonthTaken = 0.0;
try {
    $allTaken = platform_fee_sum();
    $todayTaken = platform_fee_sum($today, $today);
    $yesterdayTaken = platform_fee_sum($yesterday, $yesterday);
    $prevMonthTaken = platform_fee_sum($prevMonthStart, $prevMonthEnd);
} catch (Throwable $e) {
}

$reloadSpark = [];
$reloadPrev = null;
try {
    $samples = db_all('SELECT ms FROM platform_perf_samples ORDER BY id DESC LIMIT 24');
    $vals = array_map(static fn ($r) => (float) ($r['ms'] ?? 0), array_reverse($samples));
    $reloadSpark = $vals;
    if (count($vals) >= 8) {
        $half = (int) floor(count($vals) / 2);
        $older = array_slice($vals, 0, $half);
        $reloadPrev = $older ? array_sum($older) / count($older) : null;
    }
} catch (Throwable $e) {
}

$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = (clone $now)->modify("-{$i} days")->format('Y-m-d');
}
$collectionsSeries = array_fill_keys($days, 0.0);
$docsSeries = array_fill_keys($days, 0);
$actsSeries = array_fill_keys($days, 0);
try {
    foreach (platform_fee_series($days[0], $today, 'day') as $d => $t) {
        if (isset($collectionsSeries[$d])) {
            $collectionsSeries[$d] = (float) $t;
        }
    }
} catch (Throwable $e) {
}
try {
    $rows = db_all(
        "SELECT date d, COUNT(*) t FROM documents WHERE status = 'issued' AND date BETWEEN ? AND ? GROUP BY date",
        'ss',
        [$days[0], $today]
    );
    foreach ($rows as $r) {
        $d = (string) ($r['d'] ?? '');
        if (isset($docsSeries[$d])) {
            $docsSeries[$d] = (int) ($r['t'] ?? 0);
        }
    }
} catch (Throwable $e) {
}
try {
    $rows = db_all(
        'SELECT DATE(occurred_at) d, COUNT(*) t FROM company_activities
         WHERE DATE(occurred_at) BETWEEN ? AND ? GROUP BY DATE(occurred_at)',
        'ss',
        [$days[0], $today]
    );
    foreach ($rows as $r) {
        $d = (string) ($r['d'] ?? '');
        if (isset($actsSeries[$d])) {
            $actsSeries[$d] = (int) ($r['t'] ?? 0);
        }
    }
} catch (Throwable $e) {
}

$weekCollections = array_sum($collectionsSeries);
$weekDocs = array_sum($docsSeries);
$prevWeekStart = (clone $now)->modify('-13 days')->format('Y-m-d');
$prevWeekEnd = (clone $now)->modify('-7 days')->format('Y-m-d');
$prevWeekCollections = 0.0;
$prevWeekDocs = 0;
try {
    $prevWeekCollections = platform_fee_sum($prevWeekStart, $prevWeekEnd);
} catch (Throwable $e) {
}
try {
    $row = db_one(
        "SELECT COUNT(*) AS c FROM documents WHERE status = 'issued' AND date BETWEEN ? AND ?",
        'ss',
        [$prevWeekStart, $prevWeekEnd]
    );
    $prevWeekDocs = (int) ($row['c'] ?? 0);
} catch (Throwable $e) {
}

$recent = [];
try {
    $recent = db_all(
        'SELECT a.*, c.name AS company_name
         FROM company_activities a
         LEFT JOIN companies c ON c.id = a.company_id
         ORDER BY a.occurred_at DESC, a.id DESC
         LIMIT 8'
    );
} catch (Throwable $e) {
    try {
        $recent = db_all(
            "SELECT d.id, d.kind, d.number, d.created_at AS occurred_at, 'document' AS kind_act, c.name AS company_name,
                    CONCAT(UPPER(SUBSTRING(d.kind,1,1)), SUBSTRING(d.kind,2), ' #', d.number) AS title
             FROM documents d
             LEFT JOIN companies c ON c.id = d.company_id
             WHERE d.status = 'issued'
             ORDER BY d.id DESC LIMIT 8"
        );
        foreach ($recent as &$r) {
            $r['kind'] = 'document';
            $r['title'] = (string) ($r['title'] ?? 'Document');
        }
        unset($r);
    } catch (Throwable $e2) {
        $recent = [];
    }
}

$trend = static function (float $current, float $previous, string $vs, bool $absolute = false): array {
    if ($absolute) {
        $delta = (int) round($current - $previous);
        if ($delta > 0) {
            return ['tone' => 'up', 'text' => '↑ ' . $delta . ' ' . $vs];
        }
        if ($delta < 0) {
            return ['tone' => 'down', 'text' => '↓ ' . abs($delta) . ' ' . $vs];
        }
        return ['tone' => 'flat', 'text' => '- 0 ' . $vs];
    }
    if ($previous <= 0 && $current <= 0) {
        return ['tone' => 'flat', 'text' => '- 0% ' . $vs];
    }
    if ($previous <= 0) {
        return ['tone' => 'up', 'text' => '↑ 100% ' . $vs];
    }
    $pct = (int) round((($current - $previous) / $previous) * 100);
    if ($pct > 0) {
        return ['tone' => 'up', 'text' => '↑ ' . $pct . '% ' . $vs];
    }
    if ($pct < 0) {
        return ['tone' => 'down', 'text' => '↓ ' . abs($pct) . '% ' . $vs];
    }
    return ['tone' => 'flat', 'text' => '- 0% ' . $vs];
};

$spark = static function (array $values, string $stroke = ''): string {
    $values = array_values(array_map('floatval', $values));
    if (count($values) < 2) {
        $values = $values ? [$values[0], $values[0]] : [0, 0];
    }
    $min = min($values);
    $max = max($values);
    $span = max(0.0001, $max - $min);
    $w = 72;
    $h = 28;
    $n = count($values);
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $n === 1 ? 0 : ($i / ($n - 1)) * $w;
        $y = $h - (($v - $min) / $span) * ($h - 4) - 2;
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $color = $stroke !== '' ? $stroke : 'var(--brand)';
    return '<svg class="admin-spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true"><polyline fill="none" stroke="' . h($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . h(implode(' ', $pts)) . '"/></svg>';
};

$bars = static function (array $values): string {
    $values = array_values(array_map('floatval', $values));
    if (!$values) {
        $values = [0, 0, 0, 0, 0, 0, 0];
    }
    $max = max(1.0, max($values));
    $html = '<span class="admin-minibars" aria-hidden="true">';
    foreach ($values as $v) {
        $pct = max(8, (int) round(($v / $max) * 100));
        $html .= '<i style="height:' . $pct . '%"></i>';
    }
    return $html . '</span>';
};

$reloadTrend = $trend((float) ($ms ?? 0), (float) ($reloadPrev ?? ($ms ?? 0)), 'vs. last hour');
// Faster reload is an improvement → invert tone for display when down
if ($reloadTrend['tone'] === 'down') {
    $reloadTrend['tone'] = 'up';
} elseif ($reloadTrend['tone'] === 'up' && ($reloadPrev ?? 0) > 0) {
    $reloadTrend['tone'] = 'down';
}
$activeTrend = $trend((float) $onlineCount, (float) $onlineCount, 'vs. last hour');
$deviceTrend = [
    'tone' => $deviceLive > 0 ? 'up' : 'flat',
    'text' => $deviceLive . ' live now',
];
$liveTrend = $trend((float) $live, (float) $livePrev, 'vs. yesterday', true);
$peopleTrend = $trend((float) $deskUsers, (float) $deskUsersYesterday, 'vs. yesterday', true);
$todayTrend = $trend($todayTaken, $yesterdayTaken, 'vs. yesterday');
$allTrend = $trend($allTaken, max(0.0, $allTaken - $prevMonthTaken), 'vs. last month');
$companyTrend = $trend((float) $companyCount, (float) $companiesPrevMonth, 'vs. last month', true);
$weekCollTrend = $trend($weekCollections, $prevWeekCollections, '');
$weekDocTrend = $trend((float) $weekDocs, (float) $prevWeekDocs, '');

$ccy = platform_currency();
$dateLabel = $now->format('D, j M Y');

$activityIcon = static function (string $kind): string {
    return match ($kind) {
        'payment' => 'bank',
        'email' => 'mail',
        'client' => 'clients',
        'branch' => 'pin',
        'settings' => 'settings',
        'planner' => 'calendar',
        'stock' => 'box',
        default => 'file',
    };
};
$activityType = static function (string $kind): string {
    return match ($kind) {
        'payment' => 'Payment',
        'email' => 'Email',
        'client' => 'Client',
        'branch' => 'Branch',
        'settings' => 'Settings',
        'planner' => 'Planner',
        'stock' => 'Stock',
        default => 'Document',
    };
};

layout_admin_start('Dashboard', $user);
?>
<div class="page-head admin-dash-head">
  <div>
    <h1><?= icon('reports') ?>Dashboard</h1>
  </div>
  <div class="admin-dash-date" title="<?= h($now->format('Y-m-d')) ?>">
    <?= icon('calendar', 16) ?>
    <span>Today, <?= h($dateLabel) ?></span>
  </div>
</div>

<div class="admin-metric-grid">
  <article class="admin-metric card" data-speed-sensor="reload">
    <div class="admin-metric-label">
      <?= icon('clock', 16) ?><span>Reload speed</span>
      <button type="button" class="admin-metric-refresh" data-refresh-reload title="Refresh reload speed" aria-label="Refresh reload speed"><?= icon('refresh', 14) ?></button>
    </div>
    <div class="admin-metric-row">
      <div>
        <strong data-reload-ms><?= $ms === null ? '-' : ((int) round($ms) . ' ms') ?></strong>
        <em class="admin-trend is-<?= h($reloadTrend['tone']) ?>" data-reload-trend><?= h($reloadTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside">
        <span data-reload-spark><?= $spark($reloadSpark ?: [($ms ?? 0), ($ms ?? 0)]) ?></span>
        <span class="admin-chip is-<?= h($health['key']) ?>" data-reload-chip><?= h($health['label']) ?></span>
      </div>
    </div>
  </article>

  <article class="admin-metric card" data-speed-sensor="internet">
    <div class="admin-metric-label">
      <?= icon('globe', 16) ?><span>Internet speed</span>
      <button type="button" class="admin-metric-refresh" data-refresh-internet title="Refresh internet speed" aria-label="Refresh internet speed"><?= icon('refresh', 14) ?></button>
    </div>
    <div class="admin-metric-row">
      <div>
        <strong data-net-mbps>-</strong>
        <em class="admin-trend is-flat" data-net-trend>Tap refresh to measure</em>
      </div>
      <div class="admin-metric-aside">
        <span class="admin-chip is-healthy" data-net-chip>Normal</span>
      </div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('user', 16) ?><span>Active now</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $onlineCount ?></strong>
        <em class="admin-trend is-<?= h($activeTrend['tone']) ?>"><?= h($activeTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside">
        <?= icon('clients', 28) ?>
        <span class="admin-chip"><?= $onlineCount ? 'Online' : 'No users' ?></span>
      </div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('phone', 16) ?><span>App devices</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $deviceInstalls ?></strong>
        <em class="admin-trend is-<?= h($deviceTrend['tone']) ?>"><?= h($deviceTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside">
        <?= icon('bell', 28) ?>
        <span class="admin-chip<?= $deviceLive > 0 ? ' is-healthy' : '' ?>"><?= $deviceInstalls < 1 ? 'No installs' : ($deviceLive . ' live') ?></span>
      </div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('building', 16) ?><span>Live desks</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $live ?></strong>
        <em class="admin-trend is-<?= h($liveTrend['tone']) ?>"><?= h($liveTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $spark([(float) max(0, $live - 1), (float) $live]) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('clients', 16) ?><span>People on desks</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $deskUsers ?></strong>
        <em class="admin-trend is-<?= h($peopleTrend['tone']) ?>"><?= h($peopleTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $bars([$deskUsersYesterday, max(0, $deskUsers - 1), $deskUsers, $deskUsers, max(0, $deskUsers - 1), $deskUsers, $deskUsers]) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('bank', 16) ?><span>Taken in today</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= h(money($todayTaken, $ccy)) ?></strong>
        <em class="admin-trend is-<?= h($todayTrend['tone']) ?>"><?= h($todayTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $bars(array_values($collectionsSeries)) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('invoice', 16) ?><span>Taken in, all time</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= h(money($allTaken, $ccy)) ?></strong>
        <em class="admin-trend is-<?= h($allTrend['tone']) ?>"><?= h($allTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $bars([max(0, $allTaken - $prevMonthTaken), $allTaken * 0.7, $allTaken * 0.85, $allTaken]) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('check', 16) ?><span>Companies</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $companyCount ?></strong>
        <em class="admin-trend is-<?= h($companyTrend['tone']) ?>"><?= h($companyTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= icon('building', 28) ?></div>
    </div>
  </article>
</div>

<div class="chart-grid equal admin-dash-charts">
  <div class="card chart-box admin-dash-chart">
    <div class="card-head admin-dash-chart-head">
      <div>
        <h2><?= icon('reports', 16) ?>Collections / Taken in</h2>
        <div class="admin-dash-chart-meta">
          <strong><?= h(money($weekCollections, $ccy)) ?></strong>
          <em class="admin-trend is-<?= h($weekCollTrend['tone']) ?>"><?= h(trim($weekCollTrend['text']) ?: '-') ?></em>
        </div>
      </div>
      <span class="admin-range-chip">Last 7 days</span>
    </div>
    <div class="admin-dash-canvas-wrap">
      <canvas id="admin-dash-collections" height="220"></canvas>
    </div>
  </div>
  <div class="card chart-box admin-dash-chart admin-dash-docs-chart">
    <div class="card-head admin-dash-chart-head">
      <div>
        <h2><?= icon('file', 16) ?>Document activity</h2>
        <div class="admin-dash-chart-meta">
          <strong><?= (int) $weekDocs ?></strong>
          <em class="admin-trend is-<?= h($weekDocTrend['tone']) ?>"><?= h(trim($weekDocTrend['text']) ?: '-') ?></em>
        </div>
      </div>
      <span class="admin-range-chip">Last 7 days</span>
    </div>
    <div class="admin-dash-canvas-wrap">
      <canvas id="admin-dash-docs" height="220"></canvas>
    </div>
  </div>
</div>

<div class="admin-dash-panels">
  <section class="card admin-dash-panel">
    <div class="card-head">
      <h2><?= icon('user', 16) ?>On a desk now</h2>
      <a class="btn ghost sm" href="<?= h(url('admin_system.php')) ?>">View all</a>
    </div>
    <?php if (!$online): ?>
      <div class="admin-dash-empty">
        <?= icon('clients', 36) ?>
        <p>Nobody signed in</p>
      </div>
    <?php else: ?>
      <div class="work-list admin-dash-online">
        <?php foreach (array_slice($online, 0, 6) as $row): ?>
          <div class="work-row">
            <div>
              <strong><?= h((string) $row['name']) ?></strong>
              <span>
                <?php if ((int) ($row['company_id'] ?? 0) > 0): ?>
                  <a href="<?= h(url('admin_company.php?id=' . (int) $row['company_id'])) ?>"><?= h((string) ($row['company_name'] ?? 'Desk')) ?></a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </span>
            </div>
            <b class="mono"><?= h(format_when($row['last_seen_at'] ?? null)) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card admin-dash-panel admin-dash-activity">
    <div class="card-head">
      <h2><?= icon('clock', 16) ?>Recent activity</h2>
      <a class="btn ghost sm" href="<?= h(url('admin_system.php')) ?>">View all</a>
    </div>
    <?php if (!$recent): ?>
      <div class="admin-dash-empty"><p>No activity yet</p></div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid admin-dash-activity-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Type</th>
              <th>Details</th>
              <th class="admin-dash-hide-sm">Company</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $row):
                $kind = (string) ($row['kind'] ?? 'document');
                $when = (string) ($row['occurred_at'] ?? $row['created_at'] ?? '');
                $ts = $when !== '' ? strtotime($when) : false;
                ?>
              <tr>
                <td class="mono"><?= $ts ? h(date('g:i A', $ts)) : '-' ?></td>
                <td><span class="admin-act-type"><?= icon($activityIcon($kind), 14) ?><?= h($activityType($kind)) ?></span></td>
                <td><?= h((string) ($row['title'] ?? $row['detail'] ?? '-')) ?></td>
                <td class="admin-dash-hide-sm"><?= h((string) ($row['company_name'] ?? '-')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php
$chartPayload = json_encode([
    'dates' => $days,
    'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $days),
    'fullLabels' => array_map(static fn ($d) => date('j M Y', strtotime($d)), $days),
    'collections' => array_map(static fn ($v) => round((float) $v, 2), array_values($collectionsSeries)),
    'docs' => array_values($docsSeries),
    'acts' => array_values($actsSeries),
    'color' => brand_color(),
    'currency' => $ccy,
], JSON_UNESCAPED_UNICODE);

layout_end(
    '<script src="' . h(asset('js/chart.umd.min.js')) . '"></script><script>
(function(){
  var d=' . $chartPayload . ';
  if(!window.Chart) return;
  var brand=d.color||"#1E4EFF";
  var soft="rgba(30,78,255,.18)";
  try {
    var tmp=document.createElement("canvas").getContext("2d");
    if(tmp){
      soft=tmp.createLinearGradient(0,0,0,220);
      soft.addColorStop(0,"rgba(30,78,255,.22)");
      soft.addColorStop(1,"rgba(30,78,255,0)");
    }
  } catch(e) {}
  var c1=document.getElementById("admin-dash-collections");
  if(c1){
    new Chart(c1,{
      type:"line",
      data:{
        labels:d.labels,
        datasets:[{
          label:"Taken in",
          data:d.collections,
          borderColor:brand,
          backgroundColor:soft,
          fill:true,
          tension:.35,
          pointRadius:4,
          pointHoverRadius:6,
          pointBackgroundColor:"#fff",
          pointBorderColor:brand,
          pointBorderWidth:2,
          borderWidth:2.5,
          spanGaps:true
        }]
      },
      options:{
        maintainAspectRatio:false,
        interaction:{mode:"index",intersect:false},
        plugins:{
          legend:{display:false},
          tooltip:{
            callbacks:{
              title:function(items){
                var i=items[0]&&items[0].dataIndex;
                return (d.fullLabels&&d.fullLabels[i])|| (items[0]&&items[0].label)||"";
              },
              label:function(ctx){
                return (d.currency||"")+" "+Number(ctx.raw||0).toLocaleString();
              }
            }
          }
        },
        scales:{
          x:{
            type:"category",
            grid:{display:false},
            ticks:{color:"#6b7280",font:{size:11},maxRotation:0}
          },
          y:{
            beginAtZero:true,
            grid:{color:"rgba(8,20,58,.06)"},
            ticks:{
              color:"#6b7280",
              font:{size:11},
              callback:function(v){
                if(v>=1000000) return (v/1000000)+"m";
                if(v>=1000) return (v/1000)+"k";
                return v;
              }
            }
          }
        }
      }
    });
  }
  var barSoft="color-mix(in srgb, "+brand+" 35%, #c5d4ff)";
  var c2=document.getElementById("admin-dash-docs");
  if(c2){
    new Chart(c2,{type:"bar",data:{labels:d.labels,datasets:[{label:"Documents",data:d.docs,backgroundColor:brand,borderRadius:6,barPercentage:.55},{label:"Events",data:d.acts,backgroundColor:barSoft,borderRadius:6,barPercentage:.55}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{stacked:false,grid:{display:false},ticks:{color:"#6b7280",font:{size:11}}},y:{beginAtZero:true,grid:{color:"rgba(8,20,58,.06)"},ticks:{color:"#6b7280",precision:0,font:{size:11}}}}});
  }
})();
</script>
<script>
(function(){
  var ping = ' . json_encode(url('ping.php')) . ';
  var reloadBtn = document.querySelector("[data-refresh-reload]");
  var netBtn = document.querySelector("[data-refresh-internet]");
  function setBusy(btn, on){
    if(!btn) return;
    btn.disabled = !!on;
    btn.classList.toggle("is-busy", !!on);
  }
  function chipClass(key){
    return "admin-chip is-" + (key || "healthy");
  }
  function healthFromMbps(mbps){
    if(mbps >= 25) return {key:"fast", label:"Fast"};
    if(mbps >= 5) return {key:"healthy", label:"Normal"};
    return {key:"slow", label:"Slow"};
  }
  function fmtMbps(mbps){
    if(!(mbps > 0)) return "-";
    if(mbps >= 100) return Math.round(mbps) + " Mbps";
    if(mbps >= 10) return mbps.toFixed(1) + " Mbps";
    return mbps.toFixed(2) + " Mbps";
  }
  function sparkSvg(values){
    values = (values || []).map(Number);
    if(values.length < 2) values = values.length ? [values[0], values[0]] : [0, 0];
    var min = Math.min.apply(null, values);
    var max = Math.max.apply(null, values);
    var span = Math.max(0.0001, max - min);
    var w = 72, h = 28, n = values.length, pts = [];
    for(var i = 0; i < n; i++){
      var x = n === 1 ? 0 : (i / (n - 1)) * w;
      var y = h - ((values[i] - min) / span) * (h - 4) - 2;
      pts.push(x.toFixed(1) + "," + y.toFixed(1));
    }
    return \'<svg class="admin-spark" viewBox="0 0 \' + w + \' \' + h + \'" width="\' + w + \'" height="\' + h + \'" aria-hidden="true"><polyline fill="none" stroke="var(--brand)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="\' + pts.join(" ") + \'"/></svg>\';
  }
  async function refreshReload(){
    setBusy(reloadBtn, true);
    var msEl = document.querySelector("[data-reload-ms]");
    var chipEl = document.querySelector("[data-reload-chip]");
    var trendEl = document.querySelector("[data-reload-trend]");
    var sparkEl = document.querySelector("[data-reload-spark]");
    try {
      var t0 = performance.now();
      await fetch(ping + "?action=echo&_=" + Date.now(), {credentials:"same-origin", cache:"no-store"});
      var sample = Math.round(performance.now() - t0);
      if(sample > 0){
        await fetch(ping + "?ms=" + encodeURIComponent(sample) + "&path=" + encodeURIComponent("manual-reload"), {credentials:"same-origin", cache:"no-store"});
      }
      var res = await fetch(ping + "?action=stats&_=" + Date.now(), {credentials:"same-origin", cache:"no-store"});
      var data = await res.json();
      var reload = (data && data.reload) || {};
      if(msEl) msEl.textContent = reload.label || (sample ? sample + " ms" : "-");
      if(chipEl && reload.health){
        chipEl.textContent = reload.health.label || "Healthy";
        chipEl.className = chipClass(reload.health.key);
      }
      if(trendEl){
        trendEl.textContent = sample ? ("Just now · " + sample + " ms sample") : "Updated";
        trendEl.className = "admin-trend is-flat";
      }
      if(sparkEl && reload.spark && reload.spark.length){
        sparkEl.innerHTML = sparkSvg(reload.spark);
      }
    } catch (e) {
      if(trendEl){
        trendEl.textContent = "Could not refresh";
        trendEl.className = "admin-trend is-down";
      }
    } finally {
      setBusy(reloadBtn, false);
    }
  }
  async function refreshInternet(){
    setBusy(netBtn, true);
    var mbpsEl = document.querySelector("[data-net-mbps]");
    var chipEl = document.querySelector("[data-net-chip]");
    var trendEl = document.querySelector("[data-net-trend]");
    if(trendEl){
      trendEl.textContent = "Measuring…";
      trendEl.className = "admin-trend is-flat";
    }
    try {
      var bytes = 262144;
      var url = ping + "?action=blob&bytes=" + bytes + "&_=" + Date.now();
      var t0 = performance.now();
      var res = await fetch(url, {credentials:"same-origin", cache:"no-store"});
      var buf = await res.arrayBuffer();
      var ms = Math.max(1, performance.now() - t0);
      var mbps = (buf.byteLength * 8) / (ms / 1000) / 1e6;
      var health = healthFromMbps(mbps);
      if(mbpsEl) mbpsEl.textContent = fmtMbps(mbps);
      if(chipEl){
        chipEl.textContent = health.label;
        chipEl.className = chipClass(health.key);
      }
      if(trendEl){
        trendEl.textContent = "Round trip " + Math.round(ms) + " ms";
        trendEl.className = "admin-trend is-" + (health.key === "slow" ? "down" : (health.key === "fast" ? "up" : "flat"));
      }
    } catch (e) {
      if(mbpsEl) mbpsEl.textContent = "-";
      if(trendEl){
        trendEl.textContent = "Could not measure";
        trendEl.className = "admin-trend is-down";
      }
      if(chipEl){
        chipEl.textContent = "Slow";
        chipEl.className = chipClass("slow");
      }
    } finally {
      setBusy(netBtn, false);
    }
  }
  if(reloadBtn) reloadBtn.addEventListener("click", refreshReload);
  if(netBtn) netBtn.addEventListener("click", refreshInternet);
  // Auto-measure internet once the dashboard is idle.
  if(netBtn && "requestIdleCallback" in window){
    requestIdleCallback(function(){ refreshInternet(); }, {timeout:2500});
  } else if(netBtn){
    setTimeout(refreshInternet, 900);
  }
})();
</script>'
);
