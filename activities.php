<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$kind = trim((string) ($_GET['kind'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
if ($kind !== '' && !isset(company_activity_kinds()[$kind])) {
    $kind = '';
}
$branchesOn = company_branches_enabled();
$branchFilter = 'all';
if ($branchesOn) {
    if (!is_desk_admin($user)) {
        $branchFilter = (int) ($user['branch_id'] ?? 0);
    } else {
        $raw = (string) ($_GET['branch'] ?? 'all');
        if ($raw === '' || $raw === 'all') {
            $branchFilter = 'all';
        } else {
            $branchFilter = (int) $raw;
        }
    }
}
$opts = ['kind' => $kind, 'q' => $q, 'limit' => 120];
if ($branchesOn) {
    $opts['branch_id'] = $branchFilter;
}
$rows = company_activities($opts);
$kinds = company_activity_kinds();
$branchList = $branchesOn ? company_all_branches() : [];

layout_start('Activities', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clock') ?>Activities</h1>
    <p class="lede"><?php if ($branchesOn && is_desk_admin($user)): ?>
      Desk history by branch: pick Head office or a named branch, or show every location.
    <?php elseif ($branchesOn): ?>
      Activity at <?= h(company_branch_label((int) ($user['branch_id'] ?? 0))) ?>.
    <?php else: ?>
      Major events on this desk: documents issued or voided, receipts and supplier payments, mail sent, clients added, settings saved, and planner tasks.
    <?php endif; ?></p>
  </div>
  <?php if ($branchesOn && is_desk_admin($user)): ?>
    <div class="actions">
      <a class="btn ghost" href="<?= h(url('branches.php')) ?>"><?= icon('pin', 16) ?>Branches</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($branchesOn && is_desk_admin($user)): ?>
  <div class="filter-chips" style="margin:0 0 12px">
    <?php
      $chipQs = static function ($branch) use ($kind, $q): string {
          return http_build_query(array_filter([
              'branch' => $branch,
              'kind' => $kind !== '' ? $kind : null,
              'q' => $q !== '' ? $q : null,
          ], static fn ($v) => $v !== null && $v !== ''));
      };
    ?>
    <a class="chip<?= $branchFilter === 'all' ? ' is-on' : '' ?>" href="<?= h(url('activities.php?' . $chipQs('all'))) ?>">Every branch</a>
    <?php foreach ($branchList as $b):
        $bid = (int) ($b['id'] ?? 0);
        $on = $branchFilter !== 'all' && (int) $branchFilter === $bid;
        ?>
      <a class="chip<?= $on ? ' is-on' : '' ?>" href="<?= h(url('activities.php?' . $chipQs((string) $bid))) ?>"><?= h((string) $b['name']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form class="filters" method="get" action="<?= h(url('activities.php')) ?>">
  <?php if ($branchesOn && is_desk_admin($user)): ?>
    <input type="hidden" name="branch" value="<?= h((string) $branchFilter) ?>">
  <?php endif; ?>
  <div>
    <label for="kind">Kind</label>
    <select id="kind" name="kind">
      <option value="">All</option>
      <?php foreach ($kinds as $key => $label): ?>
        <option value="<?= h($key) ?>" <?= $kind === $key ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="flex:1;min-width:min(100%,220px)">
    <label for="q">Find</label>
    <input id="q" name="q" value="<?= h($q) ?>" placeholder="Number, client, or wording">
  </div>
  <button class="btn ghost filter-apply" type="submit">Show</button>
</form>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">Nothing recorded<?= $branchesOn && $branchFilter !== 'all' ? ' for this branch' : '' ?> yet. Issue a quotation or invoice and it will appear here.</p>
  <?php else: ?>
    <div class="activity-list">
      <?php foreach ($rows as $row):
          $href = trim((string) ($row['href'] ?? ''));
          $when = (string) ($row['occurred_at'] ?? '');
          $who = trim((string) ($row['actor_name'] ?? ''));
          $kindLabel = $kinds[$row['kind']] ?? ucfirst((string) $row['kind']);
          $place = $branchesOn ? trim((string) ($row['branch_name'] ?? '')) : '';
          ?>
        <<?= $href !== '' ? 'a href="' . h(url($href)) . '"' : 'div' ?> class="activity-row">
          <span class="activity-kind activity-kind-<?= h((string) $row['kind']) ?>"><?= h($kindLabel) ?></span>
          <div class="activity-main">
            <strong><?= h((string) $row['title']) ?></strong>
            <?php if (trim((string) ($row['detail'] ?? '')) !== ''): ?>
              <span><?= h((string) $row['detail']) ?></span>
            <?php endif; ?>
            <?php if ($place !== ''): ?>
              <span class="activity-branch"><?= icon('pin', 13) ?><?= h($place) ?></span>
            <?php endif; ?>
          </div>
          <time datetime="<?= h($when) ?>"><?= h($when !== '' ? format_date(substr($when, 0, 10)) : '') ?><?= strlen($when) >= 16 ? ' · ' . h(substr($when, 11, 5)) : '' ?><?= $who !== '' ? ' · ' . h($who) : '' ?></time>
        </<?= $href !== '' ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
