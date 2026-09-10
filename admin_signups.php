<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) post('id');
    $signup = $id ? db_one('SELECT * FROM signups WHERE id = ?', 'i', [$id]) : null;
    if (!$signup) {
        flash('That sign-up was not found.', 'err');
        redirect('admin_signups.php');
    }
    $action = post('action');
    if ($action === 'onboard') {
        redirect('admin_companies.php?new=1&signup=' . $id);
    }
    if ($action === 'contacted') {
        db_exec("UPDATE signups SET status = 'contacted' WHERE id = ?", 'i', [$id]);
        flash('Marked as contacted. Reach out to ' . $signup['name'] . ' on ' . $signup['phone'] . '.');
    } elseif ($action === 'declined') {
        db_exec("UPDATE signups SET status = 'declined' WHERE id = ?", 'i', [$id]);
        flash($signup['company'] . ' declined.');
    }
    redirect('admin_signups.php');
}

$open = db_all("SELECT * FROM signups WHERE status IN ('new','contacted') ORDER BY FIELD(status,'new','contacted'), id DESC");
$done = db_all("SELECT * FROM signups WHERE status IN ('onboarded','declined') ORDER BY id DESC LIMIT 40");

layout_admin_start('Sign-ups', $user);

$pill = static function (string $status): string {
    $class = match ($status) {
        'new' => ' warn',
        'declined' => ' bad',
        'onboarded' => '',
        default => '',
    };
    return '<span class="pill' . $class . '">' . h($status) . '</span>';
};

$rowActions = static function (array $s): void {
    if (in_array($s['status'], ['onboarded', 'declined'], true)) {
        if (!empty($s['company_id'])) {
            echo '<a class="btn sm" href="' . h(url('admin_company.php?id=' . (int) $s['company_id'])) . '">Company</a>';
        }
        return;
    }
    ?>
    <div class="actions">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn sm" name="action" value="onboard"><?= icon('building', 14) ?>Onboard</button>
      </form>
      <?php if ($s['status'] === 'new'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn ghost sm" name="action" value="contacted"><?= icon('letter', 14) ?>Reached out</button>
      </form>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn ghost sm" name="action" value="declined"><?= icon('ban', 14) ?>Decline</button>
      </form>
    </div>
    <?php
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('letter') ?>Website sign-ups</h1>
    <p class="lede">People register or request a quote from the website. Call them, then onboard the company and issue a desk login.</p>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <h2 style="margin:4px 0 12px">Waiting on you</h2>
  <?php if (!$open): ?>
    <p class="empty">No open sign-ups. New registrations from the website land here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Kind</th>
          <th>Person</th>
          <th>Company</th>
          <th>Reach them</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open as $s): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $s['created_at'], 0, 16)) ?></td>
            <td><?= ($s['source'] ?? '') === 'quote' ? '<span class="pill warn">Quote</span>' : '<span class="pill">Sign-up</span>' ?></td>
            <td><strong><?= h($s['name']) ?></strong></td>
            <td>
              <?= h($s['company']) ?>
              <?php if (trim((string) ($s['note'] ?? '')) !== ''): ?>
                <div class="muted"><?= h(mb_substr(trim((string) $s['note']), 0, 80)) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a>
              <?php if ($s['phone'] !== ''): ?><div class="mono"><?= h($s['phone']) ?></div><?php endif; ?>
            </td>
            <td><?= $pill($s['status']) ?></td>
            <td class="row-actions"><?php $rowActions($s); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:4px 0 12px">Closed</h2>
  <?php if (!$done): ?>
    <p class="empty">Onboarded and declined sign-ups will list here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Kind</th>
          <th>Person</th>
          <th>Company</th>
          <th>Email</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $s): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $s['created_at'], 0, 16)) ?></td>
            <td><?= ($s['source'] ?? '') === 'quote' ? 'Quote' : 'Sign-up' ?></td>
            <td><?= h($s['name']) ?></td>
            <td><?= h($s['company']) ?></td>
            <td><?= h($s['email']) ?></td>
            <td><?= $pill($s['status']) ?></td>
            <td class="row-actions"><?php $rowActions($s); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
