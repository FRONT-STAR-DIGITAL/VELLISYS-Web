<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$solo = platform_setting_int('stock_addon_solo_ugx', 50000);
$multi = platform_setting_int('stock_addon_multi_ugx', 100000);
$tempPass = default_desk_password();
$signupsOpen = platform_setting('signups_open', '1') !== '0';
$adminCcy = platform_currency();
$alertEmail = platform_alert_email();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $solo = max(0, (int) post('stock_addon_solo_ugx'));
    $multi = max(0, (int) post('stock_addon_multi_ugx'));
    $tempPass = trim(post('default_desk_password'));
    $signupsOpen = !empty($_POST['signups_open']);
    $picked = posted_currency('admin_currency', $adminCcy);
    $alertEmail = strtolower(trim(post('alert_email')));
    $allowed = array_keys(pricing_currencies());
    if (!in_array($picked, $allowed, true)) {
        $picked = 'USD';
    }
    if ($alertEmail !== '' && !filter_var($alertEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Alert Gmail must be a valid email, or leave it blank.';
    } elseif (strlen($tempPass) < 8) {
        $error = 'The default desk password must be at least 8 characters.';
    } else {
        save_platform_setting('stock_addon_solo_ugx', (string) $solo);
        save_platform_setting('stock_addon_multi_ugx', (string) $multi);
        save_platform_setting('default_desk_password', $tempPass);
        save_platform_setting('signups_open', $signupsOpen ? '1' : '0');
        save_platform_setting('admin_currency', $picked);
        save_platform_setting('alert_email', $alertEmail);
        flash('Platform settings saved.');
        redirect('admin_settings.php');
    }
}

layout_admin_start('Settings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('settings') ?>Settings</h1>
    <p class="lede">Amounts and defaults used across checkout and new desk logins. Desk logins themselves are edited on each company page.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<form class="card form-wide" method="post">
  <?= csrf_field() ?>
  <div class="card-head"><h2><?= icon('bank', 16) ?>Super admin figures</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="admin_currency">Currency for Dashboard, Finances and System</label>
      <select id="admin_currency" name="admin_currency">
        <?php foreach (pricing_currencies() as $code => $meta): ?>
          <option value="<?= h($code) ?>" <?= $adminCcy === $code ? 'selected' : '' ?>><?= h($code) ?> · <?= h((string) ($meta['name'] ?? $code)) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Package fees and paid terms are converted into this currency for the super admin portal. Each company desk still bills in its own currency.</p>
    </div>
  </div>
  <div class="card-head"><h2><?= icon('package', 16) ?>Stock add-on</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="stock_addon_solo_ugx">Single-branch add-on (UGX)</label>
      <input id="stock_addon_solo_ugx" name="stock_addon_solo_ugx" type="number" min="0" step="1" required value="<?= (int) $solo ?>">
      <p class="hint">Charged on Start (one branch) when the buyer ticks stock management at checkout.</p>
    </div>
    <div>
      <label for="stock_addon_multi_ugx">More than one branch (UGX)</label>
      <input id="stock_addon_multi_ugx" name="stock_addon_multi_ugx" type="number" min="0" step="1" required value="<?= (int) $multi ?>">
      <p class="hint">Charged on Business and Pro, or any package with more than one branch.</p>
    </div>
  </div>
  <div class="card-head"><h2><?= icon('lock', 16) ?>Desk logins</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="default_desk_password">Default temporary password</label>
      <input id="default_desk_password" name="default_desk_password" required minlength="8" value="<?= h($tempPass) ?>">
      <p class="hint">Pre-filled when you create a desk login. You can still type a different password on the company page.</p>
    </div>
    <div>
      <label class="check" for="signups_open">
        <input id="signups_open" name="signups_open" type="checkbox" value="1" <?= $signupsOpen ? 'checked' : '' ?>>
        Accept website register, demo and checkout forms
      </label>
      <p class="hint">Turn this off if you need to pause new public requests. Existing desks still sign in. You can still create companies here.</p>
    </div>
  </div>
  <div class="card-head"><h2><?= icon('send', 16) ?>Where alerts arrive</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="alert_email">Your Gmail (password resets and mailbox tests)</label>
      <input id="alert_email" name="alert_email" type="email" value="<?= h($alertEmail) ?>" placeholder="you@gmail.com" autocomplete="off">
      <p class="hint">Vellisys letters still leave from <?= h(product_email()) ?>. Hostinger often files mail to that address in Spam, and Spam is not forwarded to Gmail. Put the Gmail you actually open here. Password-reset requests and a copy of Send test will go there as well. On webmail.hostinger.com, open Inbox (not Spam) and mark Vellisys as not spam once.</p>
    </div>
  </div>
  <div class="actions" style="padding:6px 22px 22px">
    <button class="btn sm" type="submit"><?= icon('check', 14) ?>Save settings</button>
    <a class="btn ghost sm" href="<?= h(url('admin_companies.php')) ?>">Open companies</a>
  </div>
</form>
<?php layout_end(); ?>
