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
$print = isset($_GET['print']);
$emails = db_all('SELECT * FROM emails WHERE document_id = ? ORDER BY id DESC LIMIT 8', 'i', [$id]);

if ($print) {
    $brand = branding();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= h($doc['number']) ?></title>
  <?php product_icons(); ?>
  <?php folio_css_links(); ?>
  <?php folio_font_links(); ?>
  <style>
    :root { <?= brand_css_vars() ?> }
    @page { size: A4; margin: 0; }
  </style>
</head>
<body class="print-body">
  <?php require ROOT_PATH . '/includes/sheet.php'; render_sheet($brand, $doc); ?>
  <script src="<?= h(asset('js/print-sheet.js')) ?>"></script>
</body>
</html>
    <?php
    exit;
}

layout_start(kind_meta($doc['kind'])['singular'] . ' ' . $doc['number'], $user, ['kind' => $doc['kind']]);
require ROOT_PATH . '/includes/sheet.php';
$ledeExtra = '';
if ($doc['kind'] === 'invoice') {
    $ledeExtra = ' · Balance ' . money(invoice_balance($doc), doc_currency($doc));
} elseif ($doc['kind'] === 'expense') {
    $ledeExtra = ' · Balance ' . money(expense_balance($doc), doc_currency($doc));
} elseif ($doc['kind'] === 'receipt') {
    $s = $doc['settlement'] ?? [];
    $ledeExtra = ' · RECEIVED ' . money($s['received'] ?? $doc['paid'], doc_currency($doc)) . ' · DUE ' . money($s['balance'] ?? 0, doc_currency($doc));
}
?>
<div class="page-head">
  <div>
    <h1><?= icon($doc['kind']) ?><?= h($doc['number']) ?></h1>
    <p class="lede">
      <a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a>
      · <?= h(invoice_status_label($doc)) ?>
      <?= h($ledeExtra) ?>
    </p>
  </div>
  <div class="actions">
    <?php render_doc_actions($doc, true); ?>
  </div>
</div>

<div class="sheet-wrap">
  <div class="sheet-stage">
    <?php render_sheet(branding(), $doc); ?>
  </div>
</div>

<?php if ($emails): ?>
  <div class="card" style="margin-top:20px">
    <div class="card-head"><h2><?= icon('send', 16) ?>Emails sent</h2></div>
    <table class="grid">
      <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($emails as $em): ?>
          <tr>
            <td><?= h($em['created_at']) ?></td>
            <td><?= h($em['to_email']) ?></td>
            <td><?= h($em['subject']) ?></td>
            <td><span class="pill"><?= h($em['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
