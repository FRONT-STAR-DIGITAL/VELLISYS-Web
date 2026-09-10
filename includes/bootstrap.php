<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Africa/Kampala');

define('ROOT_PATH', dirname(__DIR__));

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
require_once ROOT_PATH . '/includes/layout.php';
