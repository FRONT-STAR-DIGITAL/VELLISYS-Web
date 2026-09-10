<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = post('current_password');
    $next = post('new_password');
    $again = post('new_password2');
    $row = db_one('SELECT password_hash FROM users WHERE id = ?', 'i', [(int) $user['id']]);
    if (!$row || !password_verify($current, (string) $row['password_hash'])) {
        $error = 'Current password is not correct.';
    } elseif (strlen($next) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($next !== $again) {
        $error = 'The two new passwords do not match.';
    } else {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($next, PASSWORD_DEFAULT), (int) $user['id']]);
        flash('Your password was changed.');
        redirect('account.php');
    }
}

layout_start('Password', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('lock') ?>Password</h1>
    <p class="lede">Change the password for <?= h($user['email']) ?>. The company admin can also reset passwords under Settings → People.</p>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<form class="card form" method="post" style="max-width:28rem" autocomplete="off">
  <?= csrf_field() ?>
  <label for="current_password">Current password</label>
  <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
  <label for="new_password">New password</label>
  <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
  <label for="new_password2">Confirm new password</label>
  <input id="new_password2" name="new_password2" type="password" required minlength="8" autocomplete="new-password">
  <div class="actions" style="margin-top:14px">
    <button class="btn" type="submit"><?= icon('check') ?>Save password</button>
  </div>
</form>
<?php layout_end(); ?>
