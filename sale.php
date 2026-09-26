<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_stock();

$error = '';
$dayError = '';
$dayOpen = stock_day_is_open();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'open_day' || $action === 'close_day') {
        $dayError = desk_handle_day_post();
        $dayOpen = stock_day_is_open();
    } else {
        stock_require_open_day();
        $ids = $_POST['s_item'] ?? [];
        $names = $_POST['s_name'] ?? [];
        $qtys = $_POST['s_qty'] ?? [];
        $prices = $_POST['s_price'] ?? [];
        $taxed = $_POST['s_taxed'] ?? [];
        $lines = [];
        foreach ((array) $ids as $i => $sid) {
            $lines[] = [
                'stock_item_id' => (int) $sid,
                'name' => (string) ($names[$i] ?? ''),
                'qty' => money_parse((string) ($qtys[$i] ?? 0)),
                'price' => money_parse((string) ($prices[$i] ?? 0)),
                'taxed' => !empty($taxed[$i]),
            ];
        }
        $paidRaw = post('paid');
        $done = stock_complete_sale([
            'customer' => post('customer'),
            'party_id' => (int) post('party_id'),
            'discount' => money_parse(post('discount')),
            'paid' => money_parse($paidRaw),
            'pay_all' => $paidRaw === '',
            'method' => post('method') ?: 'cash',
            'lines' => $lines,
        ]);
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not save that sale.');
        } else {
            $msg = 'Sale saved.';
            if (($done['balance'] ?? 0) > 0.009) {
                $msg .= ' Balance ' . money($done['balance']) . ' sits on Debtors.';
            }
            if (!empty($done['invoice_id'])) {
                $msg .= ' Invoice linked.';
            }
            $_SESSION['stock_last_print'] = (int) $done['print_id'];
            if (isset($_POST['do_print']) && (string) $_POST['do_print'] === '1' && (int) $done['print_id'] > 0) {
                redirect('document_view.php?id=' . (int) $done['print_id'] . '&print=1');
            }
            flash($msg);
            redirect('sale.php');
        }
    }
}

$catalog = stock_catalog_payload();
$customers = db_all("SELECT id, name FROM parties WHERE company_id = ? AND kind = 'customer' ORDER BY name LIMIT 250", 'i', [current_company_id()]);
$taxName = company_tax_name();
$lastPrint = stock_last_print_id();
$salesPage = stock_search_docs('invoice', stock_q(), stock_page_key('p'), 20);

layout_start('Sale', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('cart') ?>Sale</h1>
  </div>
  <?php if ($lastPrint): ?>
    <div class="actions page-actions">
      <a class="btn ghost" href="<?= h(url('document_download.php?id=' . $lastPrint)) ?>" data-pdf-download data-doc-id="<?= (int) $lastPrint ?>" data-pdf-name="document.pdf" data-sheet-url="<?= h(url('document_sheet.php?id=' . $lastPrint . '&autodownload=1')) ?>" title="Download PDF" aria-label="Download PDF"><?= icon('pdf', 16) ?> PDF last</a>
      <a class="btn ghost" href="<?= h(url('document_view.php?id=' . $lastPrint . '&print=1')) ?>"><?= icon('printer', 16) ?>Print last</a>
    </div>
  <?php endif; ?>
</div>
<?php render_stock_subnav('sale'); ?>

<?php render_sale_day_panel($dayError); ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php if (!$dayOpen): ?>
  <p class="flash flash-err">Open the day above before recording till sales.</p>
<?php endif; ?>

<form method="post" class="card pos-sale" data-pos-till data-pos-prefix="s" data-pos-mode="sale" data-pos-currency="<?= h(default_currency()) ?>">
  <?= csrf_field() ?>
  <div class="pad-form">
    <div class="form-grid">
      <div>
        <label for="customer">Customer</label>
        <input id="customer" name="customer" list="customer-list" value="Walk-in" autocomplete="off" <?= $dayOpen ? '' : 'disabled' ?>>
        <datalist id="customer-list">
          <?php foreach ($customers as $c): ?>
            <option value="<?= h($c['name']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label for="method">Paid how</label>
        <?php render_stock_payment_select('method', !$dayOpen, 'cash'); ?>
      </div>
    </div>
    <div class="pos-find">
      <label for="pos-q">Find product</label>
      <input id="pos-q" class="pos-q" autocomplete="off" placeholder="Type name or code" <?= $dayOpen ? '' : 'disabled' ?> data-pos-q>
      <div class="pos-suggest" hidden data-pos-suggest></div>
    </div>
    <div class="table-scroll">
      <table class="grid lines" id="pos-lines">
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty</th>
            <th class="right">Unit price</th>
            <th class="right">Total</th>
            <th class="center"><?= h($taxName) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody data-pos-body>
          <tr data-pos-empty>
            <td colspan="6" class="empty">Type a product above. It drops onto this list.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="pos-totals">
      <div>
        <label for="discount">Discount</label>
        <input id="discount" name="discount" inputmode="decimal" value="0" data-pos-discount <?= $dayOpen ? '' : 'disabled' ?>>
      </div>
      <div>
        <label for="paid">Paid now</label>
        <input id="paid" name="paid" inputmode="decimal" value="" placeholder="Leave blank to pay all" data-pos-paid <?= $dayOpen ? '' : 'disabled' ?>>
        <p class="hint">Unpaid balance sits on Debtors and links to the sale document.</p>
      </div>
      <div class="pos-sum">
        <span>Subtotal <strong data-pos-sub><?= h(money_behind(0)) ?></strong></span>
        <span><?= h($taxName) ?> <strong data-pos-tax><?= h(money_behind(0)) ?></strong></span>
        <span>Total <strong data-pos-grand><?= h(money_behind(0)) ?></strong></span>
        <span>Due <strong data-pos-due><?= h(money_behind(0)) ?></strong></span>
      </div>
    </div>
    <div class="actions sticky-save">
      <button class="btn pos-save" type="submit" name="do_print" value="1" <?= $dayOpen ? '' : 'disabled' ?>><?= icon('printer') ?>Save and print</button>
      <button class="btn" type="submit" <?= $dayOpen ? '' : 'disabled' ?>><?= icon('check') ?>Save sale</button>
      <?php if ($lastPrint): ?>
        <a class="btn ghost" href="<?= h(url('document_download.php?id=' . $lastPrint)) ?>" data-pdf-download data-doc-id="<?= (int) $lastPrint ?>" data-pdf-name="document.pdf" data-sheet-url="<?= h(url('document_sheet.php?id=' . $lastPrint . '&autodownload=1')) ?>" title="Download PDF" aria-label="Download PDF"><?= icon('pdf') ?> PDF last</a>
        <a class="btn ghost pos-print-last" href="<?= h(url('document_view.php?id=' . $lastPrint . '&print=1')) ?>"><?= icon('printer') ?>Print last</a>
      <?php endif; ?>
    </div>
  </div>
</form>
<span hidden data-pos-x><?= icon('x', 14) ?></span>
<script type="application/json" id="pos-catalog"><?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="pos-tax"><?= json_encode(['rate' => company_tax_rate(), 'name' => $taxName, 'default' => company_tax_default()]) ?></script>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2><?= icon('invoice', 16) ?>Sales documents</h2>
    <a class="btn ghost sm" href="<?= h(url('documents.php?kind=invoice')) ?>">All invoices</a>
  </div>
  <div class="pad-form">
    <?php stock_search_bar('sale.php', [], 'Search number or customer'); ?>
  </div>
  <?php render_stock_docs_table($salesPage, 'sale.php', 'p', 'No sales yet.'); ?>
</div>
<?php
layout_end('<script src="' . h(asset('js/stock-pos.js')) . '"></script>');
