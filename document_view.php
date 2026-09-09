<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

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
  <title><?= h($doc['number']) ?></title>
  <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
  <style>:root { --brand: <?= h(brand_color()) ?>; }</style>
</head>
<body class="print-body">
  <?php require ROOT_PATH . '/includes/sheet.php'; render_sheet($brand, $doc); ?>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
    <?php
    exit;
}

layout_start(kind_meta($doc['kind'])['singular'] . ' ' . $doc['number'], $user, ['kind' => $doc['kind']]);
require ROOT_PATH . '/includes/sheet.php';
?>
<div class="page-head">
  <div>
    <h1><?= icon($doc['kind']) ?><?= h($doc['number']) ?></h1>
    <p class="lede">
      <a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a>
      · <?= h(invoice_status_label($doc)) ?>
      <?php if ($doc['kind'] === 'invoice'): ?>
        · Balance <?= h(ugx(invoice_balance($doc))) ?>
      <?php endif; ?>
    </p>
  </div>
  <?php render_doc_actions($doc); ?>
</div>

<div class="sheet-wrap">
  <?php render_sheet(branding(), $doc); ?>
</div>

<?php if ($emails): ?>
  <div class="card" style="margin-top:20px">
    <div class="card-head"><h2><?= icon('letter', 16) ?>Emails sent</h2></div>
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
