<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

if (isset($_GET['leave'])) {
    unset($_SESSION['acting_company_id']);
    $_SESSION['company_id'] = 0;
    flash('Left the company desk.');
    redirect('admin_companies.php');
}

$id = (int) ($_GET['id'] ?? 0);
$company = $id ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
if (!$company) {
    flash('Company not found.', 'err');
    redirect('admin_companies.php');
}

$_SESSION['acting_company_id'] = $id;
$_SESSION['company_id'] = $id;
flash('Working the desk for ' . $company['name'] . '. You can convert quotes, take receipts, and edit documents.');
redirect('dashboard.php');
