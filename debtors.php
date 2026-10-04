<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$addError = '';
$showAdd = isset($_GET['add']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_ledger') {
    csrf_check();
    $showAdd = true;
    try {
        $id = create_quick_ledger_entry('debtor');
        $doc = load_document($id);
        flash('Debtor saved' . ($doc ? ' as ' . $doc['number'] : '') . '.');
        redirect('debtors.php');
    } catch (Throwable $e) {
        $addError = $e->getMessage();
    }
}

$rows = list_open_debtors();
$total = array_sum(array_map(static fn ($d) => convert_money(document_due_amount($d), doc_currency($d), default_currency()), $rows));
$overdue = array_sum(array_map(static function ($d) {
    if (($d['kind'] ?? '') !== 'invoice' || empty($d['due_date']) || $d['due_date'] >= today()) {
        return 0;
    }
    return convert_money(document_due_amount($d), doc_currency($d), default_currency());
}, $rows));
$byClient = [];
foreach ($rows as $doc) {
    $pid = (int) $doc['party_id'];
    $due = convert_money(document_due_amount($doc), doc_currency($doc), default_currency());
    if (!isset($byClient[$pid])) {
        $byClient[$pid] = ['id' => $pid, 'name' => $doc['party_name'], 'invoices' => 0, 'balance' => 0.0, 'pay_id' => (int) $doc['id']];
    }
    $byClient[$pid]['invoices']++;
    $byClient[$pid]['balance'] += $due;
    if ($due > (float) ($byClient[$pid]['pay_balance'] ?? 0)) {
        $byClient[$pid]['pay_id'] = (int) $doc['id'];
        $byClient[$pid]['pay_balance'] = $due;
    }
}
uasort($byClient, static fn ($a, $b) => $b['balance'] <=> $a['balance']);
$parties = ledger_parties_for_picker();

layout_start('Debtors', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('clients') ?>Debtors</h1>
    <p class="lede">Clients who still owe you - open invoices and quick receipts that were only part paid. Mixed currencies convert at your <?= h(default_currency()) ?> / USD rate. Take a receipt, send a reminder by email or WhatsApp, or print the document.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= h(url('debtors.php?add=1#ledger-add')) ?>"><?= icon('plus', 16) ?>Add new</a>
    <a class="btn ghost" href="<?= h(url('document_new.php?kind=invoice')) ?>"><?= icon('invoice') ?>New invoice</a>
    <a class="btn ghost" href="<?= h(export_query('debtors')) ?>"><?= icon('download', 16) ?>Export CSV</a>
  </div>
</div>

<?php render_ledger_add_form('debtor', $parties, $showAdd || $addError !== '', $addError); ?>

<?php render_filters('debtors.php'); ?>

<?php if ($byClient): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('clients', 16) ?>By client</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Client</th>
        <th class="right">Open items</th>
        <th class="right">Balance</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byClient as $c): ?>
        <tr>
          <td><a href="<?= h(url('client_view.php?id=' . $c['id'])) ?>"><?= h($c['name']) ?></a></td>
          <td class="right mono"><?= (int) $c['invoices'] ?></td>
          <td class="right mono"><?= h(ugx($c['balance'])) ?></td>
          <td class="row-actions">
            <a class="btn sm" href="<?= h(url('document_action.php?receive=' . (int) $c['pay_id'])) ?>"><?= icon('receipt', 15) ?> Make payment</a>
            <?php
              $remindDoc = null;
              foreach ($rows as $openDoc) {
                  if ((int) ($openDoc['party_id'] ?? 0) === (int) $c['id'] && ($openDoc['kind'] ?? '') === 'invoice') {
                      $remindDoc = $openDoc;
                      break;
                  }
              }
              if ($remindDoc) {
                  render_remind_button($remindDoc, true);
              }
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="stats">
  <div class="card stat"><?= icon('invoice', 20) ?><span>Open items</span><strong><?= count($rows) ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Amount owed</span><strong><?= h(ugx($total)) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Overdue</span><strong><?= h(ugx($overdue)) ?></strong></div>
  <div class="card stat"><?= icon('send', 20) ?><span>Action</span><strong>Receipt or email</strong></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="empty">No outstanding invoices or part-paid receipts in this period.</p>
  <?php else: ?>
    <div class="table-scroll">
    <table class="grid">
      <thead>
        <tr>
          <th>Number</th>
          <th>Client</th>
          <th>Date</th>
          <th>Due</th>
          <th class="right">Amount</th>
          <th class="right">Balance</th>
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
            <td><?= h(format_date($doc['due_date'])) ?></td>
            <td class="right mono"><?= h(money($doc['totals']['total'], doc_currency($doc))) ?></td>
            <td class="right mono"><?= h(money(document_due_amount($doc), doc_currency($doc))) ?></td>
            <td><span class="pill<?= in_array(invoice_status_label($doc), ['Overdue', 'Partially cleared'], true) ? ' warn' : '' ?>"><?= h(invoice_status_label($doc)) ?></span></td>
            <td class="row-actions"><?php if (($doc['kind'] ?? '') !== 'receipt') { render_make_payment_button($doc, true); } render_remind_button($doc, true); render_doc_actions($doc, false, false); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Totals</td>
          <td class="right mono"><?= h(ugx(documents_sum($rows))) ?></td>
          <td class="right mono"><?= h(ugx($total)) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_end(); ?>
