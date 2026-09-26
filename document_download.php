<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? 0);
$doc = load_document($id);
if (!$doc) {
    if (isset($_GET['warm'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false]);
        exit;
    }
    flash('Document not found.', 'err');
    redirect('dashboard.php');
}

// Background warm: build & cache PDF if possible, never redirect to sheet UI.
if (isset($_GET['warm'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (document_pdf_cache_read($doc) === '' && document_sheet_chrome() !== '') {
            $bytes = document_sheet_pdf_bytes($doc);
            document_pdf_cache_write($doc, $bytes);
        }
        echo json_encode(['ok' => true, 'cached' => document_pdf_cache_read($doc) !== '']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false]);
    }
    exit;
}

send_document_download($doc);
