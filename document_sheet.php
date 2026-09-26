<?php
declare(strict_types=1);
/**
 * Authenticated sheet-only page (same HTML designs as Print / Share).
 * ?autodownload=1 captures the sheet to PDF immediately on load.
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
    html, body.print-body { background: #fff !important; margin: 0; padding: 0; }
    .sheet-wrap, .sheet-stage { padding: 0 !important; margin: 0 !important; background: #fff; }
    .invoice-sheet { transform: none !important; zoom: 1 !important; box-shadow: none !important; margin: 0 auto !important; }
  </style>
</head>
<body class="print-body<?= $thermal ? ' print-thermal' : '' ?>" data-pdf-name="<?= h($filename) ?>"<?= $auto ? ' data-autodownload="1"' : '' ?>>
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
  <?php if ($auto): ?>
  <script src="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <script>
  (function () {
    function go() {
      var sheet = document.querySelector('.invoice-sheet') || document.querySelector('.sheet-stage');
      if (!sheet || typeof html2pdf !== 'function') {
        document.title = 'Download failed';
        return;
      }
      var name = document.body.getAttribute('data-pdf-name') || 'document.pdf';
      var thermal = document.body.classList.contains('print-thermal');
      html2pdf().set({
        margin: 0,
        filename: name,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#ffffff', logging: false },
        jsPDF: { unit: 'mm', format: thermal ? [80, 200] : 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'] }
      }).from(sheet).save().then(function () {
        setTimeout(function () {
          if (window.history.length > 1) window.history.back();
        }, 400);
      });
    }
    if (document.readyState === 'complete') go();
    else window.addEventListener('load', function () { setTimeout(go, 40); });
  })();
  </script>
  <?php endif; ?>
</body>
</html>
