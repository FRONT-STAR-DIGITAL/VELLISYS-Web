<?php
declare(strict_types=1);
$_GET['installing'] = '1';
require __DIR__ . '/includes/bootstrap.php';

$cfg = require ROOT_PATH . '/config/database.php';
$db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass']);
if ($db->connect_errno) {
    exit('Cannot connect to MySQL as ' . h($cfg['user']) . '. Start MySQL in XAMPP.');
}
$db->set_charset('utf8mb4');
$name = $db->real_escape_string($cfg['name']);
$db->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->select_db($cfg['name']);

$sql = file_get_contents(ROOT_PATH . '/sql/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    if (!$db->query($stmt)) {
        exit('Schema error: ' . $db->error . '<pre>' . h($stmt) . '</pre>');
    }
}

$exists = $db->query('SELECT COUNT(*) c FROM users')->fetch_assoc();
if ((int) $exists['c'] === 0) {
    $hash = password_hash('folio2026', PASSWORD_DEFAULT);
    $ins = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?,?,?)');
    $n = 'Accounts';
    $e = 'accounts@ofagros.org';
    $ins->bind_param('sss', $n, $e, $hash);
    $ins->execute();
    $userId = $ins->insert_id;

    $db->query("INSERT INTO branding (id, name, tagline, tin, vat_no, address, city, phone, email, website, bank_name, account_name, account_number, brand_color, logo_path, prefix, payment_note, invoice_comments, plan)
    VALUES (1, 'Ofagros Limited', 'Solutions for agriculture', '1000890123', '1000890123',
    'Kampala, Central Region, Uganda', 'Kampala, Uganda', '+256 788 141 342', 'ofagrosltd@gmail.com', 'www.ofagros.org',
    'Stanbic Bank Uganda', 'Ofagros Limited', '9030008844211', '#82B440', 'assets/img/ofagros-logo.png', 'OFG',
    'Make payment to Ofagros Limited, Kampala.',
    '1. Payment is due by the date shown above.\n2. Pay through the Ofagros client portal.\n3. Farm work starts after this invoice is marked paid.',
    'sme')");

    $parties = [
        ['Demo Visitor', 'customer', null, '+256700000001', 'demo@ofagros.com', 'Kampala, Uganda'],
        ['Nile Coffee Traders Ltd', 'customer', '1000456710', '+256 414 220 118', 'accounts@nilecoffee.ug', 'Plot 8, Portal Avenue, Kampala'],
        ['Kituza Estate Growers', 'customer', null, '+256 772 441 090', 'kituza@growers.ug', 'Mukono District'],
        ['Pearl Hotel Kampala', 'customer', '1000021766', '+256 414 251 510', 'purchasing@pearlhotel.ug', 'Kololo, Kampala'],
        ['SeedCo Uganda Ltd', 'supplier', '1000038891', '+256 414 566 200', null, 'Namanve Industrial Park'],
        ['Vivo Energy Uganda', 'supplier', '1000023301', null, null, 'Kampala'],
    ];
    $pstmt = $db->prepare('INSERT INTO parties (name, kind, tin, phone, email, address) VALUES (?,?,?,?,?,?)');
    foreach ($parties as $p) {
        $pstmt->bind_param('ssssss', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
        $pstmt->execute();
    }

    $mysqli = $db;
    require ROOT_PATH . '/sql/seed_docs.php';
}

header('Location: login.php');
exit;
