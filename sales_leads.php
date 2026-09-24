<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$status = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$bucket = (string) ($_GET['bucket'] ?? '');
$opts = ['agent_id' => (int) $user['id']];
if ($status !== '' && isset(sales_statuses()[$status])) {
    $opts['status'] = $status;
}
if ($q !== '') {
    $opts['q'] = $q;
}
if ($bucket === 'followed') {
    $opts['follow_bucket'] = 'done';
} elseif ($bucket === 'pending') {
    $opts['follow_bucket'] = 'due';
}
$leads = sales_leads_query($opts);

sales_layout_start('Leads', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Leads</h1>
    <p class="lede">Status first. Rejected needs only a reason. Everything else is optional, then save.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
  </div>
</div>

<div class="filter-chips" style="margin:0 0 12px">
  <a class="chip<?= $status === '' && $bucket === '' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php')) ?>">All</a>
  <?php foreach (sales_statuses() as $k => $label): ?>
    <a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?status=' . urlencode($k))) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a class="chip<?= $bucket === 'pending' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=pending')) ?>">Follow-ups open</a>
  <a class="chip<?= $bucket === 'followed' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=followed')) ?>">Followed up</a>
</div>
<form class="stock-search" method="get" action="<?= h(url('sales_leads.php')) ?>" style="margin-bottom:16px">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
  <?php if ($bucket !== ''): ?><input type="hidden" name="bucket" value="<?= h($bucket) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search business, contact, city" autocomplete="off">
  <button class="btn ghost sm" type="submit"><?= icon('search', 14) ?>Search</button>
</form>

<div class="card">
  <?php if (!$leads): ?>
    <p class="empty">No leads in this view.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Business</th>
            <th>Status</th>
            <th>Contact</th>
            <th>City</th>
            <th>Follow-up</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td><?= h(trim((string) $lead['business_name']) ?: '—') ?></td>
              <td><span class="pill"><?= h(sales_status_label((string) $lead['status'])) ?></span></td>
              <td><?= h(trim((string) $lead['contact_name'] . ' ' . $lead['contact_phone'])) ?></td>
              <td><?= h((string) $lead['city']) ?></td>
              <td class="date-cell"><?= !empty($lead['follow_up_date']) ? h(format_date($lead['follow_up_date'])) : '—' ?></td>
              <td class="row-actions"><a class="btn ghost sm" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
