<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$companies = sales_testing_companies_for_agent((int) $user['id']);
// Map company → lead for back-links.
$leadByCompany = [];
if ($companies) {
    $ids = array_map(static fn ($c) => (int) $c['id'], $companies);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $rows = db_all(
        "SELECT id, company_id, business_name FROM sales_leads
         WHERE agent_id = ? AND deleted_at IS NULL AND company_id IN ($in)",
        'i' . $types,
        array_merge([(int) $user['id']], $ids)
    );
    foreach ($rows as $row) {
        $leadByCompany[(int) $row['company_id']] = $row;
    }
}
$credsFlash = $_SESSION['testing_creds'] ?? null;
if (is_array($credsFlash)) {
    unset($_SESSION['testing_creds']);
}

$activeN = 0;
$signedInN = 0;
foreach ($companies as $c) {
    $act = sales_testing_activity($c);
    if ($act['online']) {
        $activeN++;
    }
    if ($act['last_login'] !== '') {
        $signedInN++;
    }
}

sales_layout_start('On Testing', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>On Testing</h1>
    <p class="lede">Your trial desks. Last login and active status show who is actually trying the app — focus follow-ups there.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sales_leads.php?status=interested')) ?>"><?= icon('clients', 14) ?>Interested leads</a>
  </div>
</div>

<?php if (is_array($credsFlash) && !empty($credsFlash['email'])): ?>
<div class="card pad-form sales-creds-card" style="margin-bottom:16px">
  <h2 style="margin-top:0">Client login</h2>
  <p class="lede" style="margin-top:0"><?= h((string) ($credsFlash['name'] ?? 'Testing desk')) ?> · ends <?= h(format_date((string) ($credsFlash['expires_at'] ?? ''))) ?>.</p>
  <p><strong>Username:</strong> <code data-copy><?= h((string) $credsFlash['email']) ?></code></p>
  <p><strong>Password:</strong> <code data-copy><?= h((string) $credsFlash['password']) ?></code></p>
  <div class="actions">
    <?php
      $cid = (int) ($credsFlash['company_id'] ?? 0);
      $lead = $leadByCompany[$cid] ?? null;
    ?>
    <?php if ($lead): ?>
      <a class="btn" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing')) ?>">Back to lead</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= h(url('sales_desk.php?id=' . $cid)) ?>">Open desk</a>
  </div>
</div>
<?php endif; ?>

<?php if ($companies): ?>
<div class="stats" style="margin-bottom:16px">
  <div class="card stat"><?= icon('building', 20) ?><span>On testing</span><strong><?= count($companies) ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Active now</span><strong><?= $activeN ?></strong></div>
  <div class="card stat"><?= icon('user', 20) ?><span>Have signed in</span><strong><?= $signedInN ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>No sign-in yet</span><strong><?= max(0, count($companies) - $signedInN) ?></strong></div>
</div>
<?php endif; ?>

<div class="card">
  <?php if (!$companies): ?>
    <p class="empty">No testing desks yet. Open an <a href="<?= h(url('sales_leads.php?status=interested')) ?>">interested lead</a> to start one.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Business</th>
          <th>Active</th>
          <th>Last login</th>
          <th>Lead</th>
          <th>Contact login</th>
          <th>Ends</th>
          <th>Time left</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c):
            $expired = company_testing_expired($c);
            $cid = (int) $c['id'];
            $lead = $leadByCompany[$cid] ?? null;
            $act = sales_testing_activity($c);
            $pillClass = $act['online'] ? '' : ($act['status_key'] === 'slow' ? ' warn' : '');
            ?>
          <tr>
            <td>
              <a href="<?= h(url('sales_company.php?id=' . $cid)) ?>"><strong><?= h((string) $c['name']) ?></strong></a>
              <?php if ($expired): ?><span class="pill bad">Expired</span><?php else: ?><span class="pill warn">Testing</span><?php endif; ?>
            </td>
            <td>
              <?php if ($act['online']): ?>
                <span class="pill">Active now</span>
              <?php else: ?>
                <span class="pill<?= h($pillClass) ?>"><?= h($act['status_label']) ?></span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= h($act['last_login_label']) ?></td>
            <td>
              <?php if ($lead): ?>
                <a href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing')) ?>"><?= h((string) ($lead['business_name'] ?: 'Lead #' . (int) $lead['id'])) ?></a>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= h((string) ($c['desk_email'] ?? '-')) ?></td>
            <td class="mono"><?= !empty($c['testing_expires_at']) ? h(format_date((string) $c['testing_expires_at'])) : '-' ?></td>
            <td><?= h(company_testing_remaining_label($c)) ?></td>
            <td class="row-actions">
              <div class="actions">
                <?php if ($lead): ?>
                  <a class="btn sm" href="<?= h(url('sales_lead_edit.php?id=' . (int) $lead['id'] . '#lead-testing')) ?>">Lead</a>
                <?php endif; ?>
                <?php if (!$expired): ?>
                  <a class="btn ghost sm" href="<?= h(url('sales_desk.php?id=' . $cid)) ?>"><?= icon('desk', 14) ?>Desk</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="hint">Sorted by most recent login. Active now means someone on that desk was seen in the last <?= (int) (function_exists('platform_online_window_minutes') ? platform_online_window_minutes() : 5) ?> minutes.</p>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
