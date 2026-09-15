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
$from = desk_now()->modify('-30 days')->format('Y-m-d');
$to = desk_now()->format('Y-m-d');
$presence = [];
foreach (platform_company_presence() as $row) {
    $presence[(int) $row['id']] = $row;
}

layout_admin_start('Companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Companies</h1>
    <p class="lede">Who is online, who signed in last, how heavily they use the desk, and when their term renews. Open a company for its own snapshot.</p>
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
          <th>Online</th>
          <th>Last sign-in</th>
          <th>Paid term</th>
          <th>Renews</th>
          <th>They pay</th>
          <th>Users</th>
          <th>Use (30d)</th>
          <th>Onboard</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c):
            $p = $presence[(int) $c['id']] ?? [];
            $onlineN = (int) ($p['online_users'] ?? 0);
            $use = platform_usage_counts((int) $c['id'], $from, $to);
            $onboard = company_onboard_progress($c);
            ?>
          <tr>
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
            <td><?php if ($onlineN > 0): ?><span class="pill"><?= $onlineN ?> online</span><?php else: ?><span class="muted">Off</span><?php endif; ?></td>
            <td class="mono"><?= h(format_when($p['last_login_at'] ?? null)) ?></td>
            <td><?= h(company_term_label($c)) ?></td>
            <td class="<?= company_expiry_state($c) === 'expired' ? 'expiry-expired' : (company_expiry_state($c) === 'soon' ? 'expiry-soon' : '') ?>"><?= !empty($c['expires_at']) ? h(format_date((string) $c['expires_at'])) : h(company_remaining_phrase($c)) ?></td>
            <td class="mono"><?= company_fee_amount($c) > 0 ? h(platform_money_company_fee($c, 'amount')) : '-' ?></td>
            <td class="mono"><?= (int) $c['users'] ?> / <?= (int) company_user_limit($c) ?></td>
            <td class="mono"><?= (int) $use['score'] ?></td>
            <td class="mono"><?= (int) $onboard['done'] ?> / <?= (int) $onboard['total'] ?></td>
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
    <p class="hint">Use in the last 30 days is sheets issued plus desk events. Online means a sign-in was seen in the last <?= (int) platform_online_window_minutes() ?> minutes. Amounts use the currency in Settings.</p>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
