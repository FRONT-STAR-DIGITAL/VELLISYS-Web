<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$brand = branding();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $color = strtoupper(post('brand_color') ?: '#82B440');
    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
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
            'UPDATE branding SET name=?, tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, currency=?, letter_templates=? WHERE company_id=?',
            'sssssssssssssssssssi',
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
                strtoupper(post('currency') ?: 'UGX') === 'USD' ? 'USD' : 'UGX',
                encode_letter_templates(isset($_POST['tpl']) && is_array($_POST['tpl']) ? $_POST['tpl'] : []),
                current_company_id(),
            ]
        );
        branding(true);
        flash('Settings saved. Navigation, buttons and documents now use ' . $color . '.');
        redirect('settings.php');
    }
}

$b = branding();
layout_start('Settings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('settings') ?>Settings</h1>
    <p class="lede">Letterhead, colour and the account that sends mail. The colour you set here is used on the navigation bar and every document.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<form class="settings-layout" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <aside class="settings-toc">
    <a href="#account"><?= icon('lock', 16) ?>Account</a>
    <a href="#appearance"><?= icon('palette', 16) ?>Appearance</a>
    <a href="#company"><?= icon('building', 16) ?>Company</a>
    <a href="#tax"><?= icon('hash', 16) ?>Tax</a>
    <a href="#bank"><?= icon('bank', 16) ?>Bank</a>
    <a href="#documents"><?= icon('invoice', 16) ?>Documents</a>
    <a href="#templates"><?= icon('letter', 16) ?>Templates</a>
  </aside>

  <div class="settings-stack">
    <section class="card settings-card" id="account">
      <h2><?= icon('lock') ?>Signed-in account</h2>
      <p class="lede">Emails leave Folio as this person. Change the mailbox by signing in with the address clients should reply to.</p>
      <div class="account-chip">
        <?= icon('user', 22) ?>
        <div>
          <strong><?= h($user['name']) ?></strong>
          <span><?= h($user['email']) ?></span>
        </div>
      </div>
    </section>

    <section class="card settings-card" id="appearance">
      <h2><?= icon('palette') ?>Appearance</h2>
      <p class="lede">Logo and colour once. They fill the bar on the left, the buttons, and the bars on invoices.</p>
      <div class="form-grid">
        <div>
          <label for="brand_color">Brand colour</label>
          <div class="color-row">
            <input id="brand_color" name="brand_color" type="color" value="<?= h($b['brand_color'] ?: '#82B440') ?>" data-color-picker>
            <input id="brand_color_hex" type="text" maxlength="7" value="<?= h($b['brand_color'] ?: '#82B440') ?>" data-color-hex aria-label="Hex colour">
          </div>
        </div>
        <div>
          <label for="logo">Logo</label>
          <input id="logo" name="logo" type="file" accept="image/*,.svg">
          <?php if (!empty($b['logo_path'])): ?>
            <div class="logo-preview"><img src="<?= h(logo_url()) ?>" alt=""></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="preview-nav" data-color-preview>
        <span>Navigation preview</span>
        <strong><?= h($b['name']) ?></strong>
      </div>
    </section>

    <section class="card settings-card" id="company">
      <h2><?= icon('building') ?>Company</h2>
      <p class="lede">Printed on every sheet under the logo.</p>
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
          <label for="city">City</label>
          <input id="city" name="city" value="<?= h($b['city'] ?? '') ?>">
        </div>
        <div style="grid-column:1 / -1">
          <label for="address">Address</label>
          <input id="address" name="address" value="<?= h($b['address']) ?>">
        </div>
      </div>
    </section>

    <section class="card settings-card" id="tax">
      <h2><?= icon('hash') ?>Tax</h2>
      <p class="lede">TIN and VAT number appear on the stationery. Every desk can charge 18% VAT on taxed lines.</p>
      <div class="form-grid">
        <div>
          <label for="tin">TIN</label>
          <input id="tin" name="tin" value="<?= h($b['tin']) ?>">
        </div>
        <div>
          <label for="vat_no">VAT number</label>
          <input id="vat_no" name="vat_no" value="<?= h($b['vat_no'] ?? '') ?>">
        </div>
        <div>
          <label for="currency">Default currency</label>
          <select id="currency" name="currency">
            <?php foreach (currencies() as $code => $label): ?>
              <option value="<?= h($code) ?>" <?= doc_currency($b) === $code ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="prefix">Document prefix</label>
          <input id="prefix" name="prefix" maxlength="12" value="<?= h($b['prefix']) ?>">
        </div>
      </div>
    </section>

    <section class="card settings-card" id="bank">
      <h2><?= icon('bank') ?>Bank</h2>
      <p class="lede">Shown as the payment note on invoices unless you write something else below.</p>
      <div class="form-grid">
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
    </section>

    <section class="card settings-card" id="documents">
      <h2><?= icon('invoice') ?>Document copy</h2>
      <label for="payment_note">Payment note on invoices</label>
      <textarea id="payment_note" name="payment_note" rows="2"><?= h($b['payment_note'] ?? '') ?></textarea>
      <label for="invoice_comments">Default invoice comments</label>
      <textarea id="invoice_comments" name="invoice_comments" rows="4"><?= h($b['invoice_comments'] ?? '') ?></textarea>
    </section>

    <section class="card settings-card" id="templates">
      <h2><?= icon('letter') ?>Templates</h2>
      <p class="lede">Correspondence templates used when you write a headed note. Put <code>{company}</code> where the company name should appear.</p>
      <div data-tpl-list>
        <?php foreach (letter_templates(true) as $key => $tpl): ?>
          <div class="tpl-edit" data-tpl-card>
            <div class="form-grid">
              <div>
                <label>Title on the desk</label>
                <input name="tpl[<?= h($key) ?>][title]" value="<?= h($tpl['title']) ?>" required>
              </div>
              <div>
                <label>Heading on the page</label>
                <input name="tpl[<?= h($key) ?>][heading]" value="<?= h($tpl['heading']) ?>">
              </div>
              <div style="grid-column:1 / -1">
                <label>Subject</label>
                <input name="tpl[<?= h($key) ?>][subject]" value="<?= h($tpl['subject']) ?>">
              </div>
            </div>
            <label>Body</label>
            <textarea name="tpl[<?= h($key) ?>][body]" rows="7"><?= h($tpl['body']) ?></textarea>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
        <button class="btn ghost sm" type="button" data-add-template><?= icon('plus', 14) ?>Add template</button>
        Clear a custom title and save to remove it.
      </p>
      <div class="actions" style="margin-top:18px">
        <button class="btn" type="submit"><?= icon('check') ?>Save settings</button>
      </div>
    </section>
  </div>
</form>
<template id="tpl-proto">
  <div class="tpl-edit" data-tpl-card>
    <div class="form-grid">
      <div>
        <label>Title on the desk</label>
        <input name="tpl[__KEY__][title]" placeholder="Thank you">
      </div>
      <div>
        <label>Heading on the page</label>
        <input name="tpl[__KEY__][heading]" placeholder="THANK YOU">
      </div>
      <div style="grid-column:1 / -1">
        <label>Subject</label>
        <input name="tpl[__KEY__][subject]">
      </div>
    </div>
    <label>Body</label>
    <textarea name="tpl[__KEY__][body]" rows="7">Dear Sir / Madam,

Yours faithfully,
Accounts
{company}</textarea>
  </div>
</template>
<?php layout_end(); ?>
