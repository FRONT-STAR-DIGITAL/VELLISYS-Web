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

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

/*
 * Speed sensors must not pay for housekeep / Web Push. Those ran before blob/echo
 * and turned a 50ms probe into a 1–2s "Slow" reading.
 */
if ($action === 'blob') {
    // Fixed payload for client-side download speed tests (max 1 MB).
    $bytes = (int) ($_GET['bytes'] ?? 1024 * 1024);
    $bytes = max(64 * 1024, min(1024 * 1024, $bytes));
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . (string) $bytes);
    header('Content-Encoding: identity');
    header('X-Content-Type-Options: nosniff');
    // Varied bytes so proxies/gzip cannot crush the payload into a tiny transfer.
    $chunk = '';
    for ($i = 0; $i < 4096; $i++) {
        $chunk .= chr(($i * 131 + 17) % 256);
    }
    $left = $bytes;
    while ($left > 0) {
        $n = min($left, strlen($chunk));
        echo substr($chunk, 0, $n);
        $left -= $n;
    }
    exit;
}

if ($action === 'echo') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 't' => (int) round(microtime(true) * 1000)]);
    exit;
}

if ($action === 'stats') {
    header('Content-Type: application/json');
    if (($user['role'] ?? '') !== 'platform') {
        echo json_encode(['ok' => false]);
        exit;
    }
    $stats = function_exists('platform_reload_stats')
        ? platform_reload_stats()
        : ['ms' => null, 'label' => '-', 'health' => ['key' => 'healthy', 'label' => 'Healthy'], 'spark' => []];
    echo json_encode(['ok' => true, 'reload' => $stats]);
    exit;
}

// Presence beat (full path): touch + occasional housekeep/push.
touch_user_seen((int) $user['id']);
if (function_exists('folio_housekeep_daily')) {
    folio_housekeep_daily();
}
if (function_exists('push_sync_outstanding')) {
    $pushKey = 'push_sync_at';
    $lastPush = (int) ($_SESSION[$pushKey] ?? 0);
    if ($lastPush <= 0 || (time() - $lastPush) >= 60) {
        $_SESSION[$pushKey] = time();
        try {
            push_sync_outstanding($user);
        } catch (Throwable $e) {
            // never break the presence beat
        }
    }
}

header('Content-Type: application/json');

$ms = (int) ($_GET['ms'] ?? $_POST['ms'] ?? 0);
if ($ms > 0) {
    record_platform_perf($ms, (string) ($_GET['path'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
}

echo json_encode(['ok' => true]);
