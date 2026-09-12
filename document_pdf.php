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

require_once ROOT_PATH . '/includes/designs.php';
layout_start('PDF ' . $doc['number'], $user, ['kind' => $doc['kind']]);
?>
<div class="page-head">
  <div>
    <h1><?= icon('file') ?>PDF</h1>
    <p class="lede"><?= h($doc['number']) ?> - same branded sheet as View. Download saves it as a PDF from the print dialog.</p>
  </div>
  <div class="actions pdf-actions sticky-save">
    <button class="btn" type="button" data-print-pdf><?= icon('download', 15) ?>Download PDF</button>
    <a class="btn ghost" href="<?= h(url('document_view.php?id=' . $id)) ?>"><?= icon('eye', 15) ?>View</a>
  </div>
</div>

<div class="sheet-wrap">
  <div class="sheet-stage">
    <?php render_sheet(branding(), $doc); ?>
  </div>
</div>
<?php
layout_end();
