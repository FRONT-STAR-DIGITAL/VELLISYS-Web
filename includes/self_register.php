<?php
declare(strict_types=1);

/** One-month Pro self-registration with email OTP (anti-spam). */

function self_register_session_key(): string
{
    return 'self_register';
}

function self_register_state(): array
{
    $raw = $_SESSION[self_register_session_key()] ?? null;
    return is_array($raw) ? $raw : [];
}

function self_register_save(array $state): void
{
    $_SESSION[self_register_session_key()] = $state;
}

function self_register_clear(): void
{
    unset($_SESSION[self_register_session_key()]);
}

function self_register_step(): string
{
    $state = self_register_state();
    $step = (string) ($state['step'] ?? 'email');
    return in_array($step, ['email', 'otp', 'details', 'password'], true) ? $step : 'email';
}

function self_register_otp_ttl_seconds(): int
{
    return 15 * 60;
}

function self_register_otp_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function self_register_normalize_email(string $email): string
{
    return strtolower(sanitize_public_text($email, 190));
}

function self_register_email_ok(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL)
        && !public_email_is_disposable($email)
        && !public_text_is_hostile($email);
}

function self_register_password_ok(string $password): bool
{
    if (strlen($password) < 8 || strlen($password) > 128) {
        return false;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $password) === 1) {
        return false;
    }
    return true;
}

function self_register_purge_old_otps(): void
{
    try {
        if (function_exists('folio_ensure_self_register_otps')) {
            folio_ensure_self_register_otps(db());
        }
        db_exec('DELETE FROM self_register_otps WHERE expires_at < DATE_SUB(NOW(), INTERVAL 2 DAY) OR (verified_at IS NOT NULL AND verified_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
    } catch (Throwable $e) {
        // ignore until migrate
    }
}

function self_register_email_taken(string $email): bool
{
    try {
        if (db_one('SELECT id FROM users WHERE email = ?', 's', [$email])) {
            return true;
        }
    } catch (Throwable $e) {
        return true;
    }
    return false;
}

/**
 * Prepare a fresh OTP (DB + session). Caller should email via folio_redirect_then
 * so the browser is not blocked by SMTP.
 *
 * @return array{ok:bool,error?:string,silent?:bool,email?:string,code?:string,otp_id?:int}
 */
function self_register_start_otp(string $email): array
{
    $email = self_register_normalize_email($email);
    if (!self_register_email_ok($email)) {
        return ['ok' => false, 'error' => 'Enter a real email address we can reach.'];
    }
    if (public_form_looks_like_spam(['email' => $email, 'name' => ''])) {
        return ['ok' => true, 'silent' => true];
    }
    if (self_register_email_taken($email)) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login. Sign in, or use another mailbox.'];
    }
    if (form_rate_blocked('self-otp-email:' . $email, 3, 3600) || form_rate_blocked('self-otp-ip', 8, 3600)) {
        return ['ok' => false, 'error' => 'Please wait before requesting another code.'];
    }

    if (!function_exists('google_otp_mail_configured') || !google_otp_mail_configured()) {
        return [
            'ok' => false,
            'error' => 'Verification email is not ready yet. Ask the site admin to add a Google App Password under Settings → Google OTP.',
        ];
    }

    self_register_purge_old_otps();
    $code = self_register_otp_code();
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $ip = visitor_ip_hash();
    $expires = (desk_now())->modify('+' . self_register_otp_ttl_seconds() . ' seconds')->format('Y-m-d H:i:s');

    // Keep any previous live OTP until the new email is known to have sent.
    $otpId = 0;
    try {
        $otpId = (int) db_exec(
            'INSERT INTO self_register_otps (email, code_hash, attempts, sent_count, ip_hash, expires_at) VALUES (?,?,0,1,?,?)',
            'ssss',
            [$email, $hash, $ip, $expires]
        );
    } catch (Throwable $e) {
        error_log('Vellisys self-register OTP save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'We could not start registration just now. Please try again shortly.'];
    }
    if ($otpId < 1) {
        return ['ok' => false, 'error' => 'We could not start registration just now. Please try again shortly.'];
    }

    form_rate_hit('self-otp-email:' . $email, 3600);
    form_rate_hit('self-otp-ip', 3600);

    self_register_save([
        'step' => 'otp',
        'email' => $email,
        'email_verified' => false,
        'otp_sent_at' => time(),
        'otp_id' => $otpId,
        'otp_mail_pending' => true,
        'otp_mail_failed' => false,
    ]);
    return ['ok' => true, 'email' => $email, 'code' => $code, 'otp_id' => $otpId];
}

/** Re-open the session after folio_redirect_then closed it. */
function self_register_resume_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}

/**
 * True when the signup is already past email OTP (details/password).
 * Late SMTP callbacks must not yank the user back to the code form.
 */
function self_register_past_otp(array $state): bool
{
    if (!empty($state['email_verified'])) {
        return true;
    }
    $step = (string) ($state['step'] ?? '');
    return in_array($step, ['details', 'password'], true);
}

/** Deliver a prepared OTP after the browser has been redirected. */
function self_register_deliver_otp(string $email, string $code, int $otpId): void
{
    $email = self_register_normalize_email($email);
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (!self_register_email_ok($email) || strlen($code) !== 6 || $otpId < 1) {
        return;
    }
    $sent = send_self_register_otp_email($email, $code);
    if (empty($sent['ok'])) {
        $err = (string) ($sent['error'] ?? 'Could not send the verification email.');
        error_log('Vellisys self-register OTP mail failed for ' . $email . ': ' . $err);
        try {
            db_exec('DELETE FROM self_register_otps WHERE id = ?', 'i', [$otpId]);
        } catch (Throwable $e) {
            // ignore
        }
        self_register_resume_session();
        $state = self_register_state();
        // Stale failure from an older send must not wipe a newer OTP, another email,
        // or an already-verified signup (including collected details).
        if ((int) ($state['otp_id'] ?? 0) === $otpId && !self_register_past_otp($state)) {
            flash($err !== '' ? $err : 'Could not send the verification email. Try again in a moment.', 'error');
            self_register_save([
                'step' => 'email',
                'email' => $email,
                'email_verified' => false,
                'otp_id' => 0,
                'otp_mail_pending' => false,
                'otp_mail_failed' => true,
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        return;
    }
    try {
        // Only drop older rows so a concurrent newer send is not wiped by a late success.
        db_exec(
            'DELETE FROM self_register_otps WHERE email = ? AND verified_at IS NULL AND id < ?',
            'si',
            [$email, $otpId]
        );
    } catch (Throwable $e) {
        // non-fatal
    }
    self_register_resume_session();
    $state = self_register_state();
    // Only the current otp_id may update the session. Never match on email alone -
    // that pulled verified users off details when a resend delivered late.
    if ((int) ($state['otp_id'] ?? 0) !== $otpId) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        return;
    }
    if (self_register_past_otp($state)) {
        $state['otp_mail_pending'] = false;
        $state['otp_mail_failed'] = false;
        self_register_save($state);
    } else {
        $state['otp_mail_pending'] = false;
        $state['otp_mail_failed'] = false;
        $state['step'] = 'otp';
        $state['email'] = $email;
        self_register_save($state);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * @return array{ok:bool,error?:string,silent?:bool}
 */
function self_register_verify_otp(string $email, string $code): array
{
    $email = self_register_normalize_email($email);
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (!self_register_email_ok($email) || strlen($code) !== 6) {
        return ['ok' => false, 'error' => 'Enter the 6-digit code from your email.'];
    }
    if (form_rate_blocked('self-otp-check:' . $email, 12, 3600) || form_rate_blocked('self-otp-check-ip', 30, 3600)) {
        return ['ok' => false, 'error' => 'Too many attempts. Wait a bit, then request a new code.'];
    }
    form_rate_hit('self-otp-check:' . $email, 3600);
    form_rate_hit('self-otp-check-ip', 3600);

    $rows = [];
    try {
        $rows = db_all(
            'SELECT * FROM self_register_otps WHERE email = ? AND verified_at IS NULL AND expires_at >= NOW() ORDER BY id DESC',
            's',
            [$email]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'We could not check that code just now.'];
    }
    if (!$rows) {
        return ['ok' => false, 'error' => 'That code has expired. Request a new one.'];
    }

    $matched = null;
    $latest = $rows[0];
    foreach ($rows as $candidate) {
        $attempts = (int) ($candidate['attempts'] ?? 0);
        if ($attempts >= 5) {
            continue;
        }
        if (password_verify($code, (string) ($candidate['code_hash'] ?? ''))) {
            $matched = $candidate;
            break;
        }
    }
    if (!$matched) {
        $latestAttempts = (int) ($latest['attempts'] ?? 0);
        if ($latestAttempts >= 5) {
            return ['ok' => false, 'error' => 'Too many wrong codes. Request a new one.'];
        }
        try {
            db_exec('UPDATE self_register_otps SET attempts = attempts + 1 WHERE id = ?', 'i', [(int) $latest['id']]);
        } catch (Throwable $e) {
            // ignore
        }
        return ['ok' => false, 'error' => 'That code does not match. Check the email and try again.'];
    }

    try {
        db_exec('UPDATE self_register_otps SET verified_at = NOW() WHERE id = ?', 'i', [(int) $matched['id']]);
        db_exec(
            'DELETE FROM self_register_otps WHERE email = ? AND verified_at IS NULL AND id <> ?',
            'si',
            [$email, (int) $matched['id']]
        );
    } catch (Throwable $e) {
        // continue
    }

    $state = self_register_state();
    $state['step'] = 'details';
    $state['email'] = $email;
    $state['email_verified'] = true;
    $state['verified_at'] = time();
    // Drop OTP mail flags so a late resend success cannot reopen the code step.
    $state['otp_id'] = 0;
    $state['otp_mail_pending'] = false;
    $state['otp_mail_failed'] = false;
    unset($state['otp_sent_at']);
    self_register_save($state);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string,fields?:array}
 */
function self_register_collect_details(array $posted): array
{
    $state = self_register_state();
    $email = self_register_normalize_email((string) ($state['email'] ?? ''));
    if (empty($state['email_verified']) || !self_register_email_ok($email)) {
        return ['ok' => false, 'error' => 'Verify your email first.'];
    }

    $name = sanitize_public_text((string) ($posted['contact_name'] ?? ''), 80);
    $company = sanitize_public_text((string) ($posted['company_name'] ?? ''), 160);
    $phone = sanitize_public_text((string) ($posted['contact_phone'] ?? ''), 40);
    $country = sanitize_public_text((string) ($posted['loc_country'] ?? ''), 80);
    if ($country === 'other') {
        $country = sanitize_public_text((string) ($posted['loc_country_other'] ?? ''), 80);
    }
    $city = sanitize_public_text((string) ($posted['loc_city'] ?? ''), 120);
    $currency = normalize_currency((string) ($posted['currency'] ?? 'UGX'), 'UGX');
    $allowedCcy = array_keys(pricing_currencies());
    if (!in_array($currency, $allowedCcy, true)) {
        $currency = 'UGX';
    }
    $knownCountries = platform_countries();
    if ($country !== '' && !in_array($country, $knownCountries, true)) {
        $country = mb_substr($country, 0, 80);
    }

    if (public_form_looks_like_spam([
        'name' => $name,
        'company' => $company,
        'email' => $email,
        'phone' => $phone,
        'note' => $city . ' ' . $country,
    ]) || public_text_is_hostile($city) || public_text_is_hostile($country)) {
        return ['ok' => false, 'error' => 'Please check the details and try again.'];
    }
    if ($name === '' || $company === '') {
        return ['ok' => false, 'error' => 'Your name and business name are required.'];
    }
    if (!public_phone_ok($phone)) {
        return ['ok' => false, 'error' => 'Enter a working phone number, including the country code if you can.'];
    }
    if ($country === '') {
        return ['ok' => false, 'error' => 'Choose a country.'];
    }
    if ($city === '') {
        return ['ok' => false, 'error' => 'Enter your city or town.'];
    }

    $fields = [
        'name' => $name,
        'company' => $company,
        'phone' => $phone,
        'country' => $country,
        'city' => $city,
        'currency' => $currency,
        'email' => $email,
    ];
    $state['step'] = 'password';
    $state['details'] = $fields;
    self_register_save($state);
    return ['ok' => true, 'fields' => $fields];
}

/**
 * Create Pro desk + admin login, notify, and return credentials for auto sign-in.
 *
 * @return array{ok:bool,error?:string,user?:array,company?:array,password?:string}
 */
function self_register_complete(string $password, string $passwordAgain): array
{
    $state = self_register_state();
    $details = is_array($state['details'] ?? null) ? $state['details'] : [];
    $email = self_register_normalize_email((string) ($details['email'] ?? $state['email'] ?? ''));
    if (empty($state['email_verified']) || $details === [] || !self_register_email_ok($email)) {
        return ['ok' => false, 'error' => 'Start again from your email.'];
    }
    if (!self_register_password_ok($password)) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if ($password !== $passwordAgain) {
        return ['ok' => false, 'error' => 'The two passwords do not match.'];
    }
    if (self_register_email_taken($email)) {
        return ['ok' => false, 'error' => 'That email already has a Vellisys login. Sign in instead.'];
    }
    if (form_rate_blocked('self-complete', 5, 3600) || form_rate_blocked('self-complete-ip', 10, 3600)) {
        return ['ok' => false, 'error' => 'Please wait a bit before trying again.'];
    }
    form_rate_hit('self-complete', 3600);
    form_rate_hit('self-complete-ip', 3600);

    $name = (string) ($details['name'] ?? '');
    $companyName = (string) ($details['company'] ?? '');
    $phone = (string) ($details['phone'] ?? '');
    $country = (string) ($details['country'] ?? '');
    $city = (string) ($details['city'] ?? '');
    $currency = normalize_currency((string) ($details['currency'] ?? 'UGX'), 'UGX');

    $plan = 'office';
    $seats = plan_user_limit_max($plan);
    $from = desk_now()->format('Y-m-d');
    $expires = compute_expiry_date($from, 1, 'months') ?: desk_now()->modify('+1 month')->format('Y-m-d');
    $notes = 'Self-registered Pro trial · 1 month free · ' . $email . ' · ' . $phone;
    $kinds = json_encode(default_enabled_kinds(), JSON_UNESCAPED_UNICODE);
    $customDoc = json_encode(default_custom_doc(), JSON_UNESCAPED_UNICODE);
    $plannerOn = planner_resolve_enabled($plan, plan_includes_planner($plan), null);
    $pnlOn = pnl_resolve_enabled($plan, plan_includes_pnl($plan), null);

    $cid = 0;
    try {
        $cid = (int) db_exec(
            'INSERT INTO companies (name, status, plan, notes, enabled_kinds, custom_doc, user_limit, planner_enabled, pnl_enabled, paid_term, paid_unit, paid_from, expires_at, fee_amount, fee_paid, fee_currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'ssssssiiidsssdds',
            [
                $companyName,
                'live',
                $plan,
                $notes,
                $kinds,
                $customDoc,
                $seats,
                $plannerOn,
                $pnlOn,
                1,
                'months',
                $from,
                $expires,
                0.0,
                0.0,
                $currency,
            ]
        );
    } catch (Throwable $e) {
        error_log('Vellisys self-register company: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'We could not open that desk just now. Please try again.'];
    }
    if ($cid < 1) {
        return ['ok' => false, 'error' => 'We could not open that desk just now. Please try again.'];
    }

    try {
        db_exec('UPDATE companies SET stock_enabled = 1 WHERE id = ?', 'i', [$cid]);
    } catch (Throwable $e) {
        // ignore
    }
    try {
        db_exec(
            'UPDATE companies SET loc_city=?, loc_country=? WHERE id=?',
            'ssi',
            [$city, $country, $cid]
        );
    } catch (Throwable $e) {
        // ignore if columns missing
    }
    if (function_exists('company_modules_materialize_all')) {
        $fresh = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]);
        company_modules_materialize_all($cid, $fresh ?: null, $plan);
    }

    $color = '#1E4EFF';
    $accent = '#C6A15B';
    $deep = function_exists('hex_shade') ? hex_shade($color, 0.52) : '#08143A';
    $prefix = prefix_from_name($companyName);
    try {
        db_exec(
            'INSERT INTO branding (company_id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, brand_accent, brand_deep, logo_path, prefix, payment_note, invoice_comments, receipt_comments, plan, currency)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'issssssssssssssssssssss',
            [
                $cid,
                $companyName,
                '',
                '',
                '',
                '',
                $city,
                $phone,
                $email,
                '',
                '',
                $companyName,
                '',
                $color,
                $accent,
                $deep,
                '',
                $prefix,
                'Make payment to ' . $companyName . '.',
                "1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.",
                'Payments made are not refundable.',
                'office',
                $currency,
            ]
        );
    } catch (Throwable $e) {
        error_log('Vellisys self-register branding: ' . $e->getMessage());
    }

    $rollbackCompany = static function (int $companyId): void {
        if ($companyId < 1) {
            return;
        }
        try {
            db_exec('DELETE FROM branding WHERE company_id = ?', 'i', [$companyId]);
        } catch (Throwable $e) {
            // ignore
        }
        try {
            db_exec('DELETE FROM users WHERE company_id = ?', 'i', [$companyId]);
        } catch (Throwable $e) {
            // ignore
        }
        try {
            db_exec('DELETE FROM companies WHERE id = ?', 'i', [$companyId]);
        } catch (Throwable $e) {
            // ignore
        }
    };

    $made = ['ok' => false, 'error' => 'Could not create that login.'];
    try {
        $made = create_desk_user($cid, [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'job_title' => 'Administrator',
            'access' => 'admin',
        ]);
    } catch (Throwable $e) {
        error_log('Vellisys self-register user: ' . $e->getMessage());
        $rollbackCompany($cid);
        return ['ok' => false, 'error' => 'Could not create that login. Please try again.'];
    }
    if (empty($made['ok'])) {
        $rollbackCompany($cid);
        return ['ok' => false, 'error' => (string) ($made['error'] ?? 'Could not create that login.')];
    }

    $userRow = db_one('SELECT * FROM users WHERE email = ? AND company_id = ?', 'si', [$email, $cid]);
    if (!$userRow || (int) ($userRow['id'] ?? 0) < 1) {
        $rollbackCompany($cid);
        return ['ok' => false, 'error' => 'Could not create that login. Please try again.'];
    }

    $signupId = 0;
    try {
        $signupId = (int) db_exec(
            'INSERT INTO signups (name, company, email, phone, status, source, note, company_id) VALUES (?,?,?,?,?,?,?,?)',
            'sssssssi',
            [
                $name,
                $companyName,
                $email,
                $phone,
                'onboarded',
                'self',
                'Self-registered Pro trial · ' . $currency . ' · ' . $city . ', ' . $country,
                $cid,
            ]
        );
    } catch (Throwable $e) {
        try {
            $signupId = (int) db_exec(
                'INSERT INTO signups (name, company, email, phone, status, source, note) VALUES (?,?,?,?,?,?,?)',
                'sssssss',
                [$name, $companyName, $email, $phone, 'onboarded', 'self', 'Self-registered Pro trial']
            );
            if ($signupId > 0) {
                db_exec('UPDATE signups SET company_id = ? WHERE id = ?', 'ii', [$cid, $signupId]);
            }
        } catch (Throwable $e2) {
            // non-fatal
        }
    }

    company_mark_onboard_step($cid, 'credentials_set');
    company_mark_onboard_step($cid, 'desk_login');
    company_mark_onboard_step($cid, 'welcome_email');

    if (function_exists('db_has_column') && db_has_column(db(), 'users', 'first_login_at')) {
        db_exec('UPDATE users SET first_login_at = NOW() WHERE id = ?', 'i', [(int) $userRow['id']]);
        company_mark_onboard_step($cid, 'first_login');
    }

    $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) ?: [
        'id' => $cid,
        'name' => $companyName,
        'expires_at' => $expires,
        'plan' => $plan,
    ];

    notify_self_register_complete($company, $userRow, [
        'phone' => $phone,
        'city' => $city,
        'country' => $country,
        'currency' => $currency,
        'signup_id' => $signupId,
        'expires_at' => $expires,
    ]);

    self_register_clear();
    return ['ok' => true, 'user' => $userRow, 'company' => $company, 'password' => $password];
}

function self_register_login_user(array $user): void
{
    $uid = (int) ($user['id'] ?? 0);
    $cid = (int) ($user['company_id'] ?? 0);
    if ($uid < 1) {
        return;
    }
    $_SESSION['user_id'] = $uid;
    $_SESSION['company_id'] = $cid;
    $_SESSION['role'] = (string) ($user['role'] ?? 'admin');
    $_SESSION['desk_welcome'] = 1;
    if (function_exists('touch_user_seen')) {
        touch_user_seen($uid, true);
    }
    if (function_exists('remember_login')) {
        remember_login(true);
    }
}
