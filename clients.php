<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$cid = current_company_id();
$filter = strtolower(trim((string) ($_GET['status'] ?? 'open')));
if (!in_array($filter, ['open', 'active', 'inactive', 'all'], true)) {
    $filter = 'open';
}
$statusSql = " AND (p.status IS NULL OR p.status <> 'deleted')";
if ($filter === 'active') {
    $statusSql = " AND (p.status IS NULL OR p.status = 'active')";
} elseif ($filter === 'inactive') {
    $statusSql = " AND p.status = 'inactive'";
} elseif ($filter === 'all') {
    $statusSql = '';
}

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
     WHERE p.company_id = ?{$statusSql}
     ORDER BY FIELD(p.status,'active','inactive','deleted'), p.name",
    'ii',
    [$cid, $cid]
);

layout_start('Clients', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Clients</h1>
    <p class="lede">Open a name to invoice, quote, receipt, write a letter, or see their documents. Mark a client inactive when you still need the history, or delete them from the list.</p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(export_query('clients')) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn ghost" href="<?= h(url('tutorials.php')) ?>"><?= icon('book', 16) ?>Tutorials</a>
    <a class="btn" href="<?= h(url('client_edit.php')) ?>"><?= icon('plus') ?>New client</a>
  </div>
</div>

<div class="filter-chips" style="margin:0 0 16px">
  <?php foreach (['open' => 'On the books', 'active' => 'Active', 'inactive' => 'Inactive', 'all' => 'Including removed'] as $key => $label): ?>
    <a class="chip<?= $filter === $key ? ' is-on' : '' ?>" href="<?= h(url('clients.php?status=' . $key)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$parties): ?>
    <p class="empty">No clients<?= $filter === 'inactive' ? ' marked inactive' : '' ?>. <a href="<?= h(url('client_edit.php')) ?>">Add one</a>.</p>
  <?php else: ?>
    <div class="table-scroll clients-table">
    <table class="grid">
      <thead>
        <tr>
          <th>Name</th>
          <th>Kind</th>
          <th>Status</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Docs</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parties as $p):
            $st = party_status($p);
            $nextStatus = $st === 'inactive' ? 'active' : 'inactive';
            ?>
          <tr class="<?= $st !== 'active' ? 'is-muted' : '' ?>">
            <td><a href="<?= h(url('client_view.php?id=' . $p['id'])) ?>"><strong><?= h($p['name']) ?></strong></a></td>
            <td><?= h($p['kind']) ?></td>
            <td><span class="pill<?= $st === 'inactive' ? ' warn' : '' ?>"><?= h(party_status_label($p)) ?></span></td>
            <td><?= h($p['email']) ?></td>
            <td><?= h($p['phone']) ?></td>
            <td class="mono"><?= (int) $p['invoices'] ?> inv · <?= (int) $p['quotes'] ?> qtn · <?= (int) $p['receipts'] ?> rct</td>
            <td class="row-actions">
              <div class="actions">
                <a class="btn sm icon-only" href="<?= h(url('client_view.php?id=' . $p['id'])) ?>" title="View" aria-label="View"><?= icon('eye', 15) ?></a>
                <a class="btn ghost sm icon-only" href="<?= h(url('document_new.php?kind=invoice&party=' . $p['id'])) ?>" title="Invoice" aria-label="Invoice"><?= icon('invoice', 15) ?></a>
                <a class="btn ghost sm icon-only" href="<?= h(url('client_edit.php?id=' . $p['id'])) ?>" title="Edit" aria-label="Edit"><?= icon('pencil', 15) ?></a>
                <?php if ($st !== 'deleted'): ?>
                  <form method="post" action="<?= h(url('client_action.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="status" value="<?= h($nextStatus) ?>">
                    <button class="btn ghost sm icon-only" type="submit" title="<?= $st === 'inactive' ? 'Mark active' : 'Mark inactive' ?>" aria-label="<?= $st === 'inactive' ? 'Mark active' : 'Mark inactive' ?>"><?= icon($st === 'inactive' ? 'check' : 'ban', 15) ?></button>
                  </form>
                  <?php render_party_delete_button((int) $p['id']); ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
