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
$voidKinds = ['quotation', 'invoice', 'receipt'];
$supportsVoidTab = in_array($kind, $voidKinds, true);
$view = strtolower((string) ($_GET['view'] ?? 'active'));
if (!$supportsVoidTab || !in_array($view, ['active', 'void'], true)) {
    $view = 'active';
}
$rows = list_documents($kind);
// Personal Creditors ledger is standalone - keep it off the Expenses list.
if ($kind === 'expense') {
    $rows = array_values(array_filter($rows, static fn ($d) => !is_personal_creditor($d)));
}
if ($supportsVoidTab) {
    $rows = array_values(array_filter(
        $rows,
        static fn ($d) => $view === 'void'
            ? ($d['status'] ?? '') === 'void'
            : ($d['status'] ?? '') !== 'void'
    ));
}
$clearFilter = $_GET['clear'] ?? 'all';
$clearedRows = [];
$partialRows = [];
if ($kind === 'receipt' && $view !== 'void') {
    foreach ($rows as $doc) {
        if (($doc['status'] ?? '') === 'void') {
            continue;
        }
        if (receipt_list_due($doc) > 0.009) {
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
$branchKeep = function_exists('desk_branch_keep') ? desk_branch_keep() : [];
$docListQuery = static function (array $extra = []) use ($kind, $period, $branchKeep): string {
    $q = array_merge($branchKeep, [
        'kind' => $kind,
        'range' => $period['preset'],
        'from' => $period['from'],
        'to' => $period['to'],
    ], $extra);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        }
    }
    return http_build_query($q);
};
$receiptQs = static function (string $clear) use ($docListQuery): string {
    return $docListQuery(['clear' => $clear]);
};
$filterKeep = array_merge(['kind' => $kind], $branchKeep);
if ($supportsVoidTab && $view === 'void') {
    $filterKeep['view'] = 'void';
}
if ($kind === 'receipt' && $view !== 'void' && in_array($clearFilter, ['cleared', 'partial'], true)) {
    $filterKeep['clear'] = $clearFilter;
}
$sideChips = [];
if ($supportsVoidTab) {
    $activeExtra = [];
    if ($kind === 'receipt' && in_array($clearFilter, ['cleared', 'partial'], true)) {
        $activeExtra['clear'] = $clearFilter;
    }
    $sideChips = [
        [
            'label' => 'Active',
            'on' => $view !== 'void',
            'href' => url('documents.php?' . $docListQuery($activeExtra)),
        ],
        [
            'label' => 'Void',
            'on' => $view === 'void',
            'href' => url('documents.php?' . $docListQuery(['view' => 'void'])),
        ],
    ];
}

layout_start($meta['title'], $user, ['kind' => $kind]);
?>
<?php if (in_array($kind, ['refund', 'return_note'], true) && function_exists('render_pnl_subnav')): ?>
  <?php render_pnl_subnav($kind); ?>
<?php endif; ?>
<div class="page-head">
  <div>
    <h1><?= icon(document_kind_icon($kind)) ?><?= h($meta['title']) ?></h1>
    <p class="lede">
      <?php if ($view === 'void'): ?>
        Void <?= h(strtolower($meta['title'])) ?> stay out of the main list and totals. Open one to review it, or delete it permanently from here.
      <?php elseif ($kind === 'expense'): ?>
        Company spending - open a row to see the expense details. Not a bill sent to a client.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede('expenses')) : '' ?>
      <?php elseif ($kind === 'letter'): ?>
        Headed letters with a subject and body, on the same document design as the rest of the books. Download a Word letterhead when you need to type the rest in Microsoft Word.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede('letters')) : '' ?>
      <?php elseif ($kind === 'custom'): ?>
        <?= h($meta['singular']) ?> using the fields set when this company was onboarded.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede(strtolower($meta['title']))) : '' ?>
      <?php elseif ($kind === 'delivery'): ?>
        Goods out: item, description and quantity. No prices.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede('delivery notes')) : '' ?>
      <?php elseif ($kind === 'receipt'): ?>
        Cleared receipts are paid in full. Partially cleared receipts still have an amount due on Debtors.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede('receipts')) : '' ?>
      <?php else: ?>
        Every row has actions - view, edit, print, email<?= $kind === 'quotation' ? ', convert to invoice' : '' ?><?= $kind === 'invoice' ? ', take a receipt (full or part)' : '' ?>, or delete.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede(strtolower($meta['title']))) : '' ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($kind === 'letter'): ?>
      <a class="btn ghost" href="<?= h(url('letter_docx.php')) ?>"><?= icon('download', 16) ?>Word template</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= h(export_query('documents', array_filter(['kind' => $kind, 'view' => $view === 'void' ? 'void' : null]))) ?>"><?= icon('download', 16) ?>Export CSV</a>
    <?php if ($view !== 'void'): ?>
      <a class="btn" href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= icon(document_kind_icon($kind)) ?><?= h($meta['verb']) ?></a>
    <?php endif; ?>
  </div>
</div>
<?php if (function_exists('render_desk_branch_chips')) {
    render_desk_branch_chips('documents.php', array_merge(
        ['kind' => $kind],
        $supportsVoidTab && $view === 'void' ? ['view' => 'void'] : []
    ));
} ?>

<?php render_filters('documents.php', $filterKeep, $sideChips ? ['side_chips' => $sideChips] : []); ?>

<?php if ($kind === 'receipt' && $view !== 'void'): ?>
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
    <p class="empty">
      <?php if ($view === 'void'): ?>
        No void <?= h(strtolower($meta['title'])) ?> in this period.
      <?php else: ?>
        No <?= h(strtolower($meta['title'])) ?> in this period. <a href="<?= h(url('document_new.php?kind=' . $kind)) ?>"><?= h($meta['verb']) ?></a>.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <?php $showRowNo = in_array($kind, ['quotation', 'invoice', 'receipt'], true); ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <?php if ($showRowNo): ?><th class="right" style="width:3rem">No.</th><?php endif; ?>
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
        <?php foreach ($rows as $i => $doc): ?>
          <tr>
            <?php if ($showRowNo): ?><td class="right mono"><?= (int) $i + 1 ?></td><?php endif; ?>
            <td class="mono"><a href="<?= h(url('document_view.php?id=' . $doc['id'])) ?>"><?= h($doc['number']) ?></a></td>
            <td><?php if ((int) ($doc['party_id'] ?? 0) > 0): ?>
              <a href="<?= h(url('client_view.php?id=' . $doc['party_id'])) ?>"><?= h($doc['party_name']) ?></a>
            <?php else: ?>
              <?= $kind === 'expense' ? 'No payee' : '-' ?>
            <?php endif; ?></td>
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
              <td class="right mono"><?= h(money(receipt_list_due($doc), doc_currency($doc))) ?></td>
            <?php elseif (!kind_shows_money($kind)): ?>
            <?php elseif ($kind !== 'letter'): ?>
              <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <?php endif; ?>
            <td><span class="pill<?= in_array(invoice_status_label($doc), ['Overdue', 'Partially cleared'], true) ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php render_doc_actions($doc); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if (kind_shows_money($kind) && $view !== 'void'): ?>
        <tfoot>
          <tr>
            <td colspan="<?= $showRowNo ? 4 : 3 ?>">Totals</td>
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
              <td class="right mono"><?= h(money(receipts_due_total_by_latest($rows))) ?></td>
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
