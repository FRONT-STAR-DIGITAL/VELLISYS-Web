<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
if (function_exists('clear_remember_cookies')) {
    clear_remember_cookies();
} else {
    $past = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => function_exists('folio_request_is_https') && folio_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(session_name(), '', $past);
    setcookie('vellisys_rm', '', $past);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
header('Location: ' . url('login.php'));
exit;
