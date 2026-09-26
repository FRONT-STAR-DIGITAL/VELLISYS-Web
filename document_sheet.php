<?php
declare(strict_types=1);
/**
 * Authenticated sheet-only page (same HTML designs as Print / Share).
 * ?autodownload=1 captures the unfitted sheet to PDF immediately on load.
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
$auto = isset($_GET['autodownload']);
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
    html, body, body.print-body, .invoice-sheet, .invoice-sheet * {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    html, body.print-body { background: #fff !important; margin: 0; padding: 0; }
    .sheet-wrap, .sheet-stage { padding: 0 !important; margin: 0 !important; background: #fff; height: auto !important; }
    .invoice-sheet {
      transform: none !important;
      zoom: 1 !important;
      box-shadow: none !important;
      margin: 0 auto !important;
      height: auto !important;
      min-height: 0 !important;
      page-break-after: avoid !important;
      break-after: avoid !important;
    }
    .invoice-sheet.sheet-thermal { width: 80mm !important; max-width: 80mm !important; }
    .invoice-sheet:not(.sheet-thermal) { width: 210mm !important; max-width: 210mm !important; }
    .sheet-frame .page-frame,
    .sheet-inset .page-inset,
    .booklet-page,
    .chit-page { min-height: 0 !important; height: auto !important; }
    .doc-authenticity { margin-top: 14px !important; }
  </style>
</head>
<body
  class="print-body<?= $thermal ? ' print-thermal' : '' ?>"
  data-pdf-name="<?= h($filename) ?>"
  data-pdf-exact="1"
  <?= $auto ? ' data-autodownload="1"' : '' ?>
>
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
  <?php if ($auto): ?>
  <script src="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <script src="<?= h(asset('js/pdf-download.js')) ?>" defer data-html2pdf="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>
