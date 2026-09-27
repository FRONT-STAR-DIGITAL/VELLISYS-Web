<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false]);
    exit;
}

touch_user_seen((int) $user['id']);
$ms = (int) ($_GET['ms'] ?? $_POST['ms'] ?? 0);
if ($ms > 0) {
    record_platform_perf($ms, (string) ($_GET['path'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
}

// Daily company dump runs here (background beat), not on desk page renders.
if (function_exists('company_backup_maybe') && (($user['role'] ?? '') !== 'platform')) {
    company_backup_maybe();
}

echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}
