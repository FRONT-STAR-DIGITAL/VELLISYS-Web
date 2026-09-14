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

send_document_download($doc);
