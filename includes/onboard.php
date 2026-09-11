<?php
declare(strict_types=1);

function order_by_onboard_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }
    return db_one('SELECT * FROM website_orders WHERE onboard_token = ?', 's', [$token]) ?: null;
}

function onboard_url(array $order): string
{
    $token = trim((string) ($order['onboard_token'] ?? ''));
    if ($token === '') {
        return absolute_url('index.php#pricing');
    }
    return absolute_url('register.php?t=' . rawurlencode($token));
}

function provision_paid_order(array $order): array
{
    $id = (int) ($order['id'] ?? 0);
    if ($id < 1 || ($order['status'] ?? '') !== 'paid') {
        return $order;
    }
    $fresh = db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$id]) ?: $order;
    if (trim((string) ($fresh['onboard_token'] ?? '')) !== '' && (int) ($fresh['company_id'] ?? 0) > 0) {
        return $fresh;
    }

    $cid = (int) ($fresh['company_id'] ?? 0);
    if ($cid < 1) {
        $signupId = (int) ($fresh['signup_id'] ?? 0);
        if ($signupId > 0) {
            $signup = db_one('SELECT company_id FROM signups WHERE id = ?', 'i', [$signupId]);
            $cid = (int) ($signup['company_id'] ?? 0);
        }
    }
    if ($cid < 1) {
        $cid = create_company_from_paid_order($fresh);
    }
    $token = trim((string) ($fresh['onboard_token'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
    }
    db_exec(
        'UPDATE website_orders SET company_id=?, onboard_token=? WHERE id=?',
        'isi',
        [$cid > 0 ? $cid : null, $token, $id]
    );
    if ($cid > 0) {
        company_mark_onboard_step($cid, 'package_paid');
        company_mark_onboard_step($cid, 'paid_term');
        $signupId = (int) ($fresh['signup_id'] ?? 0);
        if ($signupId > 0) {
            db_exec("UPDATE signups SET status='onboarded', company_id=? WHERE id=?", 'ii', [$cid, $signupId]);
        }
    }
    return db_one('SELECT * FROM website_orders WHERE id = ?', 'i', [$id]) ?: $fresh;
}

function create_company_from_paid_order(array $order): int
{
    $name = trim((string) ($order['company'] ?? '')) ?: 'New company';
    $plan = pricing_package((string) ($order['plan'] ?? ''));
    $seats = clamp_user_limit((int) ($plan['seats'] ?? 1));
    $from = desk_now()->format('Y-m-d');
    $expires = compute_expiry_date($from, 1, 'years') ?: $from;
    $fee = (float) ($order['amount'] ?? 0);
    $ccy = strtoupper((string) ($order['currency'] ?? 'USD'));
    if (strlen($ccy) !== 3) {
        $ccy = 'USD';
    }
    $notes = 'Paid website checkout. ' . ($plan['name'] ?? $order['plan'] ?? 'desk')
        . ' · ' . $ccy . ' ' . $fee
        . ' · ' . trim((string) ($order['email'] ?? ''));
    $cid = (int) db_exec(
        'INSERT INTO companies (name, status, plan, notes, enabled_kinds, custom_doc, user_limit, paid_term, paid_unit, paid_from, expires_at, fee_amount, fee_paid, fee_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'ssssssiisssdds',
        [
            $name,
            'onboarding',
            'sme',
            $notes,
            json_encode(default_enabled_kinds(), JSON_UNESCAPED_UNICODE),
            json_encode(default_custom_doc(), JSON_UNESCAPED_UNICODE),
            $seats,
            1,
            'years',
            $from,
            $expires,
            $fee,
            $fee,
            $ccy,
        ]
    );
    if ($cid < 1) {
        return 0;
    }
    $email = strtolower(trim((string) ($order['email'] ?? '')));
    $phone = trim((string) ($order['phone'] ?? ''));
    $city = trim((string) ($order['city'] ?? ''));
    db_exec(
        'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, plan, currency)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'isssssssssssssssssssss',
        [
            $cid,
            $name,
            '',
            '',
            '',
            '',
            $city,
            $phone,
            $email,
            '',
            '',
            $name,
            '',
            '#1E4EFF',
            '#C6A15B',
            '#08143A',
            '',
            prefix_from_name($name),
            'Make payment to ' . $name . '.',
            "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.",
            'sme',
            $ccy === 'UGX' ? 'UGX' : $ccy,
        ]
    );
    return $cid;
}

function complete_self_onboard(array $order, array $fields): array
{
    $cid = (int) ($order['company_id'] ?? 0);
    if ($cid < 1 || ($order['status'] ?? '') !== 'paid') {
        return ['ok' => false, 'error' => 'This onboarding link is not valid. Pay for a package first.'];
    }
    $existing = db_one("SELECT id FROM users WHERE company_id = ? AND role = 'admin'", 'i', [$cid]);
    if ($existing) {
        return ['ok' => false, 'error' => 'This desk already has an admin login. Sign in with the email you set.', 'ready' => true];
    }
    $made = create_desk_user($cid, [
        'name' => $fields['name'] ?? '',
        'email' => $fields['email'] ?? '',
        'password' => $fields['password'] ?? '',
        'job_title' => 'Administrator',
        'access' => 'admin',
    ]);
    if (empty($made['ok'])) {
        return ['ok' => false, 'error' => (string) ($made['error'] ?? 'Could not create that login.')];
    }
    company_mark_onboard_step($cid, 'credentials_set');
    company_mark_onboard_step($cid, 'desk_login');
    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) ?: ['id' => $cid, 'name' => $order['company'] ?? ''];
    notify_self_onboard_done($company, $made, $order);
    return ['ok' => true, 'email' => $made['email'], 'company' => $company];
}

function finish_member_first_login(array $user): bool
{
    if (($user['role'] ?? '') === 'platform') {
        return false;
    }
    $uid = (int) ($user['id'] ?? 0);
    $cid = (int) ($user['company_id'] ?? 0);
    if ($uid < 1 || $cid < 1) {
        return false;
    }
    if (!function_exists('db_has_column') || !db_has_column(db(), 'users', 'first_login_at')) {
        return false;
    }
    $row = db_one('SELECT first_login_at FROM users WHERE id = ?', 'i', [$uid]);
    if (!$row || trim((string) ($row['first_login_at'] ?? '')) !== '') {
        return false;
    }
    db_exec('UPDATE users SET first_login_at = NOW() WHERE id = ?', 'i', [$uid]);
    company_mark_onboard_step($cid, 'first_login');
    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) ?: ['id' => $cid, 'name' => ''];
    notify_client_first_login($company, $user);
    $_SESSION['branding_welcome'] = 1;
    return true;
}

function mark_branding_saved(int $companyId, array $user): void
{
    if ($companyId < 1) {
        return;
    }
    $row = db_one('SELECT onboard_steps FROM companies WHERE id = ?', 'i', [$companyId]);
    if (company_onboard_done($row ?: [], 'branding_saved')) {
        return;
    }
    company_mark_onboard_step($companyId, 'branding_saved');
    if (($user['role'] ?? '') === 'platform') {
        return;
    }
    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$companyId]) ?: ['id' => $companyId, 'name' => ''];
    notify_branding_saved($company, $user);
}
