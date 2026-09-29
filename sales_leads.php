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
} elseif ($bucket === 'on_test') {
    $opts['on_test'] = true;
}
$leads = sales_leads_query($opts);

sales_layout_start('Leads', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Leads</h1>
    <p class="lede"><?= $bucket === 'pending' ? 'Your open follow-ups. Open one to call the contact and change status.' : 'Status first. Rejected needs why they rejected Vellisys, an explanation, and nature of business.' ?></p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('sales_leads.php?bucket=pending')) ?>"><?= icon('calendar', 16) ?>Open follow-ups</a>
    <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
  </div>
</div>

<div class="filter-chips" style="margin:0 0 12px">
  <a class="chip<?= $status === '' && $bucket === '' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php')) ?>">All</a>
  <?php foreach (sales_statuses() as $k => $label): ?>
    <a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?status=' . urlencode($k))) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a class="chip<?= $bucket === 'on_test' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=on_test')) ?>">On test</a>
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
    <p class="empty">No leads in this view. <a href="<?= h(url('sales_lead_edit.php')) ?>">Log a new lead</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Business</th>
            <th>Status</th>
            <th>Contact</th>
            <th>Phone</th>
            <th>City</th>
            <th>Follow-up</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $lead):
              $testLabel = sales_lead_testing_label($lead);
              $phone = trim((string) ($lead['contact_phone'] ?? ''));
              $tel = $phone !== '' ? phone_tel_href($phone) : '';
              $wa = $phone !== '' ? phone_whatsapp_href($phone, 'Hi, following up about ' . trim((string) ($lead['business_name'] ?? 'your business'))) : '';
              $isOpenFu = (string) ($lead['status'] ?? '') === 'follow_up' && empty($lead['follow_up_done_at']);
              ?>
            <tr>
              <td>
                <a href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>"><strong><?= h(trim((string) $lead['business_name']) ?: '-') ?></strong></a>
                <?php if ($testLabel !== ''): ?>
                  <span class="pill<?= $testLabel === 'Test ended' ? ' bad' : ' warn' ?>"><?= h($testLabel) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="<?= h(sales_status_pill_class((string) $lead['status'])) ?>"><?= h(sales_status_label((string) $lead['status'])) ?></span></td>
              <td><?= h(trim((string) ($lead['contact_name'] ?? '')) ?: '-') ?></td>
              <td>
                <?php if ($tel !== ''): ?>
                  <a href="<?= h($tel) ?>"><?= h($phone) ?></a>
                <?php else: ?>
                  <?= h($phone !== '' ? $phone : '-') ?>
                <?php endif; ?>
              </td>
              <td><?= h((string) $lead['city']) ?></td>
              <td class="date-cell"><?= !empty($lead['follow_up_date']) ? h(sales_format_follow_up($lead)) : '-' ?></td>
              <td class="row-actions">
                <a class="btn<?= $isOpenFu ? '' : ' ghost' ?> sm" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>"><?= $isOpenFu ? 'Open / edit' : 'Open' ?></a>
                <?php if ($tel !== ''): ?>
                  <a class="btn icon-only" href="<?= h($tel) ?>" title="Call" aria-label="Call"><?= icon('phone', 15) ?></a>
                <?php endif; ?>
                <?php if ($wa !== ''): ?>
                  <a class="btn icon-only" href="<?= h($wa) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp"><?= icon('whatsapp', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
