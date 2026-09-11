<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? 0);
$doc = load_document($id);
if (!$doc) {
    flash('Document not found.', 'err');
    redirect('dashboard.php');
}

$bytes = document_pdf_bytes(branding(), $doc);
$name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $doc['number']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string) strlen($bytes));
header('Cache-Control: private, no-store');
echo $bytes;
exit;
