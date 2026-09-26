<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['t'] ?? '');
$probe = $id > 0
    ? db_one('SELECT id, company_id, number FROM documents WHERE id = ?', 'i', [$id])
    : null;

if (!$probe || !hash_equals(document_share_token($probe), $token)) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Document not available</title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
</head>
<body class="print-body">
  <p class="empty" style="padding:48px;text-align:center">This document is not available. Ask the company to send the link again.</p>
</body>
</html>
    <?php
    exit;
}

$GLOBALS['folio_company_override'] = (int) $probe['company_id'];
$doc = load_document($id);
if (!$doc) {
    http_response_code(404);
    exit('This document is not available.');
}
$brand = branding_for((int) $probe['company_id']);
$print = isset($_GET['print']);
$asSheet = isset($_GET['sheet']);
$asDownload = isset($_GET['download']);
$auto = isset($_GET['autodownload']);
$asOg = isset($_GET['og']);
if ($asOg) {
    document_send_share_preview($doc);
}
if ($asDownload) {
    $shareAuto = 'share.php?id=' . $id . '&t=' . rawurlencode($token) . '&autodownload=1';
    send_document_download($doc, $shareAuto);
}
if ($print) {
    if (send_document_print_pdf($doc)) {
        exit;
    }
    require_once ROOT_PATH . '/includes/designs.php';
    render_print_document_page($doc, false);
    exit;
}
require ROOT_PATH . '/includes/sheet.php';
$copy = document_share_preview_copy($doc, $brand);
$pdfName = document_download_filename($doc);
$thermal = doc_template_key($doc) === 'thermal'
    && ($doc['kind'] ?? '') !== 'custom'
    && ($doc['kind'] ?? '') !== 'expense';
$fitOff = $asSheet || $auto;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h($print ? $pdfName : ($copy['title'] . ' · ' . $brand['name'])) ?></title>
  <meta name="format-detection" content="telephone=no,email=no,address=no,date=no">
  <?php document_share_og_meta($doc, $brand); ?>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars($brand) ?> }
    @page { size: <?= $thermal ? '80mm auto' : 'A4' ?>; margin: 0; }
    .share-toolbar {
      max-width: 210mm;
      margin: 16px auto;
      padding: 0 16px;
      display: flex;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: 8px;
    }
    <?php if ($fitOff): ?>
    html, body, body.print-body, .invoice-sheet, .invoice-sheet * {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    html, body.print-body { background: #fff !important; margin: 0; padding: 0; }
    .sheet-wrap, .sheet-stage { padding: 0 !important; margin: 0 !important; }
    .invoice-sheet { transform: none !important; zoom: 1 !important; box-shadow: none !important; margin: 0 auto !important; }
    .share-toolbar { display: none !important; }
    <?php endif; ?>
    @media (max-width: 720px) {
      .share-toolbar { max-width: none; padding: 12px; }
      .share-toolbar .btn { flex: 1; min-height: 44px; }
      .sheet-wrap { padding: 8px; }
    }
    @media print {
      .share-toolbar { display: none !important; }
    }
  </style>
</head>
<body
  class="print-body<?= $thermal ? ' print-thermal' : '' ?>"
  data-pdf-name="<?= h($pdfName) ?>"
  <?= $auto ? ' data-autodownload="1" data-pdf-exact="1"' : '' ?>
>
  <?php if (!$print && !$asSheet && !$auto): ?>
    <div class="share-toolbar">
      <a
        class="btn ghost sm"
        href="<?= h(url('share.php?id=' . $id . '&t=' . $token . '&download=1')) ?>"
        data-pdf-download
        data-doc-id="<?= $id ?>"
        data-pdf-name="<?= h($pdfName) ?>"
        data-sheet-url="<?= h(url('share.php?id=' . $id . '&t=' . $token . '&autodownload=1')) ?>"
        title="Download PDF"
        aria-label="Download PDF"
      ><?= icon('pdf', 15) ?> PDF</a>
      <a class="btn ghost sm" href="<?= h(url('share.php?id=' . $id . '&t=' . $token . '&print=1')) ?>"><?= icon('printer', 15) ?>Print</a>
    </div>
  <?php endif; ?>
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
  <?php if (!$fitOff): ?>
  <script src="<?= h(asset('js/sheet-fit.js')) ?>"></script>
  <?php endif; ?>
  <?php if ($auto): ?>
  <script src="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <script src="<?= h(asset('js/pdf-download.js')) ?>" defer data-html2pdf="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <?php elseif (!$asSheet): ?>
  <script src="<?= h(asset('js/pdf-download.js')) ?>" defer data-html2pdf="<?= h(asset('js/vendor/html2pdf.bundle.min.js')) ?>"></script>
  <?php endif; ?>
  <?php if ($print): ?>
    <script src="<?= h(asset('js/print-sheet.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>
