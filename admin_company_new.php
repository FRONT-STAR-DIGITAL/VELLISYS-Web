<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$signupId = (int) ($_GET['signup'] ?? post('signup_id'));
$signup = $signupId ? db_one('SELECT * FROM signups WHERE id = ?', 'i', [$signupId]) : null;

$pref = static function (string $key, string $fallback = '') use ($signup): string {
    $posted = post($key);
    if ($posted !== '') {
        return $posted;
    }
    if (!$signup) {
        return $fallback;
    }
    return match ($key) {
        'name' => (string) $signup['company'],
        'user_name' => (string) $signup['name'],
        'user_email' => (string) $signup['email'],
        'phone' => (string) $signup['phone'],
        'notes' => 'Website sign-up. Contact ' . $signup['name'] . ' on ' . $signup['phone'] . ' / ' . $signup['email'] . '.',
        default => $fallback,
    };
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    csrf_check();
    $made = platform_create_company($signupId ?: null);
    if (empty($made['ok'])) {
        $error = (string) ($made['error'] ?? 'Could not create that company.');
        if (!empty($made['id'])) {
            flash($error, 'err');
            redirect('admin_company.php?id=' . (int) $made['id']);
        }
    } else {
        $welcomeNote = '';
        if (!empty($made['send_welcome'])) {
            $created = db_one('SELECT * FROM companies WHERE id = ?', 'i', [(int) $made['id']]);
            $welcome = send_welcome_email(
                $created ?: ['id' => $made['id'], 'name' => $made['name']],
                ['name' => post('user_name'), 'email' => $made['email']],
                (string) $made['password'],
                (int) $user['id']
            );
            $welcomeNote = !empty($welcome['ok'])
                ? ' Welcome mail sent from ' . product_email() . '.'
                : ' Welcome mail queued from ' . product_email() . '.';
        }
        flash(
            $made['name'] . ' is on the books as ' . $made['status'] . '. Desk login ' . $made['email']
            . ' · password ' . $made['password'] . '.' . $welcomeNote
        );
        redirect('admin_company.php?id=' . (int) $made['id']);
    }
}

$fromSignup = (bool) $signup;
layout_admin_start($fromSignup ? 'Onboard company' : 'New company', $user);
$mailPreset = mail_provider_presets()[post('mail_provider') ?: 'hostinger'] ?? mail_provider_presets()['hostinger'];
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?><?= $fromSignup ? 'Onboard ' . h($signup['company']) : 'New company' ?></h1>
    <p class="lede"><?= $fromSignup
        ? 'From the website sign-up. Fill what you have, create the desk, and issue the first login.'
        : 'Create a company client from scratch. Type the desk in by hand - no website sign-up needed.' ?></p>
  </div>
  <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>">Back to companies</a>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<form class="card form-wide create-company" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <?php if ($signupId): ?><input type="hidden" name="signup_id" value="<?= (int) $signupId ?>"><?php endif; ?>

  <div class="card-head"><h2><?= icon('building', 16) ?>Company and desk login</h2></div>
  <p class="lede" style="padding:0 22px">Legal name, first admin, how many seats, and whether this desk is live today.</p>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="name">Company name</label>
      <input id="name" name="name" required value="<?= h($pref('name')) ?>" placeholder="Harbour &amp; Co.">
    </div>
    <div>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php $statusPick = post('status') ?: 'onboarding'; ?>
        <option value="onboarding" <?= $statusPick === 'onboarding' ? 'selected' : '' ?>>Onboarding</option>
        <option value="live" <?= $statusPick === 'live' ? 'selected' : '' ?>>Live</option>
      </select>
    </div>
    <div>
      <label for="currency-pick">Currency</label>
      <?php currency_field('currency', 'currency', post('currency') ?: 'USD'); ?>
      <p class="hint">The company admin can change this later in Settings.</p>
    </div>
    <div>
      <label for="user_limit">Logins allowed</label>
      <select id="user_limit" name="user_limit">
        <?php $limitPick = clamp_user_limit((int) (post('user_limit') ?: 3)); ?>
        <?php for ($n = 1; $n <= 3; $n++): ?>
          <option value="<?= $n ?>" <?= $limitPick === $n ? 'selected' : '' ?>><?= $n ?> <?= $n === 1 ? '(admin only)' : ($n === 2 ? '(admin + 1)' : '(admin + 2)') ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label for="user_name">Desk admin</label>
      <input id="user_name" name="user_name" required value="<?= h($pref('user_name')) ?>" placeholder="Accounts">
    </div>
    <div>
      <label for="user_title">Title</label>
      <input id="user_title" name="user_title" value="<?= h(post('user_title') ?: 'Administrator') ?>">
    </div>
    <div>
      <label for="user_email">Desk email</label>
      <input id="user_email" name="user_email" type="email" required value="<?= h($pref('user_email')) ?>" placeholder="accounts@company.com">
    </div>
    <div>
      <label for="user_password">Temporary password</label>
      <input id="user_password" name="user_password" value="<?= h(post('user_password')) ?>" placeholder="Leave blank to generate" autocomplete="new-password">
      <p class="hint">At least 8 characters, or leave blank and Vellisys generates one. Shown again after save.</p>
    </div>
  </div>

  <div class="card-head" style="margin-top:8px"><h2><?= icon('palette', 16) ?>Stationery</h2></div>
  <p class="lede" style="padding:0 22px">Logo, colours, TIN, bank. This prints on every sheet. Skip what you do not have yet.</p>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="logo">Logo</label>
      <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
      <p class="hint">PNG, JPG, SVG, GIF or WebP. Under 2 MB. Optional.</p>
    </div>
    <div>
      <label for="prefix">Document prefix</label>
      <input id="prefix" name="prefix" maxlength="8" value="<?= h(post('prefix')) ?>" placeholder="Auto from name">
    </div>
    <div>
      <label for="brand_color">Primary colour</label>
      <div class="color-row" data-color-pair data-color-role="primary">
        <input type="color" name="brand_color" value="<?= h(parse_hex_color(post('brand_color'), '#1E4EFF')) ?>" data-color-picker>
        <input type="text" maxlength="7" value="<?= h(parse_hex_color(post('brand_color'), '#1E4EFF')) ?>" data-color-hex>
      </div>
    </div>
    <div>
      <label for="brand_accent">Accent colour</label>
      <div class="color-row" data-color-pair data-color-role="accent">
        <input type="color" name="brand_accent" value="<?= h(parse_hex_color(post('brand_accent'), '#C6A15B')) ?>" data-color-picker>
        <input type="text" maxlength="7" value="<?= h(parse_hex_color(post('brand_accent'), '#C6A15B')) ?>" data-color-hex>
      </div>
    </div>
    <div>
      <label for="brand_deep">Deep colour</label>
      <div class="color-row" data-color-pair data-color-role="deep">
        <input type="color" name="brand_deep" value="<?= h(parse_hex_color(post('brand_deep'), '#08143A')) ?>" data-color-picker>
        <input type="text" maxlength="7" value="<?= h(parse_hex_color(post('brand_deep'), '#08143A')) ?>" data-color-hex>
      </div>
    </div>
    <div>
      <label for="tagline">Tagline</label>
      <input id="tagline" name="tagline" value="<?= h(post('tagline')) ?>">
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h(post('tin')) ?>">
    </div>
    <div>
      <label for="vat_no">VAT number</label>
      <input id="vat_no" name="vat_no" value="<?= h(post('vat_no')) ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h($pref('phone')) ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h(post('city')) ?>">
    </div>
    <div>
      <label for="website">Website</label>
      <input id="website" name="website" value="<?= h(post('website')) ?>" placeholder="https://">
    </div>
  </div>
  <div style="padding:0 22px">
    <label for="address">Address</label>
    <input id="address" name="address" value="<?= h(post('address')) ?>">
  </div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="bank_name">Bank</label>
      <input id="bank_name" name="bank_name" value="<?= h(post('bank_name')) ?>">
    </div>
    <div>
      <label for="account_name">Account name</label>
      <input id="account_name" name="account_name" value="<?= h(post('account_name')) ?>" placeholder="Same as company if blank">
    </div>
    <div>
      <label for="account_number">Account number</label>
      <input id="account_number" name="account_number" value="<?= h(post('account_number')) ?>">
    </div>
  </div>
  <div style="padding:0 22px">
    <label for="payment_note">Payment note on invoices</label>
    <input id="payment_note" name="payment_note" value="<?= h(post('payment_note')) ?>" placeholder="Make payment to the company.">
    <label for="invoice_comments">Invoice comments</label>
    <textarea id="invoice_comments" name="invoice_comments" rows="3"><?= h(post('invoice_comments')) ?></textarea>
  </div>

  <div style="padding:8px 22px 0">
    <?php render_desk_kinds_fields(); ?>
  </div>

  <div class="card-head" style="margin-top:8px"><h2><?= icon('calendar', 16) ?>Paid term</h2></div>
  <p class="lede" style="padding:0 22px">Optional. Set how long they have paid for and the fee you collected. Leave the number at 0 to add this later.</p>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="paid_from">Paid from</label>
      <input id="paid_from" name="paid_from" type="date" value="<?= h(post('paid_from') ?: date('Y-m-d')) ?>">
    </div>
    <div>
      <label for="paid_term">Number</label>
      <input id="paid_term" name="paid_term" type="number" min="0" max="120" value="<?= h(post('paid_term') !== '' ? post('paid_term') : '0') ?>">
    </div>
    <div>
      <label for="paid_unit">Unit</label>
      <select id="paid_unit" name="paid_unit">
        <option value="months" <?= post('paid_unit') !== 'years' ? 'selected' : '' ?>>Months</option>
        <option value="years" <?= post('paid_unit') === 'years' ? 'selected' : '' ?>>Years</option>
      </select>
    </div>
    <div>
      <label for="fee_amount">Fee for this term</label>
      <input id="fee_amount" name="fee_amount" inputmode="decimal" value="<?= h(post('fee_amount')) ?>" placeholder="0">
    </div>
    <div>
      <label for="fee_paid">Amount paid</label>
      <input id="fee_paid" name="fee_paid" inputmode="decimal" value="<?= h(post('fee_paid')) ?>" placeholder="Same as fee if left blank">
    </div>
    <div>
      <label for="fee_currency">Fee currency</label>
      <?php currency_field('fee_currency', 'fee_currency', post('fee_currency') ?: (post('currency') ?: 'USD')); ?>
    </div>
  </div>

  <div class="card-head" style="margin-top:8px"><h2><?= icon('send', 16) ?>Sending mailbox</h2></div>
  <p class="lede" style="padding:0 22px">Optional. Hostinger or Titan address this desk will send from. The company cannot edit the password.</p>
  <div class="form-grid" style="padding:0 22px" data-mail-box>
    <div>
      <label for="mail_provider">Mail type</label>
      <select id="mail_provider" name="mail_provider" data-mail-provider>
        <?php foreach (mail_provider_presets() as $key => $preset): ?>
          <option value="<?= h($key) ?>" <?= (post('mail_provider') ?: 'hostinger') === $key ? 'selected' : '' ?>><?= h($preset['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="mail_email">Mailbox</label>
      <input id="mail_email" name="mail_email" type="email" value="<?= h(post('mail_email')) ?>" placeholder="accounts@company.com">
    </div>
    <div>
      <label for="mail_password">Mailbox password</label>
      <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" value="<?= h(post('mail_password')) ?>">
    </div>
    <div>
      <label for="mail_from_name">From name</label>
      <input id="mail_from_name" name="mail_from_name" value="<?= h(post('mail_from_name')) ?>" placeholder="Company name if blank">
    </div>
    <div>
      <label for="smtp_host">SMTP host</label>
      <input id="smtp_host" name="smtp_host" data-mail-field="smtp_host" value="<?= h(post('smtp_host') ?: $mailPreset['smtp_host']) ?>">
    </div>
    <div>
      <label for="smtp_port">SMTP port</label>
      <input id="smtp_port" name="smtp_port" type="number" data-mail-field="smtp_port" value="<?= h(post('smtp_port') !== '' ? post('smtp_port') : (string) $mailPreset['smtp_port']) ?>">
    </div>
    <div>
      <label for="smtp_secure">SMTP security</label>
      <select id="smtp_secure" name="smtp_secure" data-mail-field="smtp_secure">
        <?php $sec = post('smtp_secure') ?: $mailPreset['smtp_secure']; ?>
        <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= $sec === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="pop_host">POP host</label>
      <input id="pop_host" name="pop_host" data-mail-field="pop_host" value="<?= h(post('pop_host') ?: $mailPreset['pop_host']) ?>">
    </div>
    <div>
      <label for="pop_port">POP port</label>
      <input id="pop_port" name="pop_port" type="number" data-mail-field="pop_port" value="<?= h(post('pop_port') !== '' ? post('pop_port') : (string) $mailPreset['pop_port']) ?>">
    </div>
    <div>
      <label for="imap_host">IMAP host</label>
      <input id="imap_host" name="imap_host" data-mail-field="imap_host" value="<?= h(post('imap_host') ?: $mailPreset['imap_host']) ?>">
    </div>
    <div>
      <label for="imap_port">IMAP port</label>
      <input id="imap_port" name="imap_port" type="number" data-mail-field="imap_port" value="<?= h(post('imap_port') !== '' ? post('imap_port') : (string) $mailPreset['imap_port']) ?>">
    </div>
    <script type="application/json" data-mail-presets><?= json_encode(mail_provider_presets(), JSON_UNESCAPED_SLASHES) ?></script>
  </div>

  <div class="card-head" style="margin-top:8px"><h2><?= icon('pencil', 16) ?>Notes</h2></div>
  <div style="padding:0 22px 8px">
    <label for="notes">Internal notes</label>
    <textarea id="notes" name="notes" rows="3" placeholder="Call notes, who you spoke to, what they bought."><?= h($pref('notes')) ?></textarea>
    <label class="kinds-opt" style="margin:12px 0">
      <input type="checkbox" name="send_welcome" value="1" <?= post('send_welcome') !== '' || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
      <span>Email the desk login from <?= h(product_email()) ?></span>
    </label>
  </div>
  <div class="actions" style="margin:8px 22px 22px">
    <button class="btn" type="submit"><?= icon('check') ?>Create company</button>
    <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
