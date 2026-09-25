<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();
$error = '';
$fresh = db_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $user['id']]) ?: $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'avatar') {
        $saved = sales_save_avatar((int) $user['id']);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save photo.');
        } else {
            flash(empty($saved['path']) ? 'Choose a photo to upload.' : 'Profile photo updated.');
            redirect('sales_profile.php');
        }
    } elseif ($action === 'password') {
        $done = sales_change_password(
            (int) $user['id'],
            (string) post('current_password'),
            (string) post('new_password'),
            (string) post('new_password2')
        );
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not change password.');
        } else {
            flash('Password updated.');
            redirect('sales_profile.php');
        }
    }
    $fresh = db_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $user['id']]) ?: $user;
}

$avatar = sales_avatar_url($fresh);
sales_layout_start('Profile', $fresh);
?>
<div class="page-head">
  <div>
    <h1><?= icon('user') ?>Profile</h1>
    <p class="lede">Update your photo and password. Name and email are set by Vellisys admin.</p>
  </div>
</div>
<?php if ($error): ?><p class="flash flash-err"><?= h($error) ?></p><?php endif; ?>

<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('image', 16) ?>Photo</h2></div>
    <form class="pad-form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="avatar">
      <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px">
        <?php if ($avatar !== ''): ?>
          <img class="sales-avatar-lg" src="<?= h($avatar) ?>" alt="">
        <?php else: ?>
          <span class="sales-avatar-lg" style="display:inline-flex;align-items:center;justify-content:center"><?= icon('user', 36) ?></span>
        <?php endif; ?>
        <div>
          <p class="hint" style="margin:0">PNG, JPG, GIF or WebP · under 2 MB</p>
        </div>
      </div>
      <label for="avatar">Choose photo
        <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
      </label>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check', 16) ?>Save photo</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= icon('lock', 16) ?>Account</h2></div>
    <div class="pad-form">
      <label>Name
        <input value="<?= h((string) $fresh['name']) ?>" readonly>
      </label>
      <label>Email
        <input value="<?= h((string) $fresh['email']) ?>" readonly>
      </label>
      <label>Phone
        <input value="<?= h((string) ($fresh['phone'] ?? '')) ?>" readonly>
      </label>
      <p class="hint">Ask a Vellisys admin if your name, email or phone needs changing.</p>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px;max-width:32rem">
  <div class="card-head"><h2><?= icon('lock', 16) ?>Change password</h2></div>
  <form class="pad-form" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <label for="current_password">Current password
      <div class="pw-field">
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
    </label>
    <label for="new_password">New password
      <div class="pw-field">
        <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
    </label>
    <label for="new_password2">Confirm new password
      <div class="pw-field">
        <input id="new_password2" name="new_password2" type="password" required minlength="8" autocomplete="new-password">
        <button class="pw-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
          <span data-eye><?= icon('eye', 16) ?></span>
          <span data-eye-off hidden><?= icon('eye-off', 16) ?></span>
        </button>
      </div>
    </label>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('check', 16) ?>Save password</button>
    </div>
  </form>
</div>
<?php sales_layout_end(); ?>
