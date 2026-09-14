<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$solo = platform_setting_int('stock_addon_solo_ugx', 50000);
$multi = platform_setting_int('stock_addon_multi_ugx', 100000);
$tempPass = default_desk_password();
$signupsOpen = platform_setting('signups_open', '1') !== '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $solo = max(0, (int) post('stock_addon_solo_ugx'));
    $multi = max(0, (int) post('stock_addon_multi_ugx'));
    $tempPass = trim(post('default_desk_password'));
    $signupsOpen = !empty($_POST['signups_open']);
    if (strlen($tempPass) < 8) {
        $error = 'The default desk password must be at least 8 characters.';
    } else {
        save_platform_setting('stock_addon_solo_ugx', (string) $solo);
        save_platform_setting('stock_addon_multi_ugx', (string) $multi);
        save_platform_setting('default_desk_password', $tempPass);
        save_platform_setting('signups_open', $signupsOpen ? '1' : '0');
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
  <div class="actions" style="padding:6px 22px 22px">
    <button class="btn sm" type="submit"><?= icon('check', 14) ?>Save settings</button>
    <a class="btn ghost sm" href="<?= h(url('admin_companies.php')) ?>">Open companies</a>
  </div>
</form>
<?php layout_end(); ?>
