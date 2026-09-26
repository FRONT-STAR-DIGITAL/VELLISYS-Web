<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing document id.']);
    exit;
}

$doc = load_document($id);
if (!$doc) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Document not found.']);
    exit;
}

$payload = document_full_payload($doc);
echo json_encode([
    'ok' => true,
    'doc' => $payload,
    'docType' => (string) ($payload['kind'] ?? 'invoice'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
