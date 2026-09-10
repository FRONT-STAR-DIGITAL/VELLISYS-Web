<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'notice') {
    csrf_check();
    $id = (int) post('id');
    $company = $id ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
    if (!$company) {
        flash('Company not found.', 'err');
        redirect('admin_reports.php');
    }
    $state = company_expiry_state($company);
    if (!in_array($state, ['soon', 'expired'], true)) {
        flash('Notices are for desks one month from expiry, or already lapsed.', 'err');
        redirect('admin_reports.php?preview=' . $id);
    }
    $result = send_renewal_notice($company, $user);
    if (!empty($result['error'])) {
        flash($result['error'], 'err');
        redirect('admin_reports.php?preview=' . $id);
    }
    $to = $result['contact']['email'] ?? '';
    if ($result['ok']) {
        flash('Renewal notice sent to ' . $to . '.');
    } else {
        flash('The notice was prepared and queued for ' . $to . '. Mail was not accepted on this host - send it from a live server, or copy the text below.');
    }
    redirect('admin_reports.php?preview=' . $id);
}

$companies = db_all('SELECT * FROM companies ORDER BY (expires_at IS NULL), expires_at, name');
$soon = [];
$expired = [];
$later = [];
$unset = [];
foreach ($companies as $c) {
    $state = company_expiry_state($c);
    if ($state === 'soon') {
        $soon[] = $c;
    } elseif ($state === 'expired') {
        $expired[] = $c;
    } elseif ($state === 'ok') {
        $later[] = $c;
    } else {
        $unset[] = $c;
    }
}

$previewId = (int) ($_GET['preview'] ?? 0);
$preview = null;
$previewCopy = null;
$previewContact = null;
if ($previewId) {
    $preview = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$previewId]);
    if ($preview) {
        $brand = branding_for($previewId) ?: [];
        $members = db_all('SELECT id, name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$previewId]);
        $previewContact = company_notice_email($previewId, $brand, $members);
        $previewCopy = renewal_notice_copy($preview, $previewContact);
    }
}

$notices = [];
try {
    $notices = db_all(
        'SELECT n.*, c.name AS company_name
         FROM renewal_notices n
         JOIN companies c ON c.id = n.company_id
         ORDER BY n.id DESC
         LIMIT 25'
    );
} catch (Throwable $e) {
    $notices = [];
}

$live = count(array_filter($companies, static fn ($c) => ($c['status'] ?? '') === 'live'));
$withTerm = count($companies) - count($unset);

layout_admin_start('Reports', $user);

$expiryCell = static function (array $c): string {
    $state = company_expiry_state($c);
    $class = $state === 'expired' ? ' pill bad' : ($state === 'soon' ? ' pill warn' : ' pill');
    return '<span class="' . trim($class) . '">' . h(company_expiry_label($c)) . '</span>';
};

$row = static function (array $c) use ($expiryCell, $previewId): void {
    $state = company_expiry_state($c);
    $canNotice = in_array($state, ['soon', 'expired'], true);
    $sent = trim((string) ($c['renewal_notice_sent_at'] ?? ''));
    ?>
    <tr>
      <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
      <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
      <td><?= h(company_term_label($c)) ?></td>
      <td><?= $expiryCell($c) ?></td>
      <td><?= $c['expires_at'] ? h(format_date($c['expires_at'])) : '—' ?></td>
      <td><?= $sent !== '' ? h(substr($sent, 0, 16)) : '—' ?></td>
      <td class="row-actions">
        <div class="actions">
          <?php if ($canNotice): ?>
            <a class="btn sm<?= $previewId === (int) $c['id'] ? '' : ' ghost' ?>" href="<?= h(url('admin_reports.php?preview=' . $c['id'])) ?>"><?= icon('eye', 14) ?>Preview</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn sm" name="action" value="notice"><?= icon('send', 14) ?>Send notice</button>
            </form>
          <?php else: ?>
            <a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('calendar', 14) ?>Set term</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
};
?>
<div class="page-head">
  <div>
    <h1><?= icon('reports') ?>Reports</h1>
    <p class="lede">Paid terms, upcoming expiries, and renewal notices. When a desk is one month from expiry, preview the letter and send it in one click.</p>
  </div>
</div>

<div class="stats">
  <div class="card stat"><?= icon('building', 20) ?><span>Companies</span><strong><?= count($companies) ?></strong></div>
  <div class="card stat"><?= icon('check', 20) ?><span>Live</span><strong><?= $live ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Due within a month</span><strong><?= count($soon) ?></strong></div>
  <div class="card stat"><?= icon('ban', 20) ?><span>Expired</span><strong><?= count($expired) ?></strong></div>
</div>
<p class="hint" style="margin:-12px 0 20px"><?= $withTerm ?> of <?= count($companies) ?> <?= count($companies) === 1 ? 'company has' : 'companies have' ?> a paid term on file.</p>

<?php if ($preview && $previewCopy): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-head">
    <h2><?= icon('letter', 16) ?>Prepared notice for <?= h($preview['name']) ?></h2>
  </div>
  <div style="padding:0 22px 18px">
    <p class="lede" style="margin-bottom:12px">
      To: <?= $previewContact['email'] !== '' ? h($previewContact['email']) : 'No email on file' ?>
      · <?= h(company_expiry_label($preview)) ?>
      · Term <?= h(company_term_label($preview)) ?>
    </p>
    <div class="mail-preview">
      <div class="mail-preview-head"><?= h($previewCopy['subject']) ?></div>
      <div class="mail-preview-body"><?= $previewCopy['html'] ?></div>
    </div>
    <div class="actions" style="margin-top:14px">
      <?php if (in_array(company_expiry_state($preview), ['soon', 'expired'], true) && $previewContact['email'] !== ''): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $preview['id'] ?>">
        <button class="btn" name="action" value="notice"><?= icon('send', 16) ?>Send this notice</button>
      </form>
      <?php elseif ($previewContact['email'] === ''): ?>
        <p class="hint" style="margin:0">Add a public email on the company stationery, or a desk login, before sending.</p>
      <?php endif; ?>
      <a class="btn ghost" href="<?= h(url('admin_company.php?id=' . $preview['id'])) ?>">Open company</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('alert', 16) ?>Due within a month</h2></div>
  <?php if (!$soon): ?>
    <p class="empty">No desks expire in the next 31 days. Set a paid term on a company to track it here.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($soon as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('ban', 16) ?>Expired</h2></div>
  <?php if (!$expired): ?>
    <p class="empty">No lapsed desks.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expired as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Later expiries</h2></div>
  <?php if (!$later): ?>
    <p class="empty">No desks with a term more than a month out.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Term</th>
          <th>Window</th>
          <th>Expires</th>
          <th>Last notice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($later as $c) { $row($c); } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($unset): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-head"><h2><?= icon('building', 16) ?>No paid term yet</h2></div>
  <table class="grid">
    <thead>
      <tr>
        <th>Company</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($unset as $c): ?>
        <tr>
          <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
          <td><span class="pill<?= $c['status'] === 'live' ? '' : ' warn' ?>"><?= h($c['status']) ?></span></td>
          <td><a class="btn sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('calendar', 14) ?>Set term</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= icon('letter', 16) ?>Notices sent</h2></div>
  <?php if (!$notices): ?>
    <p class="empty">No renewal notices yet. When a desk is within a month of expiry, send the prepared letter from this page.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>When</th>
          <th>Company</th>
          <th>To</th>
          <th>Subject</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n): ?>
          <tr>
            <td class="mono"><?= h(substr((string) $n['created_at'], 0, 16)) ?></td>
            <td><?= h($n['company_name']) ?></td>
            <td class="mono"><?= h($n['to_email']) ?></td>
            <td><?= h($n['subject']) ?></td>
            <td><span class="pill<?= $n['status'] === 'queued' ? ' warn' : '' ?>"><?= h($n['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
