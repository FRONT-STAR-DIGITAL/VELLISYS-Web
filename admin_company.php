<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$id = (int) ($_GET['id'] ?? post('id'));
$company = $id ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
if (!$company) {
    flash('Company not found.', 'err');
    redirect('admin_companies.php');
}
$brand = branding_for($id);
$members = db_all('SELECT id, name, email, created_at FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'profile') {
        $status = post('status') ?: 'onboarding';
        if (!in_array($status, ['onboarding', 'live', 'suspended'], true)) {
            $status = 'onboarding';
        }
        $name = post('name') ?: $company['name'];
        db_exec('UPDATE companies SET name=?, status=?, notes=? WHERE id=?', 'sssi', [$name, $status, post('notes') ?: null, $id]);
        db_exec('UPDATE branding SET name=? WHERE company_id=?', 'si', [$name, $id]);
        flash('Company profile saved.');
        redirect('admin_company.php?id=' . $id);
    }
    if ($action === 'branding') {
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
                $fname = 'logo-' . $id . '-' . date('YmdHis') . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $fname)) {
                    $logoPath = 'uploads/logos/' . $fname;
                } else {
                    $error = 'Could not save the logo file.';
                }
            }
        }
        if ($error === '') {
            db_exec(
                'UPDATE branding SET tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, brand_accent=?, brand_deep=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, currency=?, fx_ugx_per_usd=? WHERE company_id=?',
                'ssssssssssssssssssdi',
                [
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
                    strtoupper(post('prefix') ?: prefix_from_name($company['name'])),
                    post('payment_note'),
                    post('invoice_comments'),
                    strtoupper(post('currency') ?: 'UGX') === 'USD' ? 'USD' : 'UGX',
                    parse_fx_rate(post('fx_ugx_per_usd')),
                    $id,
                ]
            );
            flash('Stationery saved for ' . $company['name'] . '.');
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'add_user') {
        $n = post('user_name');
        $e = strtolower(post('user_email'));
        $p = post('user_password') ?: 'folio2026';
        if ($n === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $error = 'Name and a valid email are required.';
        } elseif (db_one('SELECT id FROM users WHERE email = ?', 's', [$e])) {
            $error = 'That email already has a Vellisys login.';
        } else {
            $hash = password_hash($p, PASSWORD_DEFAULT);
            db_exec('INSERT INTO users (name, email, password_hash, role, company_id) VALUES (?,?,?,?,?)', 'ssssi', [$n, $e, $hash, 'member', $id]);
            flash('Desk login created for ' . $e . '.');
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'go_live') {
        db_exec("UPDATE companies SET status='live' WHERE id=?", 'i', [$id]);
        flash($company['name'] . ' is live.');
        redirect('admin_company.php?id=' . $id);
    }
    if ($action === 'term') {
        $term = (int) post('paid_term');
        $unit = post('paid_unit') === 'years' ? 'years' : 'months';
        $from = post('paid_from');
        if ($term < 0) {
            $term = 0;
        }
        if ($unit === 'years' && $term > 20) {
            $term = 20;
        }
        if ($unit === 'months' && $term > 120) {
            $term = 120;
        }
        if ($term === 0) {
            db_exec(
                'UPDATE companies SET paid_term=0, paid_unit=?, paid_from=NULL, expires_at=NULL, renewal_notice_sent_at=NULL WHERE id=?',
                'si',
                [$unit, $id]
            );
            flash('Paid term cleared for ' . $company['name'] . '.');
            redirect('admin_company.php?id=' . $id);
        }
        if ($from === '' || !DateTime::createFromFormat('Y-m-d', $from)) {
            $from = date('Y-m-d');
        }
        $expires = compute_expiry_date($from, $term, $unit);
        if (!$expires) {
            $error = 'Could not calculate the expiry date. Check the start date and term.';
        } else {
            db_exec(
                'UPDATE companies SET paid_term=?, paid_unit=?, paid_from=?, expires_at=?, renewal_notice_sent_at=NULL WHERE id=?',
                'isssi',
                [$term, $unit, $from, $expires, $id]
            );
            flash($company['name'] . ' is paid for ' . $term . ' ' . $unit . ', until ' . format_date($expires) . '.');
            redirect('admin_company.php?id=' . $id);
        }
    }
}

$company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
$brand = branding_for($id);
$members = db_all('SELECT id, name, email, created_at FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]);
$partyCount = (int) (db_one('SELECT COUNT(*) c FROM parties WHERE company_id = ?', 'i', [$id])['c'] ?? 0);
$docCount = (int) (db_one('SELECT COUNT(*) c FROM documents WHERE company_id = ?', 'i', [$id])['c'] ?? 0);
$hasLogo = !empty($brand['logo_path']);
$hasColour = !empty($brand['brand_color']);
$hasTin = !empty($brand['tin']);
$checks = [
    ['First desk login', count($members) > 0],
    ['Legal name and colour', $hasColour && !empty($brand['name'])],
    ['TIN on stationery', $hasTin],
    ['Logo uploaded', $hasLogo],
    ['At least one client', $partyCount > 0],
    ['A document issued', $docCount > 0],
    ['Marked live', ($company['status'] ?? '') === 'live'],
];

layout_admin_start($company['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?><?= h($company['name']) ?></h1>
    <p class="lede"><?= h(ucfirst((string) $company['status'])) ?> · <?= h($brand['currency'] ?? 'UGX') ?> · <?= count($members) ?> user<?= count($members) === 1 ? '' : 's' ?> · <?= h(company_term_label($company)) ?><?php if (company_expires_on($company)): ?> · <?= h(company_expiry_label($company)) ?><?php endif; ?></p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= h(url('admin_desk.php?id=' . $id)) ?>"><?= icon('desk') ?>Open desk</a>
    <?php if ($company['status'] !== 'live'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="go_live">
        <button class="btn ghost" type="submit"><?= icon('check') ?>Mark live</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="desk-grid">
  <div class="card">
    <div class="card-head"><h2><?= icon('check', 16) ?>Onboarding</h2></div>
    <ul class="checklist">
      <?php foreach ($checks as [$label, $ok]): ?>
        <li>
          <span class="<?= $ok ? 'ok' : 'wait' ?>"><?= $ok ? icon('check', 16) : icon('alert', 16) ?></span>
          <?= h($label) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('user', 16) ?>Desk logins</h2></div>
    <?php if (!$members): ?>
      <p class="empty">No users yet.</p>
    <?php else: ?>
      <table class="grid">
        <thead><tr><th>Name</th><th>Email</th></tr></thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <tr><td><?= h($m['name']) ?></td><td class="mono"><?= h($m['email']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <form class="form" method="post" style="padding-bottom:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="add_user">
      <label for="user_name">Add a desk user</label>
      <input id="user_name" name="user_name" required placeholder="Name">
      <label for="user_email">Email</label>
      <input id="user_email" name="user_email" type="email" required>
      <label for="user_password">Temporary password</label>
      <input id="user_password" name="user_password" value="folio2026">
      <div class="actions" style="margin-top:12px">
        <button class="btn sm" type="submit"><?= icon('plus', 14) ?>Create login</button>
      </div>
    </form>
  </div>
</div>

<form class="card form-wide" method="post" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="term">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Paid term</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="paid_from">Paid from</label>
      <input id="paid_from" name="paid_from" type="date" value="<?= h((string) ($company['paid_from'] ?: date('Y-m-d'))) ?>">
    </div>
    <div>
      <label for="paid_term">Number</label>
      <input id="paid_term" name="paid_term" type="number" min="0" max="120" value="<?= (int) ($company['paid_term'] ?? 0) ?>">
    </div>
    <div>
      <label for="paid_unit">Unit</label>
      <select id="paid_unit" name="paid_unit">
        <option value="months" <?= ($company['paid_unit'] ?? 'months') === 'months' ? 'selected' : '' ?>>Months</option>
        <option value="years" <?= ($company['paid_unit'] ?? '') === 'years' ? 'selected' : '' ?>>Years</option>
      </select>
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <p class="hint" style="margin:8px 0 12px">
      <?php if (company_expires_on($company)): ?>
        Current expiry <?= h(format_date($company['expires_at'])) ?> · <?= h(company_expiry_label($company)) ?>.
        Saving recalculates the end date. Set number to 0 to clear the term.
      <?php else: ?>
        Set how many months or years this client has paid for. Expiry is the start date plus that term. Reports list desks one month from that date so you can send a renewal letter.
      <?php endif; ?>
    </p>
    <div class="actions">
      <button class="btn" type="submit"><?= icon('check') ?>Save paid term</button>
      <a class="btn ghost" href="<?= h(url('admin_reports.php')) ?>"><?= icon('reports', 16) ?>Reports</a>
    </div>
  </div>
</form>

<form class="card form-wide" method="post" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="profile">
  <div class="card-head"><h2><?= icon('settings', 16) ?>Company</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="name">Legal name</label>
      <input id="name" name="name" required value="<?= h($company['name']) ?>">
    </div>
    <div>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach (['onboarding' => 'Onboarding', 'live' => 'Live', 'suspended' => 'Suspended'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= $company['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <label for="notes">Internal notes</label>
    <textarea id="notes" name="notes" rows="3"><?= h((string) $company['notes']) ?></textarea>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('check') ?>Save company</button>
    </div>
  </div>
</form>

<form class="card form-wide" method="post" enctype="multipart/form-data" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="branding">
  <div class="card-head"><h2><?= icon('palette', 16) ?>Stationery</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="tagline">Tagline</label>
      <input id="tagline" name="tagline" value="<?= h((string) ($brand['tagline'] ?? '')) ?>">
    </div>
    <div>
      <label for="prefix">Prefix</label>
      <input id="prefix" name="prefix" value="<?= h((string) ($brand['prefix'] ?? '')) ?>">
    </div>
    <div>
      <label for="currency">Currency</label>
      <select id="currency" name="currency">
        <?php foreach (currencies() as $code => $label): ?>
          <option value="<?= h($code) ?>" <?= ($brand['currency'] ?? 'UGX') === $code ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="fx_ugx_per_usd">1 USD equals</label>
      <div class="fx-row">
        <input id="fx_ugx_per_usd" name="fx_ugx_per_usd" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format((float) ($brand['fx_ugx_per_usd'] ?? 3700), 4, '.', ''), '0'), '.')) ?>">
        <span>UGX</span>
      </div>
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h((string) ($brand['tin'] ?? '')) ?>">
    </div>
    <div>
      <label for="vat_no">VAT / VRN</label>
      <input id="vat_no" name="vat_no" value="<?= h((string) ($brand['vat_no'] ?? '')) ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h((string) ($brand['phone'] ?? '')) ?>">
    </div>
    <div>
      <label for="email">Public email</label>
      <input id="email" name="email" type="email" value="<?= h((string) ($brand['email'] ?? '')) ?>">
    </div>
    <div>
      <label for="website">Website</label>
      <input id="website" name="website" value="<?= h((string) ($brand['website'] ?? '')) ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h((string) ($brand['city'] ?? '')) ?>">
    </div>
  </div>
  <div style="padding:0 22px">
    <label for="address">Address</label>
    <input id="address" name="address" value="<?= h((string) ($brand['address'] ?? '')) ?>">
    <div class="form-grid">
      <div>
        <label for="bank_name">Bank</label>
        <input id="bank_name" name="bank_name" value="<?= h((string) ($brand['bank_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="account_name">Account name</label>
        <input id="account_name" name="account_name" value="<?= h((string) ($brand['account_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="account_number">Account number</label>
        <input id="account_number" name="account_number" value="<?= h((string) ($brand['account_number'] ?? '')) ?>">
      </div>
      <div>
        <label for="brand_color">Primary</label>
        <div class="color-row" data-color-pair data-color-role="primary">
          <input type="color" id="brand_color" name="brand_color" value="<?= h(parse_hex_color($brand['brand_color'] ?? '', '#82B440')) ?>" data-color-picker>
          <input type="text" maxlength="7" value="<?= h(parse_hex_color($brand['brand_color'] ?? '', '#82B440')) ?>" data-color-hex>
        </div>
      </div>
      <div>
        <label for="brand_accent">Accent</label>
        <div class="color-row" data-color-pair data-color-role="accent">
          <input type="color" id="brand_accent" name="brand_accent" value="<?= h(parse_hex_color($brand['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-picker>
          <input type="text" maxlength="7" value="<?= h(parse_hex_color($brand['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-hex>
        </div>
      </div>
      <div>
        <label for="brand_deep">Deep</label>
        <div class="color-row" data-color-pair data-color-role="deep">
          <input type="color" id="brand_deep" name="brand_deep" value="<?= h(parse_hex_color($brand['brand_deep'] ?? '', '#1F3A12')) ?>" data-color-picker>
          <input type="text" maxlength="7" value="<?= h(parse_hex_color($brand['brand_deep'] ?? '', '#1F3A12')) ?>" data-color-hex>
        </div>
      </div>
    </div>
    <label for="logo">Logo</label>
    <input id="logo" name="logo" type="file" accept="image/*">
    <?php if (!empty($brand['logo_path'])): ?>
      <div class="logo-preview"><img src="<?= h(url($brand['logo_path'])) ?>" alt=""></div>
    <?php endif; ?>
    <label for="payment_note">Payment note</label>
    <textarea id="payment_note" name="payment_note" rows="3"><?= h((string) ($brand['payment_note'] ?? '')) ?></textarea>
    <label for="invoice_comments">Invoice comments</label>
    <textarea id="invoice_comments" name="invoice_comments" rows="4"><?= h((string) ($brand['invoice_comments'] ?? '')) ?></textarea>
    <div class="actions" style="margin-top:16px;padding-bottom:22px">
      <button class="btn" type="submit"><?= icon('check') ?>Save stationery</button>
    </div>
  </div>
</form>
<?php layout_end(); ?>
