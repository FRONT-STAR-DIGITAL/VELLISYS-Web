<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if (isset($_GET['new']) || isset($_GET['signup'])) {
    $to = 'admin_company_new.php';
    if (!empty($_GET['signup'])) {
        $to .= '?signup=' . (int) $_GET['signup'];
    }
    redirect($to);
}

$companies = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users,
            (SELECT COUNT(*) FROM documents d WHERE d.company_id = c.id) AS docs
     FROM companies c ORDER BY c.id DESC'
);

layout_admin_start('Companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Companies</h1>
    <p class="lede">Create a company from scratch, or onboard one from Sign-ups. Issue the first login, set stationery, then mark the desk live.</p>
  </div>
  <a class="btn" href="<?= h(url('admin_company_new.php')) ?>"><?= icon('plus') ?>New company</a>
</div>

<div class="card">
  <?php if (!$companies): ?>
    <p class="empty">No companies yet. <a href="<?= h(url('admin_company_new.php')) ?>">Create the first one from scratch</a>.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Paid term</th>
          <th>Expiry</th>
          <th>Paid</th>
          <th>Balance</th>
          <th>Users</th>
          <th>Documents</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr>
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
            <td><?= h(company_term_label($c)) ?></td>
            <td class="<?= company_expiry_state($c) === 'expired' ? 'expiry-expired' : (company_expiry_state($c) === 'soon' ? 'expiry-soon' : '') ?>"><?= h(company_remaining_phrase($c)) ?></td>
            <td class="mono"><?= company_fee_paid($c) > 0 ? h(money(company_fee_paid($c), company_fee_currency($c))) : '—' ?></td>
            <td class="mono"><?= company_fee_balance($c) > 0 ? h(money(company_fee_balance($c), company_fee_currency($c))) : '—' ?></td>
            <td class="mono"><?= (int) $c['users'] ?> / <?= (int) company_user_limit($c) ?></td>
            <td class="mono"><?= (int) $c['docs'] ?></td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
                <a class="btn ghost sm" href="<?= h(url('admin_desk.php?id=' . $c['id'])) ?>"><?= icon('desk', 14) ?>Desk</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
