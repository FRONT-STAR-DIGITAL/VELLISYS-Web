<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$kind = trim((string) ($_GET['kind'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
if ($kind !== '' && !isset(company_activity_kinds()[$kind])) {
    $kind = '';
}
$rows = company_activities(['kind' => $kind, 'q' => $q, 'limit' => 120]);
$kinds = company_activity_kinds();

layout_start('Activities', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clock') ?>Activities</h1>
    <p class="lede">Major events on this desk: documents issued or voided, receipts and supplier payments, mail sent, clients added, settings saved, and planner tasks. This is the company history, not a page-by-page visitor log.</p>
  </div>
</div>

<form class="filters" method="get" action="<?= h(url('activities.php')) ?>">
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
    <p class="empty">Nothing recorded yet. Issue a quotation or invoice and it will appear here.</p>
  <?php else: ?>
    <div class="activity-list">
      <?php foreach ($rows as $row):
          $href = trim((string) ($row['href'] ?? ''));
          $when = (string) ($row['occurred_at'] ?? '');
          $who = trim((string) ($row['actor_name'] ?? ''));
          $kindLabel = $kinds[$row['kind']] ?? ucfirst((string) $row['kind']);
          ?>
        <<?= $href !== '' ? 'a href="' . h(url($href)) . '"' : 'div' ?> class="activity-row">
          <span class="activity-kind activity-kind-<?= h((string) $row['kind']) ?>"><?= h($kindLabel) ?></span>
          <div class="activity-main">
            <strong><?= h((string) $row['title']) ?></strong>
            <?php if (trim((string) ($row['detail'] ?? '')) !== ''): ?>
              <span><?= h((string) $row['detail']) ?></span>
            <?php endif; ?>
          </div>
          <time datetime="<?= h($when) ?>"><?= h($when !== '' ? format_date(substr($when, 0, 10)) : '') ?><?= strlen($when) >= 16 ? ' · ' . h(substr($when, 11, 5)) : '' ?><?= $who !== '' ? ' · ' . h($who) : '' ?></time>
        </<?= $href !== '' ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
