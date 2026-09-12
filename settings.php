<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_desk_admin();

$brand = branding();
$error = '';
$cid = current_company_id();
$deskCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]);
$members = db_all('SELECT id, name, job_title, email, role, access, created_at FROM users WHERE company_id = ? ORDER BY role = \'admin\' DESC, id', 'i', [$cid]);
$seats = company_user_limit($deskCompany ?: null);
$used = company_seat_count($cid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'add_user') {
        $made = create_desk_user($cid, [
            'name' => post('user_name'),
            'email' => post('user_email'),
            'password' => post('user_password'),
            'job_title' => post('user_title'),
            'access' => post('user_access'),
        ]);
        if (empty($made['ok'])) {
            $error = (string) ($made['error'] ?? 'Could not add that user.');
        } else {
            flash('Login created for ' . $made['email'] . '. Temporary password: ' . $made['password']);
            redirect('settings.php#people');
        }
    } elseif ($action === 'reset_password') {
        $uid = (int) post('user_id');
        $member = db_one('SELECT * FROM users WHERE id = ? AND company_id = ?', 'ii', [$uid, $cid]);
        $newPass = post('new_password');
        if (!$member) {
            $error = 'That user is not on this desk.';
        } elseif (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($newPass, PASSWORD_DEFAULT), $uid]);
            flash('Password updated for ' . $member['email'] . '.');
            redirect('settings.php#people');
        }
    } elseif ($action === '' || $action === 'save_brand') {
    $color = parse_hex_color(post('brand_color'), '#82B440');
    $accent = parse_hex_color(post('brand_accent'), '#C6A15B');
    $deep = parse_hex_color(post('brand_deep'), '#1F3A12');
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
            'UPDATE branding SET name=?, tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, brand_accent=?, brand_deep=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, currency=?, fx_ugx_per_usd=?, letter_templates=?, doc_template=?, logo_bg=? WHERE company_id=?',
            'ssssssssssssssssssssdssii',
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
                $accent,
                $deep,
                $logoPath,
                strtoupper(post('prefix') ?: 'OFG'),
                post('payment_note'),
                post('invoice_comments'),
                posted_currency('currency', default_currency()),
                parse_fx_rate(post('fx_ugx_per_usd')),
                encode_letter_templates(isset($_POST['tpl']) && is_array($_POST['tpl']) ? $_POST['tpl'] : []),
                array_key_exists(post('doc_template'), doc_templates()) ? post('doc_template') : 'folio',
                (isset($_POST['logo_bg']) && (is_array($_POST['logo_bg']) ? in_array('1', $_POST['logo_bg'], true) : (string) $_POST['logo_bg'] === '1')) ? 1 : 0,
                current_company_id(),
            ]
        );
        $tpl = array_key_exists(post('doc_template'), doc_templates()) ? post('doc_template') : 'folio';
        db_exec('UPDATE documents SET doc_template = ? WHERE company_id = ?', 'si', [$tpl, current_company_id()]);
        branding(true);
        unset($_SESSION['branding_welcome']);
        mark_branding_saved($cid, $user);
        flash('Settings saved. This design now prints on every document. USD converts at your ' . default_currency() . ' rate.');
        redirect('settings.php');
    }
    }
    if ($action === 'dismiss_welcome') {
        unset($_SESSION['branding_welcome']);
        redirect('settings.php');
    }
}

$b = branding();
$showWelcome = isset($_GET['welcome']) || !empty($_SESSION['branding_welcome']);
layout_start('Settings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('settings') ?>Settings</h1>
    <p class="lede">Letterhead, colours, and your currency. Quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors and custom mail leave from the company mailbox Vellisys assigned - you cannot change it here.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="settings-layout">
  <aside class="settings-toc">
    <a href="#account"><?= icon('lock', 16) ?>Account</a>
    <a href="#people"><?= icon('user', 16) ?>People</a>
    <a href="#appearance"><?= icon('palette', 16) ?>Appearance</a>
    <a href="#company"><?= icon('building', 16) ?>Company</a>
    <a href="#tax"><?= icon('hash', 16) ?>Tax</a>
    <a href="#bank"><?= icon('bank', 16) ?>Bank</a>
    <a href="#documents"><?= icon('invoice', 16) ?>Documents</a>
    <a href="#templates"><?= icon('palette', 16) ?>Templates</a>
  </aside>

  <div class="settings-stack">
    <section class="card settings-card" id="account">
      <h2><?= icon('lock') ?>Signed-in account</h2>
      <p class="lede">This is who is using the desk. Change your own password on Password. Outgoing mail leaves from the company mailbox Vellisys assigned. Only a Vellisys admin can change that mailbox.</p>
      <div class="account-chip">
        <?= icon('user', 22) ?>
        <div>
          <strong><?= h($user['name']) ?></strong>
          <span><?= h($user['email']) ?><?= !empty($user['job_title']) ? ' · ' . h((string) $user['job_title']) : '' ?></span>
        </div>
      </div>
      <p style="margin:12px 0 0"><a class="btn ghost sm" href="<?= h(url('account.php')) ?>"><?= icon('lock', 14) ?>Change my password</a></p>
      <?php
        $deskCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [current_company_id()]);
        $sendAcct = $deskCompany ? company_mail_account($deskCompany) : null;
      ?>
      <div class="account-chip" style="margin-top:12px">
        <?= icon('send', 22) ?>
        <div>
          <strong>Sending mailbox</strong>
          <?php if ($sendAcct): ?>
            <span><?= h($sendAcct['from_name']) ?> · <?= h($sendAcct['from_email']) ?> (locked)</span>
          <?php else: ?>
            <span>Not assigned yet. Ask Vellisys to add the company Hostinger address. You can still print and share a link.</span>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card settings-card" id="people">
      <h2><?= icon('user') ?>People</h2>
      <p class="lede">This desk has <?= (int) $used ?> of <?= (int) $seats ?> login<?= $seats === 1 ? '' : 's' ?>. Vellisys sets the number. Maximum is 3: the company admin and up to two more. Only the company admin can open Reports, Settings and this list.</p>
      <?php if (!$members): ?>
        <p class="empty">No logins yet.</p>
      <?php else: ?>
        <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr>
              <th>Name</th>
              <th>Title</th>
              <th>Email</th>
              <th>Access</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr>
                <td><?= h($m['name']) ?></td>
                <td><?= h((string) ($m['job_title'] ?? '')) ?></td>
                <td class="mono"><?= h($m['email']) ?></td>
                <td><?= h(desk_access_label((string) $m['role'], (string) ($m['access'] ?? 'books'))) ?></td>
                <td>
                  <form method="post" class="people-reset">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <input name="new_password" type="password" minlength="8" required placeholder="New password" autocomplete="new-password" aria-label="New password for <?= h($m['name']) ?>">
                    <button class="btn ghost sm" type="submit">Reset</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
      <?php if ($used < $seats): ?>
        <form method="post" class="people-add" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_user">
          <h3>Add a user</h3>
          <div class="form-grid">
            <div>
              <label for="user_name">Name</label>
              <input id="user_name" name="user_name" required value="<?= h(post('user_name')) ?>">
            </div>
            <div>
              <label for="user_title">Title</label>
              <input id="user_title" name="user_title" value="<?= h(post('user_title')) ?>" placeholder="Accountant">
            </div>
            <div>
              <label for="user_email">Email</label>
              <input id="user_email" name="user_email" type="email" required value="<?= h(post('user_email')) ?>">
            </div>
            <div>
              <label for="user_access">Access</label>
              <select id="user_access" name="user_access">
                <?php foreach (desk_staff_access_levels() as $key => $info): ?>
                  <option value="<?= h($key) ?>" <?= post('user_access') === $key ? 'selected' : '' ?>><?= h($info['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint"><?= h(desk_staff_access_levels()['books']['hint']) ?> Sales: <?= h(desk_staff_access_levels()['sales']['hint']) ?></p>
            </div>
            <div>
              <label for="user_password">Temporary password</label>
              <input id="user_password" name="user_password" value="<?= h(post('user_password') !== '' ? post('user_password') : 'folio2026') ?>" minlength="8">
            </div>
          </div>
          <div class="actions" style="margin-top:12px">
            <button class="btn sm" type="submit"><?= icon('plus', 14) ?>Create login</button>
          </div>
        </form>
      <?php else: ?>
        <p class="hint">All <?= (int) $seats ?> seats are in use. Ask Vellisys if you need to replace a login.</p>
      <?php endif; ?>
    </section>

    <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_brand">
    <section class="card settings-card" id="appearance">
      <h2><?= icon('palette') ?>Appearance</h2>
      <p class="lede">Logo and three brand colours. Primary paints the desk. Accent and deep colour the document designs - bars, corners, rails and totals.</p>
      <div class="form-grid">
        <div>
          <label for="brand_color">Primary</label>
          <div class="color-row" data-color-pair data-color-role="primary">
            <input id="brand_color" name="brand_color" type="color" value="<?= h(parse_hex_color($b['brand_color'] ?? '', '#82B440')) ?>" data-color-picker>
            <input id="brand_color_hex" name="brand_color_hex" type="text" maxlength="7" value="<?= h(parse_hex_color($b['brand_color'] ?? '', '#82B440')) ?>" data-color-hex aria-label="Primary hex">
          </div>
        </div>
        <div>
          <label for="brand_accent">Accent</label>
          <div class="color-row" data-color-pair data-color-role="accent">
            <input id="brand_accent" name="brand_accent" type="color" value="<?= h(parse_hex_color($b['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-picker>
            <input id="brand_accent_hex" type="text" maxlength="7" value="<?= h(parse_hex_color($b['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-hex aria-label="Accent hex">
          </div>
        </div>
        <div>
          <label for="brand_deep">Deep</label>
          <div class="color-row" data-color-pair data-color-role="deep">
            <input id="brand_deep" name="brand_deep" type="color" value="<?= h(parse_hex_color($b['brand_deep'] ?? '', '#1F3A12')) ?>" data-color-picker>
            <input id="brand_deep_hex" type="text" maxlength="7" value="<?= h(parse_hex_color($b['brand_deep'] ?? '', '#1F3A12')) ?>" data-color-hex aria-label="Deep hex">
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
      <div class="palette-swatches" aria-hidden="true">
        <span style="background:var(--brand)"></span>
        <span style="background:var(--brand-2)"></span>
        <span style="background:var(--brand-3)"></span>
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
          <label for="currency-pick">Currency</label>
          <?php currency_field('currency', 'currency', (string) ($b['currency'] ?? default_currency()), ['data-fx-home-input' => true]); ?>
          <p class="hint">Pick a currency, or type any three-letter primary code (UGX, KES, EUR, MWK…).</p>
        </div>
        <div>
          <label for="fx_ugx_per_usd">1 USD equals</label>
          <div class="fx-row">
            <input id="fx_ugx_per_usd" name="fx_ugx_per_usd" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format(fx_home_per_usd(), 4, '.', ''), '0'), '.')) ?>">
            <span data-fx-home-label><?= h(default_currency()) ?></span>
          </div>
          <p class="hint">Used when a document is in USD and your books are in <?= h(default_currency()) ?>, and on printed equivalents.</p>
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
      <h2><?= icon('palette') ?>Document designs</h2>
      <p class="lede">Twelve layouts. Pick the one that matches the company. Logo watermark and Bond watermark print the company mark faintly on the paper. Atelier and Company seal are quiet, formal sheets meant to email. Every invoice, quotation, receipt, expense and headed note reprints in that design, in the client's logo and colours. Changing it here reprints the whole books. Correspondence text stays editable - only the paper around it changes.</p>
      <?php $letterTpls = letter_templates(true); ?>
      <label class="check">
        <input type="hidden" name="logo_bg" value="0">
        <input type="checkbox" name="logo_bg" value="1" <?= !empty($b['logo_bg']) ? 'checked' : '' ?>>
        Put the company logo in the background of documents
      </label>
      <p class="hint">A faint watermark of your logo on invoices, receipts, quotations, letters and the rest — not only the Logo watermark and Bond watermark layouts.</p>
      <div class="design-grid">
        <?php
        $currentDesign = doc_template_key(['doc_template' => $b['doc_template'] ?? 'folio']);
        foreach (doc_templates() as $key => $info):
            ?>
          <label class="design-card">
            <input type="radio" name="doc_template" value="<?= h($key) ?>" <?= $currentDesign === $key ? 'checked' : '' ?>>
            <div class="design-mini mini-<?= h($key) ?>" aria-hidden="true"></div>
            <strong><?= h($info['name']) ?></strong>
            <span><?= h($info['blurb']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <h2 style="margin-top:28px"><?= icon('letter') ?>Correspondence copy</h2>
      <p class="lede">The note itself stays 100% editable when you write it. These are starting texts only. Put <code>{company}</code> where the company name should appear. Open one tab at a time — Save still stores every letter and email template.</p>
      <div class="tpl-tabs" data-tpl-tabs>
        <div class="tpl-tab-bar" data-tpl-tab-bar>
          <?php foreach ($letterTpls as $key => $tpl): ?>
            <button type="button" class="tpl-tab" data-tpl-tab="<?= h($key) ?>"><?= h($tpl['title']) ?></button>
          <?php endforeach; ?>
        </div>
        <div data-tpl-list>
          <?php foreach ($letterTpls as $key => $tpl): ?>
            <div class="tpl-edit" data-tpl-card data-tpl-panel="<?= h($key) ?>">
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
      </div>
      <p class="hint" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
        <button class="btn ghost sm" type="button" data-add-template><?= icon('plus', 14) ?>Add template</button>
        Clear a custom title and save to remove it.
      </p>
      <div class="actions sticky-save">
        <button class="btn" type="submit"><?= icon('check') ?>Save settings</button>
      </div>
    </section>
    </form>
  </div>
</div>
<template id="tpl-proto">
  <div class="tpl-edit" data-tpl-card data-tpl-panel="__KEY__">
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
<?php if ($showWelcome && !is_acting_admin()): ?>
  <div class="welcome-pop" role="dialog" aria-labelledby="welcome-title">
    <div class="welcome-pop-card">
      <h2 id="welcome-title">Finish company branding</h2>
      <p>Welcome to your desk. Open Settings on this page and set the company name, logo, colours, TIN, bank and currency so every sheet leaves in your brand.</p>
      <p>If you need help, call <?= h(implode(' or ', product_phones())) ?> and a Vellisys agent will walk you through it.</p>
      <div class="actions">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="dismiss_welcome">
          <button class="btn" type="submit"><?= icon('check', 16) ?>I'll finish branding</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
