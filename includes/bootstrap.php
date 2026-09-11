<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/env.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => folio_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
date_default_timezone_set('Africa/Kampala');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (folio_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($scriptName === '/' || $scriptName === '\\') {
    $scriptName = '';
}
define('BASE_URL', rtrim($scriptName, '/'));

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

function asset(string $path): string
{
    $rel = 'assets/' . ltrim($path, '/');
    $full = ROOT_PATH . '/' . $rel;
    $v = is_file($full) ? (string) filemtime($full) : '1';
    return url($rel) . '?v=' . $v;
}

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/pricing.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/icons.php';
require_once ROOT_PATH . '/includes/documents.php';
require_once ROOT_PATH . '/includes/smtp.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/pesapal.php';
require_once ROOT_PATH . '/includes/onboard.php';
require_once ROOT_PATH . '/includes/visits.php';
require_once ROOT_PATH . '/includes/pdf.php';
require_once ROOT_PATH . '/includes/layout.php';
