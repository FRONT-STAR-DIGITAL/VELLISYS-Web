<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';
$signupId = (int) ($_GET['signup'] ?? post('signup_id'));
$signup = $signupId ? db_one('SELECT * FROM signups WHERE id = ?', 'i', [$signupId]) : null;
$showNew = isset($_GET['new']) || $signup || ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create');

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
    $name = post('name');
    $currency = posted_currency('currency', 'USD');
    $userName = post('user_name');
    $userEmail = strtolower(post('user_email'));
    $password = post('user_password') ?: 'folio2026';
    $color = parse_hex_color(post('brand_color'), '#82B440');
    $accent = parse_hex_color(post('brand_accent'), '#C6A15B');
    $deep = parse_hex_color(post('brand_deep'), '#1F3A12');
    if ($name === '' || $userName === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Company name, desk user and a valid email are required.';
        $showNew = true;
    } elseif (db_one('SELECT id FROM users WHERE email = ?', 's', [$userEmail])) {
        $error = 'That email already has a Vellisys login.';
        $showNew = true;
    } else {
        $kindsPosted = $_POST['enabled_kinds'] ?? [];
        if (!is_array($kindsPosted) || $kindsPosted === []) {
            $error = 'Select at least one document type this company will use.';
            $showNew = true;
        } else {
        $cid = db_exec(
            'INSERT INTO companies (name, status, plan, notes, enabled_kinds, custom_doc, user_limit) VALUES (?,?,?,?,?,?,?)',
            'ssssssi',
            [$name, 'onboarding', 'sme', post('notes') ?: null, posted_enabled_kinds(), posted_custom_doc(), clamp_user_limit((int) post('user_limit') ?: 3)]
        );
        $prefix = strtoupper(post('prefix') ?: prefix_from_name($name));
        db_exec(
            'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, plan, currency)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'isssssssssssssssssssss',
            [
                $cid,
                $name,
                post('tagline'),
                post('tin'),
                post('vat_no'),
                post('address'),
                post('city'),
                post('phone'),
                $userEmail,
                post('website'),
                post('bank_name'),
                $name,
                post('account_number'),
                $color,
                $accent,
                $deep,
                'assets/img/ofagros-logo.png',
                $prefix,
                'Make payment to ' . $name . '.',
                "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.",
                'sme',
                $currency,
            ]
        );
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_exec(
            'INSERT INTO users (name, job_title, email, password_hash, role, access, company_id) VALUES (?,?,?,?,?,?,?)',
            'ssssssi',
            [$userName, post('user_title') ?: 'Administrator', $userEmail, $hash, 'admin', 'admin', $cid]
        );
        if ($signupId && $signup) {
            db_exec("UPDATE signups SET status = 'onboarded', company_id = ? WHERE id = ?", 'ii', [$cid, $signupId]);
        }
        $created = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]);
        $welcome = send_welcome_email(
            $created ?: ['id' => $cid, 'name' => $name],
            ['name' => $userName, 'email' => $userEmail],
            $password,
            (int) $user['id']
        );
        flash(
            $name . ' is ready. Desk login ' . $userEmail
            . ($welcome['ok'] ? '. Welcome mail sent from ' . product_email() . '.' : '. Welcome mail queued from ' . product_email() . '.')
        );
        redirect('admin_company.php?id=' . $cid);
        }
    }
}

$companies = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users,
            (SELECT COUNT(*) FROM documents d WHERE d.company_id = c.id) AS docs
     FROM companies c ORDER BY c.id DESC'
);

layout_admin_start('Companies', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?>Companies</h1>
    <p class="lede">Create a desk, issue the first login, set stationery, then mark the company live. Website registrations wait on Sign-ups.</p>
  </div>
  <a class="btn" href="<?= h(url('admin_companies.php?new=1')) ?>"><?= icon('plus') ?>New company</a>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if ($showNew): ?>
<form class="card form-wide" method="post" style="margin-bottom:24px">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <?php if ($signupId): ?><input type="hidden" name="signup_id" value="<?= (int) $signupId ?>"><?php endif; ?>
  <h2 style="margin:18px 0 4px"><?= $signup ? 'Onboard ' . h($signup['company']) : 'Onboard a company' ?></h2>
  <p class="lede"><?= $signup ? 'From the website sign-up. This creates the company, stationery defaults, and the first desk login.' : 'This creates the company, stationery defaults, and the first desk login.' ?></p>
  <div class="form-grid">
    <div>
      <label for="name">Company name</label>
      <input id="name" name="name" required value="<?= h($pref('name')) ?>">
    </div>
    <div>
      <label for="currency">Currency</label>
      <?php currency_field('currency', 'currency', post('currency') ?: 'USD'); ?>
      <p class="hint">The company types their billing currency later if this is wrong - UGX, KES, EUR, USD…</p>
    </div>
    <div>
      <label for="user_name">First user (admin)</label>
      <input id="user_name" name="user_name" required value="<?= h($pref('user_name')) ?>" placeholder="Accounts">
    </div>
    <div>
      <label for="user_title">Title</label>
      <input id="user_title" name="user_title" value="<?= h(post('user_title') ?: 'Administrator') ?>">
    </div>
    <div>
      <label for="user_email">Desk email</label>
      <input id="user_email" name="user_email" type="email" required value="<?= h($pref('user_email')) ?>">
    </div>
    <div>
      <label for="user_password">Temporary password</label>
      <input id="user_password" name="user_password" value="<?= h(post('user_password') ?: 'folio2026') ?>">
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
      <label for="prefix">Document prefix</label>
      <input id="prefix" name="prefix" maxlength="8" value="<?= h(post('prefix')) ?>" placeholder="OFG">
    </div>
    <div>
      <label for="brand_color">Primary colour</label>
      <div class="color-row" data-color-pair data-color-role="primary">
        <input type="color" name="brand_color" value="<?= h(parse_hex_color(post('brand_color'), '#82B440')) ?>" data-color-picker>
        <input type="text" maxlength="7" value="<?= h(parse_hex_color(post('brand_color'), '#82B440')) ?>" data-color-hex>
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
        <input type="color" name="brand_deep" value="<?= h(parse_hex_color(post('brand_deep'), '#1F3A12')) ?>" data-color-picker>
        <input type="text" maxlength="7" value="<?= h(parse_hex_color(post('brand_deep'), '#1F3A12')) ?>" data-color-hex>
      </div>
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h(post('tin')) ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h($pref('phone')) ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h(post('city') ?: $pref('city')) ?>" placeholder="City">
    </div>
  </div>
  <label for="address">Address</label>
  <input id="address" name="address" value="<?= h(post('address')) ?>">
  <label for="tagline">Tagline</label>
  <input id="tagline" name="tagline" value="<?= h(post('tagline')) ?>">
  <?php render_desk_kinds_fields(); ?>
  <label for="notes">Internal notes</label>
  <textarea id="notes" name="notes" rows="3"><?= h($pref('notes')) ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Create company</button>
    <a class="btn ghost" href="<?= h(url('admin_companies.php')) ?>">Cancel</a>
  </div>
</form>
<?php endif; ?>

<div class="card">
  <?php if (!$companies): ?>
    <p class="empty">No companies yet. <a href="<?= h(url('admin_companies.php?new=1')) ?>">Onboard the first one</a>.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Company</th>
          <th>Status</th>
          <th>Paid term</th>
          <th>Expiry</th>
          <th>Paid</th>
          <th>Balance</th>
          <th>Users</th>
          <th>Documents</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr>
            <td><a href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><strong><?= h($c['name']) ?></strong></a></td>
            <td><span class="pill<?= $c['status'] === 'live' ? '' : ($c['status'] === 'suspended' ? ' bad' : ' warn') ?>"><?= h($c['status']) ?></span></td>
            <td><?= h(company_term_label($c)) ?></td>
            <td class="<?= company_expiry_state($c) === 'expired' ? 'expiry-expired' : (company_expiry_state($c) === 'soon' ? 'expiry-soon' : '') ?>"><?= h(company_remaining_phrase($c)) ?></td>
            <td class="mono"><?= company_fee_paid($c) > 0 ? h(money(company_fee_paid($c), company_fee_currency($c))) : '—' ?></td>
            <td class="mono"><?= company_fee_balance($c) > 0 ? h(money(company_fee_balance($c), company_fee_currency($c))) : '—' ?></td>
            <td class="mono"><?= (int) $c['users'] ?> / <?= (int) company_user_limit($c) ?></td>
            <td class="mono"><?= (int) $c['docs'] ?></td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm" href="<?= h(url('admin_company.php?id=' . $c['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
                <a class="btn ghost sm" href="<?= h(url('admin_desk.php?id=' . $c['id'])) ?>"><?= icon('desk', 14) ?>Desk</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
