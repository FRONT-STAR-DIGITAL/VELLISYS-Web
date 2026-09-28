<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
sales_require_clock_in();

$companies = sales_testing_companies_for_agent((int) $user['id']);
$credsFlash = $_SESSION['testing_creds'] ?? null;
if (is_array($credsFlash)) {
    unset($_SESSION['testing_creds']);
}

sales_layout_start('My companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>My companies</h1>
    <p class="lede">Testing desks you opened for interested clients. Each runs for 2 weeks. Hand them FirstWord@vellisys.com and password Folio2026, then promote to full onboard when they are ready.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sales_company_new.php')) ?>"><?= icon('plus') ?>New test desk</a>
  </div>
</div>

<?php if (is_array($credsFlash) && !empty($credsFlash['email'])): ?>
<div class="card pad-form sales-creds-card" style="margin-bottom:16px">
  <h2 style="margin-top:0">Hand these to the client</h2>
  <p class="lede" style="margin-top:0"><?= h((string) ($credsFlash['name'] ?? 'Testing desk')) ?> - testing until <?= h(format_date((string) ($credsFlash['expires_at'] ?? ''))) ?>.</p>
  <p><strong>Username:</strong> <code data-copy><?= h((string) $credsFlash['email']) ?></code></p>
  <p><strong>Password:</strong> <code data-copy><?= h((string) $credsFlash['password']) ?></code></p>
  <p class="hint">Save a screenshot or write them down. You can open them again from this company.</p>
  <div class="actions">
    <a class="btn" href="<?= h(url('sales_company.php?id=' . (int) ($credsFlash['company_id'] ?? 0))) ?>">Open company</a>
    <a class="btn ghost" href="<?= h(url('sales_desk.php?id=' . (int) ($credsFlash['company_id'] ?? 0))) ?>">Open desk</a>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <?php if (!$companies): ?>
    <p class="empty">No testing companies yet. When a lead wants to try Vellisys first, <a href="<?= h(url('sales_company_new.php')) ?>">open a 2-week test desk</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Business</th>
          <th>Contact login</th>
          <th>Ends</th>
          <th>Time left</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c):
            $expired = company_testing_expired($c);
            ?>
          <tr>
            <td>
              <a href="<?= h(url('sales_company.php?id=' . (int) $c['id'])) ?>"><strong><?= h((string) $c['name']) ?></strong></a>
              <?php if ($expired): ?><span class="pill bad">Expired</span><?php else: ?><span class="pill warn">Testing</span><?php endif; ?>
            </td>
            <td class="mono"><?= h((string) ($c['desk_email'] ?? '-')) ?></td>
            <td class="mono"><?= !empty($c['testing_expires_at']) ? h(format_date((string) $c['testing_expires_at'])) : '-' ?></td>
            <td><?= h(company_testing_remaining_label($c)) ?></td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm" href="<?= h(url('sales_company.php?id=' . (int) $c['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
                <?php if (!$expired): ?>
                  <a class="btn ghost sm" href="<?= h(url('sales_desk.php?id=' . (int) $c['id'])) ?>"><?= icon('desk', 14) ?>Desk</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="hint">Open shows credentials and details. Desk opens their trial desk so you can walk them through it. Use Leave desk when you finish.</p>
  <?php endif; ?>
</div>
<?php sales_layout_end(); ?>
