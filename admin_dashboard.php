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

$branches = 0;
$branchesPrevMonth = 0;
try {
    $b = db_one('SELECT COUNT(*) AS c FROM branches');
    $branches = (int) ($b['c'] ?? 0);
    $b2 = db_one('SELECT COUNT(*) AS c FROM branches WHERE DATE(created_at) <= ?', 's', [$prevMonthEnd]);
    $branchesPrevMonth = (int) ($b2['c'] ?? $branches);
} catch (Throwable $e) {
}

$todayUsd = 0.0;
$yesterdayUsd = 0.0;
$allUsd = 0.0;
$prevMonthUsd = 0.0;
try {
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger');
    $allUsd = (float) ($row['t'] ?? 0);
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) = ?', 's', [$today]);
    $todayUsd = (float) ($row['t'] ?? 0);
    $row = db_one('SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) = ?', 's', [$yesterday]);
    $yesterdayUsd = (float) ($row['t'] ?? 0);
    $row = db_one(
        'SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) BETWEEN ? AND ?',
        'ss',
        [$prevMonthStart, $prevMonthEnd]
    );
    $prevMonthUsd = (float) ($row['t'] ?? 0);
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
    $rows = db_all(
        'SELECT DATE(occurred_at) d, SUM(amount_usd) t FROM platform_fee_ledger
         WHERE DATE(occurred_at) BETWEEN ? AND ? GROUP BY DATE(occurred_at)',
        'ss',
        [$days[0], $today]
    );
    foreach ($rows as $r) {
        $d = (string) ($r['d'] ?? '');
        if (isset($collectionsSeries[$d])) {
            $collectionsSeries[$d] = (float) ($r['t'] ?? 0);
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
    $row = db_one(
        'SELECT COALESCE(SUM(amount_usd),0) AS t FROM platform_fee_ledger WHERE DATE(occurred_at) BETWEEN ? AND ?',
        'ss',
        [$prevWeekStart, $prevWeekEnd]
    );
    $prevWeekCollections = (float) ($row['t'] ?? 0);
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

$branchRows = [];
try {
    $branchRows = db_all(
        'SELECT b.id, b.name, b.city, c.name AS company_name, c.id AS company_id
         FROM branches b
         LEFT JOIN companies c ON c.id = b.company_id
         ORDER BY b.id DESC LIMIT 6'
    );
} catch (Throwable $e) {
    $branchRows = [];
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
        return ['tone' => 'flat', 'text' => '— 0 ' . $vs];
    }
    if ($previous <= 0 && $current <= 0) {
        return ['tone' => 'flat', 'text' => '— 0% ' . $vs];
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
    return ['tone' => 'flat', 'text' => '— 0% ' . $vs];
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
$liveTrend = $trend((float) $live, (float) $livePrev, 'vs. yesterday', true);
$peopleTrend = $trend((float) $deskUsers, (float) $deskUsersYesterday, 'vs. yesterday', true);
$branchTrend = $trend((float) $branches, (float) $branchesPrevMonth, 'vs. last month', true);
$todayTrend = $trend($todayUsd, $yesterdayUsd, 'vs. yesterday');
$allTrend = $trend($allUsd, max(0.0, $allUsd - $prevMonthUsd), 'vs. last month');
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
  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('clock', 16) ?><span>Reload speed</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $ms === null ? '—' : ((int) round($ms) . ' ms') ?></strong>
        <em class="admin-trend is-<?= h($reloadTrend['tone']) ?>"><?= h($reloadTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside">
        <?= $spark($reloadSpark ?: [($ms ?? 0), ($ms ?? 0)]) ?>
        <span class="admin-chip is-<?= h($health['key']) ?>"><?= h($health['label']) ?></span>
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
    <div class="admin-metric-label"><?= icon('pin', 16) ?><span>Named branches</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= $branches ?></strong>
        <em class="admin-trend is-<?= h($branchTrend['tone']) ?>"><?= h($branchTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= icon('building', 28) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('bank', 16) ?><span>Taken in today</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= h(platform_money($todayUsd, 'USD')) ?></strong>
        <em class="admin-trend is-<?= h($todayTrend['tone']) ?>"><?= h($todayTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $bars(array_values($collectionsSeries)) ?></div>
    </div>
  </article>

  <article class="admin-metric card">
    <div class="admin-metric-label"><?= icon('invoice', 16) ?><span>Taken in, all time</span></div>
    <div class="admin-metric-row">
      <div>
        <strong><?= h(platform_money($allUsd, 'USD')) ?></strong>
        <em class="admin-trend is-<?= h($allTrend['tone']) ?>"><?= h($allTrend['text']) ?></em>
      </div>
      <div class="admin-metric-aside"><?= $bars([max(0, $allUsd - $prevMonthUsd), $allUsd * 0.7, $allUsd * 0.85, $allUsd]) ?></div>
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
          <strong><?= h(platform_money($weekCollections, 'USD')) ?></strong>
          <em class="admin-trend is-<?= h($weekCollTrend['tone']) ?>"><?= h(trim($weekCollTrend['text']) ?: '—') ?></em>
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
          <em class="admin-trend is-<?= h($weekDocTrend['tone']) ?>"><?= h(trim($weekDocTrend['text']) ?: '—') ?></em>
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
                  —
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
                <td class="mono"><?= $ts ? h(date('g:i A', $ts)) : '—' ?></td>
                <td><span class="admin-act-type"><?= icon($activityIcon($kind), 14) ?><?= h($activityType($kind)) ?></span></td>
                <td><?= h((string) ($row['title'] ?? $row['detail'] ?? '—')) ?></td>
                <td class="admin-dash-hide-sm"><?= h((string) ($row['company_name'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="card admin-dash-panel admin-dash-branches">
    <div class="card-head">
      <h2><?= icon('pin', 16) ?>Branches</h2>
      <a class="btn ghost sm" href="<?= h(url('admin_locations.php')) ?>">View all</a>
    </div>
    <?php if (!$branchRows): ?>
      <div class="admin-dash-empty">
        <?= icon('pin', 36) ?>
        <p>No branches yet</p>
        <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>"><?= icon('plus', 14) ?>Add branch</a>
      </div>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($branchRows as $b): ?>
          <a class="work-row" href="<?= h(url('admin_company.php?id=' . (int) ($b['company_id'] ?? 0))) ?>">
            <div>
              <strong><?= h((string) $b['name']) ?></strong>
              <span><?= h(trim((string) ($b['company_name'] ?? '') . ($b['city'] ? ' · ' . $b['city'] : ''))) ?></span>
            </div>
            <b><?= icon('arrow-right', 16) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="pad-form" style="padding-top:0">
        <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>"><?= icon('plus', 14) ?>Add branch</a>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php
$chartPayload = json_encode([
    'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $days),
    'collections' => array_map(static fn ($v) => round(platform_convert((float) $v, 'USD', $ccy), 2), array_values($collectionsSeries)),
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
  var soft="color-mix(in srgb, "+brand+" 35%, #c5d4ff)";
  var c1=document.getElementById("admin-dash-collections");
  if(c1){
    new Chart(c1,{type:"line",data:{labels:d.labels,datasets:[{label:"Taken in",data:d.collections,borderColor:brand,backgroundColor:"transparent",tension:.35,pointRadius:3,pointBackgroundColor:brand,borderWidth:2.5}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return (d.currency||"")+" "+Number(ctx.raw||0).toLocaleString();}}}},scales:{x:{grid:{display:false},ticks:{color:"#6b7280",font:{size:11}}},y:{beginAtZero:true,grid:{color:"rgba(8,20,58,.06)"},ticks:{color:"#6b7280",font:{size:11},callback:function(v){return v>=1000?(v/1000)+"k":v;}}}}});
  }
  var c2=document.getElementById("admin-dash-docs");
  if(c2){
    new Chart(c2,{type:"bar",data:{labels:d.labels,datasets:[{label:"Documents",data:d.docs,backgroundColor:brand,borderRadius:6,barPercentage:.55},{label:"Events",data:d.acts,backgroundColor:soft,borderRadius:6,barPercentage:.55}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{stacked:false,grid:{display:false},ticks:{color:"#6b7280",font:{size:11}}},y:{beginAtZero:true,grid:{color:"rgba(8,20,58,.06)"},ticks:{color:"#6b7280",precision:0,font:{size:11}}}}});
  }
})();
</script>'
);
