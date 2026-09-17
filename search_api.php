<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (!search_is_platform_portal()) {
    require_member();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$q = search_query();
$data = search_run($q, 6);
echo json_encode([
    'q' => $q,
    'hits' => $q === '' ? [] : search_flat_hits($data, 12),
    'exact' => $data['exact'] ?? null,
], JSON_UNESCAPED_UNICODE);
