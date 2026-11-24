<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

restore_housekeep_uploads();

$token = (string) ($_SESSION['restore_token'] ?? '');
$meta = is_array($_SESSION['restore_meta'] ?? null) ? $_SESSION['restore_meta'] : null;
if ($token !== '') {
    $paths = restore_token_paths($token);
    if (!is_file($paths['upload']) || !is_file($paths['meta'])) {
        restore_forget_token($token);
        $token = '';
        $meta = null;
    } elseif (!$meta) {
        $meta = json_decode((string) file_get_contents($paths['meta']), true) ?: null;
    }
}

$liveCompanies = db_all('SELECT id, name, status FROM companies ORDER BY name');
$error = '';
$step = 'upload';
if ($token !== '' && is_array($meta)) {
    $step = 'configure';
}

$backupCompanyId = (int) ($_GET['backup_company'] ?? post('backup_company_id') ?: ($meta['backup_company_id'] ?? 0));
$liveCompanyId = (int) ($_GET['live_company'] ?? post('live_company_id'));
$parties = [];
$payloadPreview = null;

if ($step === 'configure' && $backupCompanyId > 0 && $token !== '') {
    $built = restore_build_payload($token, $backupCompanyId);
    if (!empty($built['ok']) && is_array($built['data'] ?? null)) {
        $payloadPreview = $built['data'];
        $parties = restore_payload_parties($payloadPreview);
    } elseif ($error === '' && isset($built['error'])) {
        $error = (string) $built['error'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'clear') {
        restore_forget_token($token);
        flash('Upload cleared.');
        redirect('admin_restore.php');
    }
    if ($action === 'upload') {
        $file = $_FILES['backup_file'] ?? [];
        if (!is_array($file)) {
            $file = [];
        }
        $done = restore_store_upload($file);
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Upload failed.');
        } else {
            flash('Backup uploaded. Choose the business and what to restore.');
            redirect('admin_restore.php');
        }
    }
    if ($action === 'restore') {
        $token = (string) ($_SESSION['restore_token'] ?? '');
        $backupCompanyId = (int) post('backup_company_id');
        $liveCompanyId = (int) post('live_company_id');
        $scopes = $_POST['restore_scope'] ?? [];
        if (!is_array($scopes)) {
            $scopes = [];
        }
        if (post('restore_all') !== '') {
            $scopes = array_keys(restore_scopes());
        }
        $partyId = (int) post('party_id');
        $confirm = trim(post('restore_confirm'));
        $live = $liveCompanyId ? db_one('SELECT id, name FROM companies WHERE id = ?', 'i', [$liveCompanyId]) : null;
        if ($token === '') {
            $error = 'Upload a backup file first.';
            $step = 'upload';
        } elseif (!$live) {
            $error = 'Pick the live company that should receive the data.';
            $step = 'configure';
        } elseif (strcasecmp($confirm, (string) $live['name']) !== 0) {
            $error = 'Type the live company name exactly to confirm.';
            $step = 'configure';
            $backupCompanyId = (int) post('backup_company_id');
            $built = restore_build_payload($token, $backupCompanyId);
            if (!empty($built['ok'])) {
                $payloadPreview = $built['data'];
                $parties = restore_payload_parties($payloadPreview);
            }
        } else {
            $built = restore_build_payload($token, $backupCompanyId);
            if (empty($built['ok'])) {
                $error = (string) ($built['error'] ?? 'Could not read that backup.');
                $step = 'configure';
            } else {
                $done = restore_apply_to_company($built['data'], $liveCompanyId, $scopes, [
                    'party_id' => $partyId,
                    'mode' => 'replace',
                    'user_id' => (int) $user['id'],
                ]);
                if (empty($done['ok'])) {
                    $error = (string) ($done['error'] ?? 'Restore failed.');
                    $step = 'configure';
                    $payloadPreview = $built['data'];
                    $parties = restore_payload_parties($payloadPreview);
                } else {
                    restore_forget_token($token);
                    $bits = $done['restored'] ?? [];
                    $countBits = [];
                    foreach (($done['counts'] ?? []) as $k => $n) {
                        if ((int) $n > 0) {
                            $countBits[] = $k . ' ' . (int) $n;
                        }
                    }
                    flash(
                        'Restored into ' . $live['name'] . ': '
                        . implode(', ', $bits)
                        . ($countBits ? ' (' . implode(', ', $countBits) . ')' : '')
                        . '. Other companies were not changed.'
                    );
                    redirect('admin_company.php?id=' . $liveCompanyId);
                }
            }
        }
    }
}

layout_admin_start('Restore', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('file') ?>Restore from backup</h1>
    <p class="lede">Upload a hosting MySQL dump or a Vellisys JSON backup, pick one live company, and restore only what you need. Other desks are left alone. The app does not create backups - use hosting backups when you need a file.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>"><?= icon('building', 16) ?>Companies</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if ($step === 'upload' || $token === ''): ?>
<form class="card form-wide" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="upload">
  <div class="card-head"><h2><?= icon('send', 16) ?>1. Upload backup</h2></div>
  <div style="padding:0 22px 22px">
    <p class="hint">Accepted: <strong>.sql</strong>, <strong>.sql.gz</strong> (hosting phpMyAdmin / cPanel dump), or <strong>.json</strong> / <strong>.json.gz</strong> (older Vellisys desk export). Max 250 MB.</p>
    <label for="backup_file">Backup file</label>
    <input id="backup_file" name="backup_file" type="file" accept=".sql,.gz,.json,.sql.gz,.json.gz" required>
    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit"><?= icon('check', 16) ?>Upload and continue</button>
    </div>
  </div>
</form>
<?php else: ?>
<?php
  $backupCompanies = is_array($meta['companies'] ?? null) ? $meta['companies'] : [];
  if ($backupCompanyId < 1 && $backupCompanies) {
      $backupCompanyId = (int) ($backupCompanies[0]['id'] ?? 0);
  }
?>
<form class="card form-wide" method="post" style="margin-bottom:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="clear">
  <div class="card-head"><h2><?= icon('file', 16) ?>Uploaded file</h2></div>
  <div style="padding:0 22px 16px">
    <p class="hint" style="margin:0 0 10px">
      <strong><?= h((string) ($meta['original_name'] ?? 'backup')) ?></strong>
      · format <?= h((string) ($meta['format'] ?? '')) ?>
      · <?= count($backupCompanies) ?> company<?= count($backupCompanies) === 1 ? '' : 'ies' ?> in file
    </p>
    <button class="btn ghost sm" type="submit"><?= icon('x', 14) ?>Clear upload</button>
  </div>
</form>

<form class="card form-wide" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="restore">
  <div class="card-head"><h2><?= icon('building', 16) ?>2. Choose business and data</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="backup_company_id">Company inside the backup</label>
      <select id="backup_company_id" name="backup_company_id" required onchange="location.href='<?= h(url('admin_restore.php')) ?>?backup_company='+encodeURIComponent(this.value)+'&live_company='+encodeURIComponent(document.getElementById('live_company_id').value)">
        <?php foreach ($backupCompanies as $co): ?>
          <option value="<?= (int) $co['id'] ?>" <?= $backupCompanyId === (int) $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?> (#<?= (int) $co['id'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="live_company_id">Restore into live company</label>
      <select id="live_company_id" name="live_company_id" required>
        <option value="">Select…</option>
        <?php foreach ($liveCompanies as $co): ?>
          <option value="<?= (int) $co['id'] ?>" <?= $liveCompanyId === (int) $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?> · <?= h((string) $co['status']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint" style="margin:6px 0 0">Only this company is written. Every other desk stays as it is.</p>
    </div>
    <div>
      <label for="party_id">One client only (optional)</label>
      <select id="party_id" name="party_id">
        <option value="0">All clients in the selected scopes</option>
        <?php foreach ($parties as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) post('party_id') === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint" style="margin:6px 0 0">Pick one client to bring back only that client's records (and their documents). Leave on All for a full scope restore.</p>
    </div>
  </div>

  <div style="padding:12px 22px 0">
    <p class="lede" style="margin:0 0 10px">What to restore</p>
    <label class="check" style="margin-bottom:10px"><input type="checkbox" name="restore_all" value="1" data-restore-all> Everything listed below</label>
    <div class="form-grid">
      <?php foreach (restore_scopes() as $key => $label): ?>
        <label class="check"><input type="checkbox" name="restore_scope[]" value="<?= h($key) ?>" data-restore-scope <?= in_array($key, ['documents', 'clients'], true) ? 'checked' : '' ?>> <?= h($label) ?></label>
      <?php endforeach; ?>
    </div>
    <p class="hint">Replace mode: selected scopes on the live company are cleared for that company only, then filled from the backup. Logins, mailbox password and paid term are never taken from the dump.</p>
  </div>

  <div style="padding:12px 22px 22px">
    <label for="restore_confirm">Type the live company name to confirm</label>
    <input id="restore_confirm" name="restore_confirm" required autocomplete="off" placeholder="Live company name">
    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit" onclick="return confirm('Restore into the selected live company only? Other companies will not be changed.');"><?= icon('check', 16) ?>Restore selected data</button>
    </div>
  </div>
</form>
<?php endif; ?>

<script>
(function () {
  var all = document.querySelector('[data-restore-all]');
  if (!all) return;
  all.addEventListener('change', function () {
    document.querySelectorAll('[data-restore-scope]').forEach(function (el) {
      el.checked = all.checked;
    });
  });
})();
</script>
<?php
layout_end();
