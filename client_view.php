<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$party = db_one('SELECT * FROM parties WHERE id = ?', 'i', [$id]);
if (!$party) {
    flash('Client not found.', 'err');
    redirect('clients.php');
}

$docs = attach_document_totals(db_all(
    'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.party_id = ? ORDER BY d.date DESC, d.id DESC',
    'i',
    [$id]
));
$byKind = ['invoice' => [], 'quotation' => [], 'receipt' => [], 'expense' => [], 'letter' => []];
foreach ($docs as $d) {
    $byKind[$d['kind']][] = $d;
}

layout_start($party['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= h($party['name']) ?></h1>
    <p class="lede">
      <?= h($party['kind']) ?>
      <?php if ($party['tin']): ?> · TIN <?= h($party['tin']) ?><?php endif; ?>
      <?php if ($party['email']): ?> · <?= h($party['email']) ?><?php endif; ?>
      <?php if ($party['phone']): ?> · <?= h($party['phone']) ?><?php endif; ?>
    </p>
    <?php if ($party['address']): ?><p class="lede"><?= h($party['address']) ?></p><?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(url('client_edit.php?id=' . $id)) ?>">Edit</a>
  </div>
</div>

<div class="action-grid">
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=invoice&party=' . $id)) ?>">
    <span>New invoice</span>
    <strong><?= count($byKind['invoice']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=quotation&party=' . $id)) ?>">
    <span>New quotation</span>
    <strong><?= count($byKind['quotation']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=receipt&party=' . $id)) ?>">
    <span>New receipt</span>
    <strong><?= count($byKind['receipt']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=letter&party=' . $id)) ?>">
    <span>New letter</span>
    <strong><?= count($byKind['letter']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=expense&party=' . $id)) ?>">
    <span>Record expense</span>
    <strong><?= count($byKind['expense']) ?> recorded</strong>
  </a>
</div>

<?php foreach (['invoice' => 'Invoices', 'quotation' => 'Quotations', 'receipt' => 'Receipts', 'letter' => 'Letters', 'expense' => 'Expenses'] as $kind => $title): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-head">
      <h2><?= h($title) ?></h2>
      <a class="btn sm" href="<?= h(url('document_new.php?kind=' . $kind . '&party=' . $id)) ?>">Add</a>
    </div>
    <?php if (!$byKind[$kind]): ?>
      <p class="empty">None yet.</p>
    <?php else: ?>
      <table class="grid">
        <thead>
          <tr>
            <th>Number</th>
            <th>Date</th>
            <?php if ($kind !== 'letter'): ?><th class="right">Amount</th><?php endif; ?>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byKind[$kind] as $doc): ?>
            <tr>
              <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
              <td><?= h(format_date($doc['date'])) ?></td>
              <?php if ($kind !== 'letter'): ?>
                <td class="right mono"><?= h(ugx($doc['totals']['total'])) ?></td>
              <?php endif; ?>
              <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
              <td class="row-actions"><?php render_doc_actions($doc); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php layout_end(); ?>
