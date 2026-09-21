<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$kind = $_GET['kind'] ?? 'invoice';
if (!in_array($kind, desk_kind_list(), true)) {
    $kind = 'invoice';
}
require_desk_kind($kind);
$meta = kind_meta($kind);
$rows = list_documents($kind);
$clearFilter = $_GET['clear'] ?? 'all';
$clearedRows = [];
$partialRows = [];
if ($kind === 'receipt') {
    foreach ($rows as $doc) {
        if ($doc['status'] === 'void') {
            continue;
        }
        if (document_due_amount($doc) > 0.009) {
            $partialRows[] = $doc;
        } else {
            $clearedRows[] = $doc;
        }
    }
    if ($clearFilter === 'cleared') {
        $rows = $clearedRows;
    } elseif ($clearFilter === 'partial') {
        $rows = $partialRows;
    }
}
$period = period_range();
$receiptQs = static function (string $clear) use ($kind, $period): string {
    return http_build_query([
        'kind' => $kind,
        'range' => $period['preset'],
        'from' => $period['from'],
        'to' => $period['to'],
        'clear' => $clear,
    ]);
};

layout_start($meta['title'], $user, ['kind' => $kind]);
?>
<?php if (in_array($kind, ['refund', 'return_note'], true) && function_exists('render_pnl_subnav')): ?>
  <?php render_pnl_subnav($kind); ?>
<?php endif; ?>
<div class="page-head">
  <div>
    <h1><?= icon(document_kind_icon($kind)) ?><?= h($meta['title']) ?></h1>
    <p class="lede">
      <?php if ($kind === 'expense'): ?>
        Company spending - open a row to see the expense details. Not a bill sent to a client.
      <?php elseif ($kind === 'letter'): ?>
        Headed letters with a subject and body, on the same document design as the rest of the books. Download a Word letterhead when you need to type the rest in Microsoft Word.
      <?php elseif ($kind === 'custom'): ?>
        <?= h($meta['singular']) ?> using the fields set when this company was onboarded.
      <?php elseif ($kind === 'delivery'): ?>
        Goods out: item, description and quantity. No prices.
      <?php elseif ($kind === 'receipt'): ?>
        Cleared receipts are paid in full. Partially cleared receipts still have an amount due on Debtors.
      <?php else: ?>
        Every row has actions - view, edit, print, email<?= $kind === 'quotation' ? ', convert to invoice' : '' ?><?= $kind === 'invoice' ? ', take a receipt (full or part)' : '' ?>, or delete.
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($kind === 'letter'): ?>
      <a class="btn ghost" href="<?= h(url('letter_docx.php')) ?>"><?= icon('download', 16) ?>Word template</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= h(export_query('documents', ['kind' => $kind])) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <a class="btn" href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= icon(document_kind_icon($kind)) ?><?= h($meta['verb']) ?></a>
  </div>
</div>

<?php render_filters('documents.php', ['kind' => $kind]); ?>

<?php if ($kind === 'receipt'): ?>
  <div class="docs-extra">
    <div class="stats">
      <div class="card stat"><?= icon('receipt', 20) ?><span>Cleared</span><strong><?= count($clearedRows) ?></strong></div>
      <div class="card stat"><?= icon('alert', 20) ?><span>Partially cleared</span><strong><?= count($partialRows) ?></strong></div>
    </div>
    <p class="filter-chips">
      <a class="chip<?= $clearFilter === 'all' ? ' is-on' : '' ?>" href="<?= h(url('documents.php?' . $receiptQs('all'))) ?>">All</a>
      <a class="chip<?= $clearFilter === 'cleared' ? ' is-on' : '' ?>" href="<?= h(url('documents.php?' . $receiptQs('cleared'))) ?>">Cleared</a>
      <a class="chip<?= $clearFilter === 'partial' ? ' is-on' : '' ?>" href="<?= h(url('documents.php?' . $receiptQs('partial'))) ?>">Partially cleared</a>
    </p>
  </div>
<?php endif; ?>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No <?= h(strtolower($meta['title'])) ?> in this period. <a href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= h($meta['verb']) ?></a>.</p>
  <?php else: ?>
    <div class="table-scroll">
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
          <?php elseif ($kind === 'receipt'): ?>
            <th class="right">Received</th>
            <th class="right">Due</th>
          <?php elseif (!kind_shows_money($kind)): ?>
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
            <td class="date-cell"><?= h(format_date($doc['date'])) ?></td>
            <?php if ($kind === 'expense'): ?>
              <td><?= h($doc['expense_category'] ?: 'Other') ?></td>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['paid'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <?php elseif ($kind === 'invoice'): ?>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money($doc['balance'], doc_currency($doc))) ?></td>
            <?php elseif ($kind === 'receipt'): ?>
              <td class="right mono"><?= h(money($doc['paid'], doc_currency($doc))) ?></td>
              <td class="right mono"><?= h(money(document_due_amount($doc), doc_currency($doc))) ?></td>
            <?php elseif (!kind_shows_money($kind)): ?>
            <?php elseif ($kind !== 'letter'): ?>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <?php endif; ?>
            <td><span class="pill<?= in_array(invoice_status_label($doc), ['Overdue', 'Partially cleared'], true) ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if (kind_shows_money($kind)): ?>
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
            <?php elseif ($kind === 'receipt'): ?>
              <td class="right mono"><?= h(money(documents_sum($rows, 'paid'))) ?></td>
              <td class="right mono"><?= h(money(array_sum(array_map(static fn ($d) => convert_money(document_due_amount($d), doc_currency($d), default_currency()), $rows)))) ?></td>
            <?php else: ?>
              <td class="right mono"><?= h(money(documents_sum($rows))) ?></td>
            <?php endif; ?>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
