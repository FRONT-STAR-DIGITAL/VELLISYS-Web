<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $user = db_one('SELECT * FROM users WHERE id = ?', 'i', [(int) $_SESSION['user_id']]);
    } catch (Throwable $e) {
        return null;
    }
    if (!$user) {
        return null;
    }
    $user['job_title'] = (string) ($user['job_title'] ?? '');
    $user['access'] = (string) ($user['access'] ?? (($user['role'] ?? '') === 'admin' ? 'admin' : 'books'));
    $user['role'] = (string) ($user['role'] ?? 'member');
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_member(): array
{
    $user = require_login();
    if (($user['role'] ?? '') === 'platform') {
        $acting = (int) ($_SESSION['acting_company_id'] ?? 0);
        if ($acting <= 0) {
            redirect('admin_signups.php');
        }
        $_SESSION['company_id'] = $acting;
    }
    return $user;
}

function is_acting_admin(): bool
{
    return is_platform() && (int) ($_SESSION['acting_company_id'] ?? 0) > 0;
}

function require_platform(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'platform') {
        redirect('dashboard.php');
    }
    return $user;
}

function is_desk_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'platform') {
        return true;
    }
    return ($user['role'] ?? '') === 'admin';
}

function require_desk_admin(): array
{
    $user = require_member();
    if (!is_desk_admin($user)) {
        flash('Only the company admin can open that page.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function user_access(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return 'books';
    }
    if (is_desk_admin($user)) {
        return 'admin';
    }
    $access = (string) ($user['access'] ?? 'books');
    return $access === 'sales' ? 'sales' : 'books';
}

function desk_staff_access_levels(): array
{
    return [
        'books' => [
            'label' => 'Books',
            'hint' => 'Documents, clients, debtors, creditors and email. No reports, settings or people.',
        ],
        'sales' => [
            'label' => 'Sales',
            'hint' => 'Quotations, invoices, receipts, clients and email. No expenses, reports, settings or people.',
        ],
    ];
}

function user_allowed_kinds(?array $user = null): ?array
{
    if (is_desk_admin($user) || user_access($user) === 'books') {
        return null;
    }
    return ['quotation', 'invoice', 'receipt'];
}

function user_can_kind(string $kind, ?array $user = null): bool
{
    if (!company_allows_kind($kind) && $kind !== 'expense') {
        return false;
    }
    if ($kind === 'expense' && user_access($user) === 'sales') {
        return false;
    }
    $allowed = user_allowed_kinds($user);
    if ($allowed === null) {
        return true;
    }
    return in_array($kind, $allowed, true);
}

function user_can_open(string $script, string $kind = ''): bool
{
    $script = basename($script);
    if (is_desk_admin()) {
        return true;
    }
    $access = user_access();
    $adminOnly = ['settings.php', 'reports.php', 'branding.php'];
    if (in_array($script, $adminOnly, true)) {
        return false;
    }
    $kindScripts = ['documents.php', 'document_new.php', 'document_view.php', 'document_email.php', 'document_action.php'];
    if (in_array($script, $kindScripts, true)) {
        if ($kind === '') {
            return true;
        }
        return user_can_kind($kind);
    }
    if ($script === 'export.php') {
        return $kind === '' || user_can_kind($kind);
    }
    if ($script === 'creditors.php' && $access === 'sales') {
        return false;
    }
    return true;
}

function attempt_login(string $email, string $password): bool
{
    $user = db_one('SELECT * FROM users WHERE email = ?', 's', [strtolower($email)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['company_id'] = (int) ($user['company_id'] ?? 0);
    $_SESSION['role'] = $user['role'] ?? 'member';
    return true;
}

function remember_login(bool $remember): void
{
    $lifetime = $remember ? 60 * 60 * 24 * 30 : 0;
    $_SESSION['remember'] = $remember ? 1 : 0;
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $lifetime > 0 ? time() + $lifetime : 0,
        'path' => $params['path'] ?: '/',
        'secure' => folio_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
