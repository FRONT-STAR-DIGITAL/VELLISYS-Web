<?php
declare(strict_types=1);
/**
 * Authenticated sheet-only page for client PDF capture.
 * Renders the same HTML document designs as Print / Share (not a separate layout).
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? 0);
$doc = load_document($id);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}
if (strtolower((string) ($doc['status'] ?? '')) === 'void') {
    http_response_code(403);
    exit('Void documents cannot be downloaded.');
}

$brand = branding();
require_once ROOT_PATH . '/includes/designs.php';
$thermal = doc_template_key($doc) === 'thermal'
    && ($doc['kind'] ?? '') !== 'custom'
    && ($doc['kind'] ?? '') !== 'expense';
$filename = document_download_filename($doc);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h($filename) ?></title>
  <meta name="format-detection" content="telephone=no,email=no,address=no,date=no">
  <?php folio_css_links(true, true); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars($brand) ?> }
    @page { size: <?= $thermal ? '80mm auto' : 'A4' ?>; margin: 0; }
    html, body.print-body { background: #fff !important; margin: 0; padding: 0; }
    .sheet-wrap, .sheet-stage { padding: 0 !important; margin: 0 !important; background: #fff; }
    .invoice-sheet { transform: none !important; zoom: 1 !important; box-shadow: none !important; margin: 0 auto !important; }
  </style>
</head>
<body class="print-body<?= $thermal ? ' print-thermal' : '' ?>" data-doc-number="<?= h((string) ($doc['number'] ?? '')) ?>" data-pdf-name="<?= h($filename) ?>">
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
</body>
</html>
