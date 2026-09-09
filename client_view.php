<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? 0);
$cid = current_company_id();
$party = db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
if (!$party) {
    flash('Client not found.', 'err');
    redirect('clients.php');
}

[$extra, $types, $params] = period_sql('d.date');
$docs = attach_document_totals(db_all(
    'SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id WHERE d.company_id = ? AND d.party_id = ?' . $extra . ' ORDER BY d.date DESC, d.id DESC',
    'ii' . $types,
    array_merge([$cid, $id], $params)
));
$byKind = ['invoice' => [], 'quotation' => [], 'receipt' => [], 'expense' => [], 'letter' => []];
foreach ($docs as $d) {
    $byKind[$d['kind']][] = $d;
}

layout_start($party['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?><?= h($party['name']) ?></h1>
    <p class="lede">
      <?= h($party['kind']) ?>
      <?php if ($party['tin']): ?> · TIN <?= h($party['tin']) ?><?php endif; ?>
      <?php if ($party['email']): ?> · <?= h($party['email']) ?><?php endif; ?>
      <?php if ($party['phone']): ?> · <?= h($party['phone']) ?><?php endif; ?>
    </p>
    <?php if ($party['address']): ?><p class="lede"><?= h($party['address']) ?></p><?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(export_query('party', ['id' => (string) $id])) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn ghost" href="<?= h(url('client_edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Edit</a>
  </div>
</div>

<?php render_filters('client_view.php', ['id' => (string) $id]); ?>

<div class="action-grid">
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=invoice&party=' . $id)) ?>">
    <?= icon('invoice', 20) ?>
    <span>New invoice</span>
    <strong><?= count($byKind['invoice']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=quotation&party=' . $id)) ?>">
    <?= icon('quotation', 20) ?>
    <span>New quotation</span>
    <strong><?= count($byKind['quotation']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=receipt&party=' . $id)) ?>">
    <?= icon('receipt', 20) ?>
    <span>New receipt</span>
    <strong><?= count($byKind['receipt']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=letter&party=' . $id)) ?>">
    <?= icon('letter', 20) ?>
    <span>New correspondence</span>
    <strong><?= count($byKind['letter']) ?> issued</strong>
  </a>
  <a class="card action-tile" href="<?= h(url('document_new.php?kind=expense&party=' . $id)) ?>">
    <?= icon('expense', 20) ?>
    <span>Record expense</span>
    <strong><?= count($byKind['expense']) ?> recorded</strong>
  </a>
</div>

<?php foreach (['invoice' => 'Invoices', 'quotation' => 'Quotations', 'receipt' => 'Receipts', 'letter' => 'Correspondence', 'expense' => 'Expenses'] as $kind => $title): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-head">
      <h2><?= icon($kind, 16) ?><?= h($title) ?></h2>
      <a class="btn sm" href="<?= h(url('document_new.php?kind=' . $kind . '&party=' . $id)) ?>"><?= icon('plus', 14) ?>Add</a>
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
                <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <?php endif; ?>
              <td><span class="pill"><?= h(invoice_status_label($doc)) ?></span></td>
              <td class="row-actions"><?php render_doc_actions($doc); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($kind !== 'letter'): ?>
          <tfoot>
            <tr>
              <td colspan="2">Totals</td>
              <td class="right mono"><?= h(money(documents_sum($byKind[$kind]))) ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php layout_end(); ?>
