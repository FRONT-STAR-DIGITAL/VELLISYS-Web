<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/env.php';
require_once ROOT_PATH . '/includes/session_store.php';

// Sessions persist until Sign out — no idle expiry.
// Prefer MySQL (survives Docker redeploys); fall back to storage/sessions.
if (!defined('SESSION_PERSIST_SECONDS')) {
    define('SESSION_PERSIST_SECONDS', 60 * 60 * 24 * 365 * 10); // ~10 years
}
if (!defined('REMEMBER_LIFETIME_SECONDS')) {
    define('REMEMBER_LIFETIME_SECONDS', SESSION_PERSIST_SECONDS);
}
if (!defined('SESSION_COOKIE_SECONDS')) {
    define('SESSION_COOKIE_SECONDS', SESSION_PERSIST_SECONDS);
}
if (!defined('PLATFORM_LIFETIME_SECONDS')) {
    define('PLATFORM_LIFETIME_SECONDS', SESSION_PERSIST_SECONDS);
}

$persist = SESSION_PERSIST_SECONDS;
@ini_set('session.gc_maxlifetime', (string) $persist);
@ini_set('session.cookie_lifetime', (string) $persist);
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');

folio_register_session_store();

session_set_cookie_params([
    'lifetime' => $persist,
    'path' => '/',
    'secure' => folio_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
// Super Admin / platform default: East Africa Time (EAT, Africa/Kampala, UTC+3).
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
if ($scriptName === '/' || $scriptName === '\\' || $scriptName === '.' || $scriptName === '') {
    $scriptName = '';
}
define('BASE_URL', rtrim($scriptName, '/'));

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = BASE_URL === '.' ? '' : BASE_URL;
    return ($base === '' ? '' : $base) . '/' . $path;
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
require_once ROOT_PATH . '/includes/client_fields.php';
require_once ROOT_PATH . '/includes/search.php';
require_once ROOT_PATH . '/includes/pricing.php';
require_once ROOT_PATH . '/includes/auth.php';

// Resume signed login cookie if the PHP session row was lost, then slide cookies.
if (function_exists('folio_auth_resume')) {
    folio_auth_resume();
}
if (function_exists('refresh_remembered_session')) {
    refresh_remembered_session();
}

require_once ROOT_PATH . '/includes/icons.php';
require_once ROOT_PATH . '/includes/documents.php';
require_once ROOT_PATH . '/includes/stock.php';
require_once ROOT_PATH . '/includes/desk_day.php';
require_once ROOT_PATH . '/includes/sales.php';
require_once ROOT_PATH . '/includes/feedback.php';
require_once ROOT_PATH . '/includes/backup.php';
require_once ROOT_PATH . '/includes/import.php';
require_once ROOT_PATH . '/includes/planner.php';
require_once ROOT_PATH . '/includes/branches.php';
require_once ROOT_PATH . '/includes/pnl.php';
require_once ROOT_PATH . '/includes/smtp.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/pesapal.php';
require_once ROOT_PATH . '/includes/join.php';
require_once ROOT_PATH . '/includes/onboard.php';
require_once ROOT_PATH . '/includes/platform.php';
require_once ROOT_PATH . '/includes/visits.php';
require_once ROOT_PATH . '/includes/activity.php';
require_once ROOT_PATH . '/includes/docx.php';
require_once ROOT_PATH . '/includes/pdf.php';
require_once ROOT_PATH . '/includes/layout.php';
require_once ROOT_PATH . '/includes/push.php';

if (function_exists('apply_desk_timezone')) {
    try {
        apply_desk_timezone();
    } catch (Throwable $e) {
        date_default_timezone_set('Africa/Kampala');
        if (function_exists('apply_mysql_timezone')) {
            try {
                apply_mysql_timezone('Africa/Kampala');
            } catch (Throwable $e2) {
            }
        }
    }
}
