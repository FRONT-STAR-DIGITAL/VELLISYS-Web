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

$filterCountry = trim((string) ($_GET['country'] ?? ''));
$presence = platform_company_presence();
$rows = [];
foreach ($presence as $row) {
    $use = platform_usage_counts((int) $row['id'], $from, $to);
    $row['use_docs'] = $use['documents'];
    $row['use_acts'] = $use['activities'];
    $row['use_score'] = $use['score'];
    $row['desk_health'] = platform_desk_health($row);
    $row['place_perf'] = location_perf_label($row);
    $rows[] = $row;
}

$placed = array_values(array_filter($rows, static fn ($r) => company_has_admin_location($r)));
$missing = count($rows) - count($placed);
$countries = [];
foreach ($placed as $r) {
    $c = company_loc($r, 'country') !== '' ? company_loc($r, 'country') : 'No country set';
    $countries[$c] = true;
}
$countryNames = array_keys($countries);
sort($countryNames);

$view = $filterCountry !== ''
    ? array_values(array_filter($rows, static fn ($r) => company_loc($r, 'country') === $filterCountry))
    : $rows;

$group = static function (array $list, string $level): array {
    $out = [];
    foreach ($list as $r) {
        $key = location_place_key($r, $level);
        if (!isset($out[$key])) {
            $out[$key] = [
                'label' => $key,
                'companies' => 0,
                'live' => 0,
                'users' => 0,
                'use' => 0,
                'well' => 0,
                'weak' => 0,
            ];
        }
        $out[$key]['companies']++;
        if (($r['status'] ?? '') === 'live') {
            $out[$key]['live']++;
        }
        $out[$key]['users'] += (int) ($r['users'] ?? 0);
        $out[$key]['use'] += (int) ($r['use_score'] ?? 0);
        $perf = $r['place_perf']['key'] ?? 'slow';
        if ($perf === 'fast') {
            $out[$key]['well']++;
        } elseif ($perf === 'slow') {
            $out[$key]['weak']++;
        }
    }
    $vals = array_values($out);
    usort($vals, static fn ($a, $b) => ($b['use'] <=> $a['use']) ?: ($b['companies'] <=> $a['companies']) ?: strcasecmp($a['label'], $b['label']));
    return $vals;
};

$byCountry = $group($placed, 'country');
$ugRows = array_values(array_filter($placed, static fn ($r) => company_loc($r, 'country') === 'Uganda'));
$byUgRegion = $group($ugRows, 'region');
$byCity = $group($filterCountry !== '' ? array_values(array_filter($placed, static fn ($r) => company_loc($r, 'country') === $filterCountry)) : $placed, 'city');

$well = count(array_filter($view, static fn ($r) => ($r['place_perf']['key'] ?? '') === 'fast'));
$weak = count(array_filter($view, static fn ($r) => ($r['place_perf']['key'] ?? '') === 'slow'));

layout_admin_start('Locations', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('pin') ?>Locations</h1>
    <p class="lede">Where desks sit, how many you have in each country, and where use is strong or quiet. This map is Vellisys only - companies never see it.</p>
  </div>
</div>
<?php render_filters('admin_locations.php', $filterCountry !== '' ? ['country' => $filterCountry] : [], ['live' => true]); ?>

<div class="stats">
  <div class="card stat"><?= icon('building', 20) ?><span>Companies placed</span><strong><?= count($placed) ?></strong></div>
  <div class="card stat"><?= icon('globe', 20) ?><span>Countries</span><strong><?= count($countryNames) ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Performing well</span><strong><?= $well ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Needs attention</span><strong><?= $weak ?></strong></div>
</div>
<p class="hint" style="margin:-8px 0 16px"><?= $missing ?> <?= $missing === 1 ? 'company has' : 'companies have' ?> no country or city yet. Open a company and fill Location. Use in range is sheets issued plus desk events.</p>

<?php if ($countryNames): ?>
  <div class="filter-chips" style="margin:0 0 16px;display:flex;flex-wrap:wrap;gap:8px">
    <a class="btn sm<?= $filterCountry === '' ? '' : ' ghost' ?>" href="<?= h(url('admin_locations.php')) ?>">All countries</a>
    <?php foreach ($countryNames as $name): ?>
      <a class="btn sm<?= $filterCountry === $name ? '' : ' ghost' ?>" href="<?= h(url('admin_locations.php?country=' . rawurlencode($name))) ?>"><?= h($name) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('globe', 16) ?>By country</h2></div>
  <?php if (!$byCountry): ?>
    <p class="empty">No locations saved yet. Open a company and set country, region and city.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Country</th>
            <th class="right">Companies</th>
            <th class="right">Live</th>
            <th class="right">People</th>
            <th class="right">Use in range</th>
            <th class="right">Performing well</th>
            <th class="right">Needs attention</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byCountry as $g): ?>
            <tr>
              <td>
                <?php if ($g['label'] === 'No country set'): ?>
                  <strong><?= h($g['label']) ?></strong>
                <?php else: ?>
                  <a href="<?= h(url('admin_locations.php?country=' . rawurlencode($g['label']))) ?>"><strong><?= h($g['label']) ?></strong></a>
                <?php endif; ?>
              </td>
              <td class="right mono"><?= (int) $g['companies'] ?></td>
              <td class="right mono"><?= (int) $g['live'] ?></td>
              <td class="right mono"><?= (int) $g['users'] ?></td>
              <td class="right mono"><?= (int) $g['use'] ?></td>
              <td class="right mono"><?= (int) $g['well'] ?></td>
              <td class="right mono"><?= (int) $g['weak'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($ugRows): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('pin', 16) ?>Uganda by region</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Region</th>
          <th class="right">Companies</th>
          <th class="right">Live</th>
          <th class="right">People</th>
          <th class="right">Use in range</th>
          <th class="right">Performing well</th>
          <th class="right">Needs attention</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($byUgRegion as $g): ?>
          <tr>
            <td><strong><?= h($g['label']) ?></strong></td>
            <td class="right mono"><?= (int) $g['companies'] ?></td>
            <td class="right mono"><?= (int) $g['live'] ?></td>
            <td class="right mono"><?= (int) $g['users'] ?></td>
            <td class="right mono"><?= (int) $g['use'] ?></td>
            <td class="right mono"><?= (int) $g['well'] ?></td>
            <td class="right mono"><?= (int) $g['weak'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint" style="padding:0 22px 16px">Central, Eastern, Northern and Western. Set the region on each Uganda company so this split is complete.</p>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('building', 16) ?>City / district</h2></div>
  <?php if (!$byCity): ?>
    <p class="empty">No cities saved yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Place</th>
            <th class="right">Companies</th>
            <th class="right">Live</th>
            <th class="right">People</th>
            <th class="right">Use in range</th>
            <th class="right">Performing well</th>
            <th class="right">Needs attention</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byCity as $g): ?>
            <tr>
              <td><?= h($g['label']) ?></td>
              <td class="right mono"><?= (int) $g['companies'] ?></td>
              <td class="right mono"><?= (int) $g['live'] ?></td>
              <td class="right mono"><?= (int) $g['users'] ?></td>
              <td class="right mono"><?= (int) $g['use'] ?></td>
              <td class="right mono"><?= (int) $g['well'] ?></td>
              <td class="right mono"><?= (int) $g['weak'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>Each company</h2></div>
  <?php if (!$view): ?>
    <p class="empty">No companies in this filter.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Company</th>
            <th>Office / street</th>
            <th>City</th>
            <th>Region</th>
            <th>Country</th>
            <th>Place</th>
            <th class="right">Use</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($view as $c): ?>
            <tr>
              <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
              <td><?php $line = trim(company_loc($c, 'office') . (company_loc($c, 'office') && company_loc($c, 'street') ? ', ' : '') . company_loc($c, 'street')); echo $line !== '' ? h($line) : '<span class="muted">-</span>'; ?></td>
              <td><?= company_loc($c, 'city') !== '' ? h(company_loc($c, 'city')) : '<span class="muted">-</span>' ?></td>
              <td><?= company_loc($c, 'region') !== '' ? h(company_loc($c, 'region')) : '<span class="muted">-</span>' ?></td>
              <td><?= company_loc($c, 'country') !== '' ? h(company_loc($c, 'country')) : '<span class="muted">-</span>' ?></td>
              <td><span class="admin-health is-<?= h($c['place_perf']['key']) ?>"><?= h($c['place_perf']['label']) ?></span></td>
              <td class="right mono"><?= (int) $c['use_score'] ?></td>
              <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<p class="hint">Performing well means the desk is active now, or healthy with solid use in the selected dates. Needs attention means idle or no sign-in yet.</p>
<?php layout_end();