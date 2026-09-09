<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$brand = branding();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $color = post('brand_color') ?: '#82B440';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#82B440';
    }
                    $logoPath = $brand['logo_path'] ?? 'assets/img/ofagros-logo.png';
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], true)) {
            $error = 'Logo must be PNG, JPG, SVG, GIF or WebP.';
        } elseif ($_FILES['logo']['size'] > 2_000_000) {
            $error = 'Logo must be under 2 MB.';
        } else {
            $dir = ROOT_PATH . '/uploads/logos';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = 'logo-' . date('YmdHis') . '.' . $ext;
            $dest = $dir . '/' . $name;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $logoPath = 'uploads/logos/' . $name;
            } else {
                $error = 'Could not save the logo file.';
            }
        }
    }
    if ($error === '') {
        db_exec(
            'UPDATE branding SET name=?, tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, plan=? WHERE id=1',
            'ssssssssssssssssss',
            [
                post('name'),
                post('tagline'),
                post('tin'),
                post('vat_no'),
                post('address'),
                post('city'),
                post('phone'),
                post('email'),
                post('website'),
                post('bank_name'),
                post('account_name'),
                post('account_number'),
                $color,
                $logoPath,
                strtoupper(post('prefix') ?: 'OFG'),
                post('payment_note'),
                post('invoice_comments'),
                post('plan') ?: 'sme',
            ]
        );
        branding(true);
        flash('Branding saved. The navigation bar and documents now use ' . $color . '.');
        redirect('branding.php');
    }
}

$b = branding();
layout_start('Branding', $user);
?>
<div class="page-head">
  <div>
    <h1>Branding</h1>
    <p class="lede">Set the colour once. It fills the navigation bar, buttons, invoice bars and the login panel.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= h($error) ?></p><?php endif; ?>

<form class="card form-wide" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div>
      <label for="name">Company name</label>
      <input id="name" name="name" required value="<?= h($b['name']) ?>">
    </div>
    <div>
      <label for="tagline">Tagline</label>
      <input id="tagline" name="tagline" value="<?= h($b['tagline']) ?>">
    </div>
    <div>
      <label for="brand_color">Brand colour</label>
      <div class="color-row">
        <input id="brand_color" name="brand_color" type="color" value="<?= h($b['brand_color'] ?: '#82B440') ?>">
        <span class="pill" style="background:<?= h($b['brand_color']) ?>;color:#fff"><?= h($b['brand_color']) ?></span>
      </div>
    </div>
    <div>
      <label for="prefix">Document prefix</label>
      <input id="prefix" name="prefix" maxlength="12" value="<?= h($b['prefix']) ?>">
    </div>
    <div>
      <label for="plan">Plan</label>
      <select id="plan" name="plan">
        <?php foreach (['starter' => 'Starter — no VAT', 'sme' => 'SME — VAT + EFRIS marks', 'office' => 'Office — three users'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= ($b['plan'] ?? 'sme') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="logo">Logo</label>
      <input id="logo" name="logo" type="file" accept="image/*,.svg">
      <?php if (!empty($b['logo_path'])): ?>
        <p class="hint"><img src="<?= h(logo_url()) ?>" alt="" style="height:40px;margin-top:8px;background:#fff;padding:4px;border-radius:6px"></p>
      <?php endif; ?>
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h($b['tin']) ?>">
    </div>
    <div>
      <label for="vat_no">VAT number</label>
      <input id="vat_no" name="vat_no" value="<?= h($b['vat_no'] ?? '') ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h($b['phone']) ?>">
    </div>
    <div>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= h($b['email']) ?>">
    </div>
    <div>
      <label for="website">Website</label>
      <input id="website" name="website" value="<?= h($b['website']) ?>">
    </div>
    <div>
      <label for="address">Address</label>
      <input id="address" name="address" value="<?= h($b['address']) ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h($b['city'] ?? '') ?>">
    </div>
    <div>
      <label for="bank_name">Bank</label>
      <input id="bank_name" name="bank_name" value="<?= h($b['bank_name'] ?? '') ?>">
    </div>
    <div>
      <label for="account_name">Account name</label>
      <input id="account_name" name="account_name" value="<?= h($b['account_name'] ?? '') ?>">
    </div>
    <div>
      <label for="account_number">Account number</label>
      <input id="account_number" name="account_number" value="<?= h($b['account_number'] ?? '') ?>">
    </div>
  </div>
  <label for="payment_note">Payment note on invoices</label>
  <textarea id="payment_note" name="payment_note" rows="2"><?= h($b['payment_note'] ?? '') ?></textarea>
  <label for="invoice_comments">Default invoice comments</label>
  <textarea id="invoice_comments" name="invoice_comments" rows="4"><?= h($b['invoice_comments'] ?? '') ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit">Save branding</button>
  </div>
</form>
<?php layout_end(); ?>
