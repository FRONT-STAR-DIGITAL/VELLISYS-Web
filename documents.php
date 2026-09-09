<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$kind = $_GET['kind'] ?? 'invoice';
if (!in_array($kind, ['invoice', 'quotation', 'receipt', 'expense', 'letter'], true)) {
    $kind = 'invoice';
}
$meta = kind_meta($kind);
$rows = attach_document_totals(db_all(
    'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.kind = ? ORDER BY d.date DESC, d.id DESC',
    's',
    [$kind]
));

layout_start($meta['title'], $user, ['kind' => $kind]);
?>
<div class="page-head">
  <div>
    <h1><?= h($meta['title']) ?></h1>
    <p class="lede">Every row has actions — view, print, email<?= $kind === 'quotation' ? ', convert to invoice' : '' ?><?= $kind === 'invoice' ? ', take a receipt' : '' ?>, or void.</p>
  </div>
  <a class="btn" href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= h($meta['verb']) ?></a>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No <?= h(strtolower($meta['title'])) ?> yet. <a href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= h($meta['verb']) ?></a>.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
          <th><?= $kind === 'expense' ? 'Payee' : 'Client' ?></th>
          <th>Date</th>
          <?php if ($kind !== 'letter'): ?><th class="right">Amount</th><?php endif; ?>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $doc): ?>
          <tr>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
            <td><a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a></td>
            <td><?= h(format_date($doc['date'])) ?></td>
            <?php if ($kind !== 'letter'): ?>
              <td class="right mono"><?= h(ugx($kind === 'invoice' ? $doc['balance'] : $doc['totals']['total'])) ?></td>
            <?php endif; ?>
            <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
