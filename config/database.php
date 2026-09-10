<?php
/**
 * Local / XAMPP defaults: database folio, user root, empty password.
 * On www.vellisys.com (Hostinger) the live database is used unless FOLIO_DB_* is set.
 */
require_once __DIR__ . '/env.php';

$env = static function (string $key, $fallback) {
    $value = getenv($key);
    return $value !== false ? $value : $fallback;
};

if (folio_is_live_host()) {
    return [
        'host' => $env('FOLIO_DB_HOST', 'localhost'),
        'name' => $env('FOLIO_DB_NAME', 'u454222977_Vell'),
        'user' => $env('FOLIO_DB_USER', 'u454222977_Vell'),
        'pass' => $env('FOLIO_DB_PASS', 'Coml%^sd235(&'),
    ];
}

return [
    'host' => $env('FOLIO_DB_HOST', '127.0.0.1'),
    'name' => $env('FOLIO_DB_NAME', 'folio'),
    'user' => $env('FOLIO_DB_USER', 'root'),
    'pass' => getenv('FOLIO_DB_PASS') !== false ? getenv('FOLIO_DB_PASS') : '',
];
