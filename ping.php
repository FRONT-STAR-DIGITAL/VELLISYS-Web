<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$user = current_user();
if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false]);
    exit;
}

touch_user_seen((int) $user['id']);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'blob') {
    // Fixed payload for client-side download speed tests (max 1 MB).
    $bytes = (int) ($_GET['bytes'] ?? 262144);
    $bytes = max(32 * 1024, min(1024 * 1024, $bytes));
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . (string) $bytes);
    header('X-Content-Type-Options: nosniff');
    // Deterministic compressible-but-varied pattern (not all zeros).
    $chunk = '';
    for ($i = 0; $i < 1024; $i++) {
        $chunk .= chr(($i * 37 + 11) % 256);
    }
    $left = $bytes;
    while ($left > 0) {
        $n = min($left, strlen($chunk));
        echo substr($chunk, 0, $n);
        $left -= $n;
    }
    exit;
}

header('Content-Type: application/json');

if ($action === 'stats') {
    if (($user['role'] ?? '') !== 'platform') {
        echo json_encode(['ok' => false]);
        exit;
    }
    $stats = function_exists('platform_reload_stats') ? platform_reload_stats() : ['ms' => null, 'label' => '-', 'health' => ['key' => 'healthy', 'label' => 'Healthy'], 'spark' => []];
    echo json_encode(['ok' => true, 'reload' => $stats]);
    exit;
}

if ($action === 'echo') {
    // Tiny authenticated round-trip for manual reload-speed sampling.
    echo json_encode(['ok' => true, 't' => (int) round(microtime(true) * 1000)]);
    exit;
}

$ms = (int) ($_GET['ms'] ?? $_POST['ms'] ?? 0);
if ($ms > 0) {
    record_platform_perf($ms, (string) ($_GET['path'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
}

echo json_encode(['ok' => true]);
