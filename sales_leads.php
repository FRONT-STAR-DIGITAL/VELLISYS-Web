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
if ($bucket === 'followed' || $bucket === 'closed') {
    $opts['follow_bucket'] = 'done';
} elseif ($bucket === 'pending') {
    $opts['follow_bucket'] = 'due';
} elseif ($bucket === 'overdue') {
    $opts['follow_bucket'] = 'overdue';
} elseif ($bucket === 'on_test') {
    $opts['on_test'] = true;
}
$leads = sales_leads_query($opts);
$isOverdueView = $bucket === 'overdue';
$isOpenFollowView = $bucket === 'pending' || $isOverdueView;
$openFollowN = sales_open_followups_count((int) $user['id']);
$overdueFollowN = sales_overdue_followups_count((int) $user['id']);
$pageTitle = $isOverdueView ? 'Overdue follow-ups' : ($bucket === 'pending' ? 'Open follow-ups' : 'Leads');

sales_layout_start($pageTitle, $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon($isOpenFollowView ? 'calendar' : 'clients') ?><?= h($pageTitle) ?><?php if ($isOverdueView && $overdueFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $overdueFollowN ?></span><?php elseif ($bucket === 'pending' && $openFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $openFollowN ?></span><?php endif; ?></h1>
    <p class="lede"><?= $isOverdueView ? 'Past due and not attended. Call these first, then update the status.' : ($isOpenFollowView ? 'High interest clients first. Open one to call, then change status after you follow up.' : 'Status first. Rejected needs why they rejected Vellisys, an explanation, and nature of business.') ?></p>
  </div>
  <div class="actions page-actions">
    <?php if (!$isOpenFollowView): ?>
      <a class="btn ghost" href="<?= h(url('sales_leads.php?bucket=overdue')) ?>"><?= icon('calendar', 16) ?>Overdue<?php if ($overdueFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $overdueFollowN ?></span><?php endif; ?></a>
      <a class="btn ghost" href="<?= h(url('sales_leads.php?bucket=pending')) ?>">Open follow-ups<?php if ($openFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $openFollowN ?></span><?php endif; ?></a>
    <?php else: ?>
      <a class="btn ghost" href="<?= h(url('sales_leads.php')) ?>"><?= icon('clients', 16) ?>All leads</a>
    <?php endif; ?>
    <a class="btn" href="<?= h(url('sales_lead_edit.php')) ?>"><?= icon('plus', 16) ?>New lead</a>
  </div>
</div>

<div class="filter-chips" style="margin:0 0 12px">
  <a class="chip<?= $status === '' && $bucket === '' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php')) ?>">All</a>
  <?php foreach (sales_statuses() as $k => $label): ?>
    <a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?status=' . urlencode($k))) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a class="chip<?= $bucket === 'on_test' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=on_test')) ?>">On test</a>
  <a class="chip<?= $bucket === 'overdue' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=overdue')) ?>">Overdue<?php if ($overdueFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $overdueFollowN ?></span><?php endif; ?></a>
  <a class="chip<?= $bucket === 'pending' ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=pending')) ?>">Open follow-ups<?php if ($openFollowN > 0): ?> <span class="sales-follow-count"><?= (int) $openFollowN ?></span><?php endif; ?></a>
  <a class="chip<?= in_array($bucket, ['followed', 'closed'], true) ? ' is-on' : '' ?>" href="<?= h(url('sales_leads.php?bucket=closed')) ?>">Closed</a>
</div>
<form class="stock-search" method="get" action="<?= h(url('sales_leads.php')) ?>" style="margin-bottom:16px">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
  <?php if ($bucket !== ''): ?><input type="hidden" name="bucket" value="<?= h($bucket) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search business, contact, city, phone" autocomplete="off">
  <button class="btn ghost sm" type="submit"><?= icon('search', 14) ?>Search</button>
</form>

<div class="card">
  <?php if (!$leads): ?>
    <p class="empty"><?= $isOverdueView ? 'No overdue follow-ups right now.' : ($isOpenFollowView ? 'No open follow-ups right now.' : 'No leads in this view.') ?> <a href="<?= h(url('sales_lead_edit.php')) ?>">Log a new lead</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Business</th>
            <?php if ($isOpenFollowView): ?>
              <th>Interest</th>
            <?php else: ?>
              <th>Status</th>
            <?php endif; ?>
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
              $interest = (int) ($lead['interest_rating'] ?? 0);
              $overdue = sales_lead_followup_overdue($lead);
              $rowClass = [];
              if ($overdue) {
                  $rowClass[] = 'sales-fu-overdue';
              }
              if ($isOpenFollowView && $interest >= 4) {
                  $rowClass[] = 'sales-fu-hot';
              }
              ?>
            <tr<?= $rowClass ? ' class="' . h(implode(' ', $rowClass)) . '"' : '' ?>>
              <td>
                <a href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'])) ?>"><strong><?= h(trim((string) $lead['business_name']) ?: '-') ?></strong></a>
                <?php if ($testLabel !== ''): ?>
                  <span class="pill<?= $testLabel === 'Test ended' ? ' bad' : ' warn' ?>"><?= h($testLabel) ?></span>
                <?php endif; ?>
                <?php if ($overdue): ?>
                  <span class="pill bad">Overdue</span>
                <?php endif; ?>
              </td>
              <?php if ($isOpenFollowView): ?>
                <td><span class="<?= h(sales_interest_pill_class($interest)) ?>"><?= h(sales_interest_label($interest)) ?></span></td>
              <?php else: ?>
                <td><span class="<?= h(sales_status_pill_class((string) $lead['status'])) ?>"><?= h(sales_status_label((string) $lead['status'])) ?></span></td>
              <?php endif; ?>
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
