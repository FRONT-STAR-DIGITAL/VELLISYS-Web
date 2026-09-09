<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$kind = $_GET['kind'] ?? 'invoice';
if (!in_array($kind, ['invoice', 'quotation', 'receipt', 'expense', 'letter'], true)) {
    $kind = 'invoice';
}
$meta = kind_meta($kind);
$rows = list_documents($kind);

layout_start($meta['title'], $user, ['kind' => $kind]);
?>
<div class="page-head">
  <div>
    <h1><?= icon($kind) ?><?= h($meta['title']) ?></h1>
    <p class="lede">
      <?php if ($kind === 'expense'): ?>
        Bills in a table with totals. Open a row to see the expense as a card, not stationery.
      <?php elseif ($kind === 'letter'): ?>
        Headed notes using the five templates. The word “letter” is not printed on the page.
      <?php else: ?>
        Every row has actions - view, edit, print, email<?= $kind === 'quotation' ? ', convert to invoice' : '' ?><?= $kind === 'invoice' ? ', take a receipt (full or part)' : '' ?>, or void.
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <a class="btn ghost" href="<?= h(export_query('documents', ['kind' => $kind])) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn" href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= icon($kind) ?><?= h($meta['verb']) ?></a>
  </div>
</div>

<?php render_filters('documents.php', ['kind' => $kind]); ?>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No <?= h(strtolower($meta['title'])) ?> in this period. <a href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= h($meta['verb']) ?></a>.</p>
  <?php else: ?>
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
          <th><?= $kind === 'expense' ? 'Payee' : 'Client' ?></th>
          <th>Date</th>
          <?php if ($kind === 'expense'): ?>
            <th>Category</th>
            <th class="right">Amount</th>
            <th class="right">Paid</th>
            <th class="right">Balance</th>
          <?php elseif ($kind === 'invoice'): ?>
            <th class="right">Amount</th>
            <th class="right">Balance</th>
          <?php elseif ($kind !== 'letter'): ?>
            <th class="right">Amount</th>
          <?php endif; ?>
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
            <?php if ($kind === 'expense'): ?>
              <td><?= h($doc['expense_category'] ?: 'Other') ?></td>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['paid'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <?php elseif ($kind === 'invoice'): ?>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <?php elseif ($kind !== 'letter'): ?>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <?php endif; ?>
            <td><span class="pill<?= invoice_status_label($doc) === 'Overdue' ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($kind !== 'letter'): ?>
        <tfoot>
          <tr>
            <td colspan="3">Totals</td>
            <?php if ($kind === 'expense'): ?>
              <td></td>
              <td class="right mono"><?= h(money(documents_sum($rows))) ?></td>
              <td class="right mono"><?= h(money(documents_sum($rows, 'paid'))) ?></td>
              <td class="right mono"><?= h(money(documents_sum($rows, 'balance'))) ?></td>
            <?php elseif ($kind === 'invoice'): ?>
              <td class="right mono"><?= h(money(documents_sum($rows))) ?></td>
              <td class="right mono"><?= h(money(documents_sum($rows, 'balance'))) ?></td>
            <?php else: ?>
              <td class="right mono"><?= h(money(documents_sum($rows))) ?></td>
            <?php endif; ?>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
