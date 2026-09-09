<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$cid = current_company_id();
$parties = db_all(
    "SELECT p.*,
        COALESCE(c.invoices,0) AS invoices,
        COALESCE(c.quotes,0) AS quotes,
        COALESCE(c.receipts,0) AS receipts
     FROM parties p
     LEFT JOIN (
       SELECT party_id,
         SUM(kind='invoice' AND status='issued') AS invoices,
         SUM(kind='quotation' AND status='issued') AS quotes,
         SUM(kind='receipt' AND status='issued') AS receipts
       FROM documents WHERE company_id = ? GROUP BY party_id
     ) c ON c.party_id = p.id
     WHERE p.company_id = ?
     ORDER BY p.name",
    'ii',
    [$cid, $cid]
);

layout_start('Clients', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Clients</h1>
    <p class="lede">Open a name to invoice, quote, receipt, write correspondence, or see their documents.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(export_query('clients')) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn" href="<?= h(url('client_edit.php')) ?>"><?= icon('plus') ?>New client</a>
  </div>
</div>

<div class="card">
  <?php if (!$parties): ?>
    <p class="empty">No clients yet. <a href="<?= h(url('client_edit.php')) ?>">Add one</a>.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Name</th>
          <th>Kind</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Docs</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parties as $p): ?>
          <tr>
            <td><a href="<?= h(url('client_view.php?id=' . $p['id'])) ?>"><strong><?= h($p['name']) ?></strong></a></td>
            <td><?= h($p['kind']) ?></td>
            <td><?= h($p['email']) ?></td>
            <td><?= h($p['phone']) ?></td>
            <td class="mono"><?= (int) $p['invoices'] ?> inv · <?= (int) $p['quotes'] ?> qtn · <?= (int) $p['receipts'] ?> rct</td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm" href="<?= h(url('client_view.php?id=' . $p['id'])) ?>"><?= icon('eye', 14) ?>Open</a>
                <a class="btn ghost sm" href="<?= h(url('document_new.php?kind=invoice&party=' . $p['id'])) ?>"><?= icon('invoice', 14) ?>Invoice</a>
                <a class="btn ghost sm" href="<?= h(url('document_new.php?kind=quotation&party=' . $p['id'])) ?>"><?= icon('quotation', 14) ?>Quote</a>
                <a class="btn ghost sm" href="<?= h(url('client_edit.php?id=' . $p['id'])) ?>"><?= icon('pencil', 14) ?>Edit</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
