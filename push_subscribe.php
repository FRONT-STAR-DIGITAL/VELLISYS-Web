<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'vapid' => vapid_public_key(),
        'items' => push_current_items_for_user($user),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_check();
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}
$action = (string) ($data['action'] ?? post('action'));

try {
    if ($action === 'subscribe') {
        $endpoint = (string) ($data['endpoint'] ?? '');
        $keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        push_save_subscription(
            $user,
            $endpoint,
            (string) ($keys['p256dh'] ?? $data['p256dh'] ?? ''),
            (string) ($keys['auth'] ?? $data['auth'] ?? '')
        );
        $items = push_current_items_for_user($user);
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        push_schedule_sync();
        exit;
    }
    if ($action === 'unsubscribe') {
        push_delete_subscription((string) ($data['endpoint'] ?? ''));
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
