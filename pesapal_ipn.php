<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$json = json_decode($raw, true);
if (!is_array($json)) {
    $json = [];
}
$tracking = (string) ($_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? $json['OrderTrackingId'] ?? $json['orderTrackingId'] ?? '');
$ref = (string) ($_GET['OrderMerchantReference'] ?? $_GET['orderMerchantReference'] ?? $json['OrderMerchantReference'] ?? $json['orderMerchantReference'] ?? '');
$type = (string) ($_GET['OrderNotificationType'] ?? $json['OrderNotificationType'] ?? 'IPNCHANGE');

$order = $tracking !== '' ? order_by_tracking($tracking) : null;
if (!$order && $ref !== '') {
    $order = order_by_merchant($ref);
}
if ($order) {
    refresh_order_from_pesapal($order);
}

header('Content-Type: application/json');
echo json_encode([
    'orderNotificationType' => $type !== '' ? $type : 'IPNCHANGE',
    'orderTrackingId' => $tracking,
    'orderMerchantReference' => $ref,
    'status' => 200,
]);
