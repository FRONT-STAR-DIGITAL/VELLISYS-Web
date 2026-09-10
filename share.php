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
require ROOT_PATH . '/includes/sheet.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h($doc['number']) ?> · <?= h($brand['name']) ?></title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars($brand) ?> }
    @page { size: A4; margin: 0; }
    .share-toolbar {
      max-width: 210mm;
      margin: 16px auto;
      padding: 0 16px;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }
    @media (max-width: 720px) {
      .share-toolbar { max-width: none; padding: 12px; }
      .share-toolbar .btn { flex: 1; min-height: 44px; }
      .sheet-wrap { padding: 10px 0; }
    }
    @media print {
      .share-toolbar { display: none !important; }
    }
  </style>
</head>
<body class="print-body">
  <?php if (!$print): ?>
    <div class="share-toolbar">
      <a class="btn ghost sm" href="<?= h(url('share.php?id=' . $id . '&t=' . $token . '&print=1')) ?>"><?= icon('printer', 15) ?>Print</a>
    </div>
  <?php endif; ?>
  <div class="sheet-wrap">
    <div class="sheet-stage">
      <?php render_sheet($brand, $doc); ?>
    </div>
  </div>
  <?php if ($print): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
  <?php else: ?>
    <script src="<?= h(asset('js/sheet-fit.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>
