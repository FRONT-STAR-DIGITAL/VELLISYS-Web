<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $target = post('target') ?: 'signup';
    if ($target === 'order') {
        $oid = (int) post('id');
        $order = $oid ? db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$oid]) : null;
        if (!$order) {
            flash('That checkout was not found.', 'err');
            redirect('admin_signups.php');
        }
        if ($action === 'delete') {
            db_exec('DELETE FROM website_orders WHERE id = ?', 'i', [$oid]);
            flash('Checkout for ' . $order['company'] . ' deleted.');
        }
        redirect('admin_signups.php');
    }
    $id = (int) post('id');
    $signup = $id ? db_one('SELECT * FROM signups WHERE id = ?', 'i', [$id]) : null;
    if (!$signup) {
        flash('That sign-up was not found.', 'err');
        redirect('admin_signups.php');
    }
    if ($action === 'onboard') {
        redirect('admin_company_new.php?signup=' . $id);
    }
    if ($action === 'contacted') {
        db_exec("UPDATE signups SET status = 'contacted' WHERE id = ?", 'i', [$id]);
        flash('Marked as contacted. Reach out to ' . $signup['name'] . ' on ' . $signup['phone'] . '.');
    } elseif ($action === 'declined') {
        db_exec("UPDATE signups SET status = 'declined' WHERE id = ?", 'i', [$id]);
        flash($signup['company'] . ' declined.');
    } elseif ($action === 'reopen') {
        db_exec("UPDATE signups SET status = 'new' WHERE id = ?", 'i', [$id]);
        flash($signup['company'] . ' moved back to waiting.');
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM signups WHERE id = ?', 'i', [$id]);
        flash($signup['company'] . ' removed from sign-ups.');
    }
    redirect('admin_signups.php');
}

$open = db_all("SELECT * FROM signups WHERE status IN ('new','contacted') ORDER BY FIELD(status,'new','contacted'), id DESC");
$done = db_all("SELECT * FROM signups WHERE status IN ('onboarded','declined') ORDER BY id DESC LIMIT 40");
$orders = [];
try {
    $orders = db_all('SELECT * FROM website_orders ORDER BY updated_at DESC, id DESC LIMIT 80');
} catch (Throwable $e) {
    $orders = [];
}

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
    ?>
    <div class="actions row-action-stack">
      <?php if (!in_array($s['status'], ['onboarded', 'declined'], true)): ?>
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
      <?php endif; ?>
      <?php if (!empty($s['company_id'])): ?>
        <a class="btn sm" href="<?= h(url('admin_company.php?id=' . (int) $s['company_id'])) ?>">Company</a>
      <?php endif; ?>
      <?php if (in_array($s['status'], ['onboarded', 'declined'], true)): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn ghost sm" name="action" value="reopen"><?= icon('convert', 14) ?>Reopen</button>
      </form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Delete this sign-up from the list? An onboarded company desk is not removed.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="btn ghost sm" name="action" value="delete"><?= icon('trash', 14) ?>Delete</button>
      </form>
    </div>
    <?php
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('letter') ?>Website sign-ups</h1>
    <p class="lede">People register, book a demo, or start checkout from the website. Incomplete forms and failed Pesapal payments land here too. Call them, then onboard the company - or create one from scratch under Companies.</p>
  </div>

<div class="card" style="margin-bottom:24px">
  <h2 style="margin:4px 0 12px">Waiting on you</h2>
  <?php if (!$open): ?>
    <p class="empty">No open sign-ups. New registrations from the website land here.</p>
  <?php else: ?>
    <div class="table-scroll">
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
            <td><span class="pill<?= in_array(($s['source'] ?? ''), ['quote', 'checkout', 'demo'], true) ? ' warn' : '' ?>"><?= h(signup_source_label($s['source'] ?? null)) ?></span></td>
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
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:4px 0 12px">Closed</h2>
  <?php if (!$done): ?>
    <p class="empty">Onboarded and declined sign-ups will list here.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Kind</th>
          <th>Person</th>
          <th>Company</th>
          <th>Email</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $s): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $s['created_at'], 0, 16)) ?></td>
            <td><?= h(signup_source_label($s['source'] ?? null)) ?></td>
            <td><?= h($s['name']) ?></td>
            <td><?= h($s['company']) ?></td>
            <td><?= h($s['email']) ?></td>
            <td><?= $pill($s['status']) ?></td>
            <td class="row-actions"><?php $rowActions($s); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px">
  <h2 style="margin:4px 0 12px">Checkout forms</h2>
  <?php if (!$orders): ?>
    <p class="empty">Package checkouts, drafts and failed Pesapal payments will list here.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Package</th>
          <th>Person</th>
          <th>Company</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <?php
            $plan = pricing_package((string) $o['plan']);
            $st = (string) $o['status'];
            $stClass = match ($st) {
                'paid' => '',
                'failed', 'cancelled' => ' bad',
                'draft', 'pending' => ' warn',
                default => '',
            };
          ?>
          <tr>
            <td class="mono"><?= h(substr((string) $o['updated_at'], 0, 16)) ?></td>
            <td><?= h($plan['name'] ?? (string) $o['plan']) ?></td>
            <td>
              <strong><?= h((string) $o['name']) ?></strong>
              <div class="muted"><a href="mailto:<?= h((string) $o['email']) ?>"><?= h((string) $o['email']) ?></a></div>
            </td>
            <td>
              <?= h((string) $o['company']) ?>
              <?php
                $place = trim((string) ($o['city'] ?? '') . (((string) ($o['city'] ?? '') !== '' && (string) ($o['country'] ?? '') !== '') ? ', ' : '') . (string) ($o['country'] ?? ''));
              ?>
              <?php if ($place !== ''): ?>
                <div class="muted"><?= h($place) ?></div>
              <?php endif; ?>
              <?php if (trim((string) ($o['phone'] ?? '')) !== ''): ?>
                <div class="mono muted"><?= h((string) $o['phone']) ?></div>
              <?php endif; ?>
              <?php if (trim((string) ($o['last_error'] ?? '')) !== ''): ?>
                <div class="muted"><?= h(mb_substr(trim((string) $o['last_error']), 0, 90)) ?></div>
              <?php endif; ?>
            </td>
            <td class="mono"><?= h((string) $o['currency']) ?> <?= h((string) $o['amount']) ?></td>
            <td><span class="pill<?= $stClass ?>"><?= h($st) ?></span></td>
            <td class="row-actions">
              <div class="actions row-action-stack">
                <?php if (!empty($o['signup_id'])): ?>
                  <a class="btn sm" href="<?= h(url('admin_company_new.php?signup=' . (int) $o['signup_id'])) ?>">Onboard</a>
                <?php endif; ?>
                <?php if (trim((string) ($o['email'] ?? '')) !== ''): ?>
                  <a class="btn ghost sm" href="mailto:<?= h((string) $o['email']) ?>"><?= icon('letter', 14) ?>Email</a>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this checkout form?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="target" value="order">
                  <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                  <button class="btn ghost sm" name="action" value="delete"><?= icon('trash', 14) ?>Delete</button>
                </form>
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
