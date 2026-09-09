<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return db_one('SELECT id, name, email, role, company_id FROM users WHERE id = ?', 'i', [(int) $_SESSION['user_id']]);
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
