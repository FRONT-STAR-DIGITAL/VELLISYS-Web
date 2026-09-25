<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'create') {
            $name = trim(post('name'));
            $email = strtolower(trim(post('email')));
            $password = (string) post('password');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Name and a valid email are required.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            if (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
                throw new RuntimeException('That email already has a Vellisys login.');
            }
            db_exec(
                'INSERT INTO users (name, email, password_hash, role, access, company_id) VALUES (?,?,?,?,?,NULL)',
                'sssss',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'platform', 'admin']
            );
            flash('Platform admin created for ' . $email . '.');
            redirect('admin_admins.php');
        }

        if ($action === 'update') {
            $id = (int) post('id');
            $row = $id ? db_one("SELECT * FROM users WHERE id = ? AND role = 'platform'", 'i', [$id]) : null;
            if (!$row) {
                throw new RuntimeException('That admin was not found.');
            }
            $name = trim(post('name'));
            $email = strtolower(trim(post('email')));
            $password = (string) post('password');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Name and a valid email are required.');
            }
            $taken = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, $id]);
            if ($taken) {
                throw new RuntimeException('That email already has a Vellisys login.');
            }
            if ($password !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException('Password must be at least 8 characters.');
                }
                db_exec(
                    'UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ? AND role = \'platform\'',
                    'sssi',
                    [$name, $email, password_hash($password, PASSWORD_DEFAULT), $id]
                );
            } else {
                db_exec(
                    'UPDATE users SET name = ?, email = ? WHERE id = ? AND role = \'platform\'',
                    'ssi',
                    [$name, $email, $id]
                );
            }
            flash('Admin account updated.');
            redirect('admin_admins.php');
        }

        if ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one("SELECT * FROM users WHERE id = ? AND role = 'platform'", 'i', [$id]) : null;
            if (!$row) {
                throw new RuntimeException('That admin was not found.');
            }
            $countRow = db_one("SELECT COUNT(*) AS c FROM users WHERE role = 'platform'");
            $total = (int) ($countRow['c'] ?? 0);
            if ($id === (int) $user['id'] && $total <= 1) {
                throw new RuntimeException('You cannot delete yourself when you are the last platform admin.');
            }
            if ($total <= 1) {
                throw new RuntimeException('At least one platform admin must remain.');
            }
            db_exec("DELETE FROM users WHERE id = ? AND role = 'platform'", 'i', [$id]);
            flash('Platform admin removed.');
            redirect('admin_admins.php');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $editId = (int) post('id');
    }
}

$admins = db_all("SELECT id, name, email, created_at FROM users WHERE role = 'platform' ORDER BY id ASC");
$editing = null;
foreach ($admins as $a) {
    if ((int) $a['id'] === $editId) {
        $editing = $a;
        break;
    }
}

layout_admin_start('Admins', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('user') ?>Admins</h1>
    <p class="lede">Platform accounts that share the same admin dashboard - sign-ups, companies, sales, messages and reports. New super admins see everything the others do. Keep at least one admin.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="desk-grid">
  <div class="card">
    <div class="card-head"><h2><?= icon('user', 16) ?>Platform users</h2></div>
    <?php if (!$admins): ?>
      <p class="empty">No platform admins yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
              <tr>
                <td><strong><?= h($a['name']) ?></strong><?= (int) $a['id'] === (int) $user['id'] ? ' <span class="pill">You</span>' : '' ?></td>
                <td class="mono"><?= h($a['email']) ?></td>
                <td class="mono"><?= h(substr((string) ($a['created_at'] ?? ''), 0, 10)) ?></td>
                <td class="row-actions">
                  <div class="actions">
                    <a class="btn ghost sm" href="<?= h(url('admin_admins.php?edit=' . (int) $a['id'])) ?>"><?= icon('pencil', 14) ?>Edit</a>
                    <?php if (count($admins) > 1): ?>
                      <form method="post" onsubmit="return confirm('Remove this platform admin?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button class="btn ghost sm" type="submit"><?= icon('x', 14) ?>Remove</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= $editing ? icon('pencil', 16) . 'Edit admin' : icon('plus', 16) . 'New admin' ?></h2></div>
    <form class="form" method="post" style="padding:0 18px 18px" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php endif; ?>
      <label for="name">Name</label>
      <input id="name" name="name" required value="<?= h((string) ($editing['name'] ?? post('name'))) ?>">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= h((string) ($editing['email'] ?? post('email'))) ?>">
      <label for="password"><?= $editing ? 'New password (optional)' : 'Password' ?></label>
      <input id="password" name="password" type="password" <?= $editing ? '' : 'required' ?> minlength="8" autocomplete="new-password" placeholder="<?= $editing ? 'Leave blank to keep' : 'At least 8 characters' ?>">
      <div class="actions" style="margin-top:14px">
        <button class="btn" type="submit"><?= icon('check') ?><?= $editing ? 'Save changes' : 'Create admin' ?></button>
        <?php if ($editing): ?>
          <a class="btn ghost" href="<?= h(url('admin_admins.php')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php layout_end(); ?>
