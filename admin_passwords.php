<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? db_one('SELECT * FROM sales_vault WHERE id = ?', 'i', [$editId]) : null;
$showForm = isset($_GET['new']) || $edit || (string) ($_GET['show'] ?? '') === 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save') {
        $id = (int) post('vault_id') ?: null;
        $saved = sales_vault_save([
            'company_id' => (int) post('company_id') ?: null,
            'company_name' => post('company_name'),
            'email' => post('email'),
            'password' => post('password'),
            'notes' => post('notes'),
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save.');
            $showForm = true;
            $editId = (int) ($id ?? 0);
            $edit = [
                'id' => $editId,
                'company_id' => post('company_id'),
                'company_name' => post('company_name'),
                'email' => post('email'),
                'notes' => post('notes'),
            ];
        } else {
            flash($id ? 'Password entry updated.' : 'Password saved to the vault.');
            redirect('admin_passwords.php');
        }
    } elseif ($action === 'delete') {
        $done = sales_vault_delete((int) post('vault_id'));
        flash(empty($done['ok']) ? 'Could not delete.' : 'Entry removed from the vault.', empty($done['ok']) ? 'err' : 'ok');
        redirect('admin_passwords.php');
    } elseif ($action === 'import_company') {
        $cid = (int) post('company_id');
        $co = $cid ? db_one('SELECT c.id, c.name, u.email FROM companies c LEFT JOIN users u ON u.company_id = c.id AND u.role = \'admin\' ORDER BY u.id ASC', 'i', [$cid]) : null;
        // Prefer first admin login for that company
        $admin = $cid ? db_one("SELECT email FROM users WHERE company_id = ? AND role IN ('admin','member') ORDER BY role = 'admin' DESC, id ASC LIMIT 1", 'i', [$cid]) : null;
        $company = $cid ? db_one('SELECT id, name FROM companies WHERE id = ?', 'i', [$cid]) : null;
        if (!$company) {
            flash('Company not found.', 'err');
            redirect('admin_passwords.php');
        }
        $email = (string) ($admin['email'] ?? '');
        $existing = $email !== ''
            ? db_one('SELECT id FROM sales_vault WHERE email = ? LIMIT 1', 's', [$email])
            : null;
        if ($existing) {
            flash('That desk email is already in the vault. Edit it instead.');
            redirect('admin_passwords.php?edit=' . (int) $existing['id']);
        }
        flash('Fill the temporary password for ' . $company['name'] . ', then save.');
        redirect('admin_passwords.php?new=1&company_id=' . $cid . '&company_name=' . rawurlencode((string) $company['name']) . '&email=' . rawurlencode($email));
    }
}

$rows = sales_vault_list($q);
$companies = db_all('SELECT id, name FROM companies ORDER BY name LIMIT 300');

layout_admin_start('Passwords', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('lock') ?>Client passwords</h1>
    <p class="lede">Company names, desk emails and passwords in one place. Copy when you need to help a client sign in.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('admin_passwords.php?new=1')) ?>"><?= icon('plus', 16) ?>New entry</a>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if ($showForm):
    $preCompanyId = (int) ($edit['company_id'] ?? $_GET['company_id'] ?? 0);
    $preName = (string) ($edit['company_name'] ?? $_GET['company_name'] ?? '');
    $preEmail = (string) ($edit['email'] ?? $_GET['email'] ?? '');
    $preNotes = (string) ($edit['notes'] ?? '');
    $plain = $edit ? sales_vault_decrypt((string) ($edit['password_enc'] ?? '')) : '';
    ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= $edit ? 'Edit entry' : 'New password' ?></h2></div>
  <form method="post" class="pad-form form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="vault_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div>
      <label for="company_id">Link company (optional)</label>
      <select id="company_id" name="company_id">
        <option value="">—</option>
        <?php foreach ($companies as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $preCompanyId === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="company_name">Company name</label>
      <input id="company_name" name="company_name" required value="<?= h($preName) ?>" placeholder="Harbour &amp; Co.">
    </div>
    <div>
      <label for="email">Login email</label>
      <input id="email" name="email" type="email" required value="<?= h($preEmail) ?>" placeholder="accounts@company.com">
    </div>
    <div>
      <label for="password">Password<?= $edit ? ' (blank = keep)' : '' ?></label>
      <input id="password" name="password" type="text" <?= $edit ? '' : 'required' ?> value="<?= h($edit ? '' : $plain) ?>" placeholder="<?= $edit ? 'Leave blank to keep current' : 'Temporary desk password' ?>" autocomplete="new-password">
      <?php if ($edit && $plain !== ''): ?>
        <p class="hint">Current: <code class="mono" data-copy="<?= h($plain) ?>"><?= h($plain) ?></code>
          <button type="button" class="btn ghost sm" data-copy-btn="<?= h($plain) ?>">Copy</button></p>
      <?php endif; ?>
    </div>
    <div class="full">
      <label for="notes">Notes</label>
      <input id="notes" name="notes" value="<?= h($preNotes) ?>" placeholder="Who asked for reset, last shared…">
    </div>
    <div class="actions" style="grid-column:1/-1">
      <button class="btn" type="submit"><?= icon('check') ?>Save</button>
      <a class="btn ghost" href="<?= h(url('admin_passwords.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<form method="get" class="stock-search" style="margin-bottom:16px">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search company, email, notes" autocomplete="off">
  <button class="btn ghost sm" type="submit"><?= icon('search', 14) ?>Search</button>
</form>

<div class="card">
  <div class="card-head"><h2><?= icon('lock', 16) ?>Vault</h2></div>
  <?php if (!$rows): ?>
    <p class="empty">No passwords stored yet. Add one, or save from company onboarding.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Company</th>
            <th>Email</th>
            <th>Password</th>
            <th>Notes</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
              $pw = sales_vault_decrypt((string) $row['password_enc']);
              ?>
            <tr>
              <td>
                <strong><?= h((string) $row['company_name']) ?></strong>
                <?php if (!empty($row['company_id'])): ?>
                  <div class="muted"><a href="<?= h(url('admin_company.php?id=' . (int) $row['company_id'])) ?>">Open company</a></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="mono"><?= h((string) $row['email']) ?></span>
                <button type="button" class="btn ghost sm" data-copy-btn="<?= h((string) $row['email']) ?>">Copy</button>
              </td>
              <td>
                <code class="mono vault-pw" data-masked="1"><?= h($pw !== '' ? str_repeat('•', min(12, max(6, strlen($pw)))) : '—') ?></code>
                <?php if ($pw !== ''): ?>
                  <button type="button" class="btn ghost sm" data-reveal-pw="<?= h($pw) ?>">Show</button>
                  <button type="button" class="btn ghost sm" data-copy-btn="<?= h($pw) ?>">Copy</button>
                <?php endif; ?>
              </td>
              <td><?= h((string) ($row['notes'] ?? '')) ?></td>
              <td class="date-cell"><?= h(format_date(substr((string) $row['updated_at'], 0, 10))) ?></td>
              <td class="row-actions">
                <a class="btn ghost sm" href="<?= h(url('admin_passwords.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Remove this vault entry?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="vault_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn ghost sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head"><h2>Pull from a company</h2></div>
  <form method="post" class="pad-form form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="import_company">
    <div>
      <label for="import_company_id">Company</label>
      <select id="import_company_id" name="company_id" required>
        <option value="">Choose…</option>
        <?php foreach ($companies as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="actions" style="align-self:end"><button class="btn ghost" type="submit">Start entry</button></div>
  </form>
</div>
<script>
(function(){
  function copyText(t){
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(t);
    }
    var a = document.createElement('textarea');
    a.value = t; document.body.appendChild(a); a.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(a);
    return Promise.resolve();
  }
  document.querySelectorAll('[data-copy-btn]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = btn.getAttribute('data-copy-btn') || '';
      copyText(t).then(function(){ btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = 'Copy'; }, 1200); });
    });
  });
  document.querySelectorAll('[data-reveal-pw]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var cell = btn.closest('td');
      var code = cell && cell.querySelector('.vault-pw');
      if (!code) return;
      var plain = btn.getAttribute('data-reveal-pw') || '';
      if (code.getAttribute('data-masked') === '1') {
        code.textContent = plain;
        code.setAttribute('data-masked', '0');
        btn.textContent = 'Hide';
      } else {
        code.textContent = '••••••••';
        code.setAttribute('data-masked', '1');
        btn.textContent = 'Show';
      }
    });
  });
})();
</script>
<?php layout_end(); ?>
