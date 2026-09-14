<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
require_desk_kind('letter');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$doc = null;
if ($id > 0) {
    $doc = load_document($id);
    if (!$doc || ($doc['kind'] ?? '') !== 'letter') {
        flash('Letter not found.', 'err');
        redirect('documents.php?kind=letter');
    }
}

try {
    $bytes = letter_docx_bytes($doc);
} catch (Throwable $e) {
    flash($e->getMessage(), 'err');
    redirect($id > 0 ? 'document_view.php?id=' . $id : 'documents.php?kind=letter');
}

$name = letter_docx_filename($doc);
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string) strlen($bytes));
header('Cache-Control: private, no-store');
echo $bytes;
exit;
