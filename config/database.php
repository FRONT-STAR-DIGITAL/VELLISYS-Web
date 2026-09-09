<?php
/**
 * XAMPP defaults: user root, empty password.
 * After copying to htdocs/folio, import sql/install.sql or open /install.php
 */
return [
    'host' => getenv('FOLIO_DB_HOST') ?: '127.0.0.1',
    'name' => getenv('FOLIO_DB_NAME') ?: 'folio',
    'user' => getenv('FOLIO_DB_USER') ?: 'root',
    'pass' => getenv('FOLIO_DB_PASS') !== false ? getenv('FOLIO_DB_PASS') : '',
];
