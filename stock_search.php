<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_stock();
header('Content-Type: application/json; charset=utf-8');
$q = (string) ($_GET['q'] ?? '');
echo json_encode(stock_search($q, 12), JSON_UNESCAPED_UNICODE);
