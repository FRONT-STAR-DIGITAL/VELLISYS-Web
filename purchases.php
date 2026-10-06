<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_stock();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'purchase') {
        $done = stock_post_purchase_from_request();
        if (empty($done['ok'])) {
            $error = (string) ($done['error'] ?? 'Could not save that purchase.');
        }
    }
}

$taxName = company_tax_name();
$catalog = stock_catalog_payload();
$supHome = function_exists('party_write_branch_id') ? party_write_branch_id() : 0;
[$supSql, $supTypes, $supArgs] = function_exists('party_branch_where')
    ? party_branch_where('', $supHome, true)
    : ['', '', []];
$suppliers = db_all(
    "SELECT id, name FROM parties WHERE company_id = ? AND kind = 'supplier' AND (status IS NULL OR status = 'active')" . $supSql . ' ORDER BY name LIMIT 250',
    'i' . $supTypes,
    array_merge([current_company_id()], $supArgs)
);
$q = stock_q();
$buyPage = stock_search_docs('expense', $q, stock_page_key('p'), 20, null, 'Stock');

layout_start('Purchases', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('expense') ?>Purchases</h1>
    <p class="lede">Restock goods for <?= h(function_exists('company_branch_label') ? company_branch_label(stock_write_branch_id()) : 'the business') ?>. Paid bills clear now; unpaid balances sit on Creditors, not on day profit.<?= function_exists('desk_branch_lede') ? h(desk_branch_lede('purchases')) : '' ?></p>
  </div>
  <div class="actions page-actions">
    <a class="btn ghost" href="<?= h(url('stock.php?tab=items')) ?>"><?= icon('package', 16) ?>Stock</a>
    <a class="btn ghost" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?>Sale</a>
  </div>
</div>
<?php render_stock_subnav('purchases'); ?>
<?php if (function_exists('render_desk_branch_chips')) { render_desk_branch_chips('purchases.php'); } ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Restock</h2></div>
  <form method="post" class="pad-form pos-sale" data-pos-till data-pos-prefix="p" data-pos-mode="buy" data-pos-currency="<?= h(default_currency()) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <div class="form-grid">
      <div>
        <label for="supplier">Supplier name</label>
        <input id="supplier" name="supplier" list="supplier-list" placeholder="Type or pick" autocomplete="off" required>
        <datalist id="supplier-list">
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= h($s['name']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label for="method">Paid how</label>
        <?php render_stock_payment_select('method', false, 'cash'); ?>
      </div>
    </div>
    <div class="pos-find">
      <label for="pos-q">Find product</label>
      <input id="pos-q" class="pos-q" autocomplete="off" placeholder="Type name or code. New names can be added." data-pos-q>
      <div class="pos-suggest" hidden data-pos-suggest></div>
    </div>
    <div class="table-scroll">
      <table class="grid lines">
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
            <td colspan="6" class="empty">Type a product. If it is new, tap Add new.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <script type="application/json" id="pos-catalog"><?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/json" id="pos-tax"><?= json_encode(['rate' => company_tax_rate(), 'default' => company_tax_default()]) ?></script>
    <div class="pos-totals">
      <div>
        <label for="paid">Amount paid now</label>
        <input id="paid" name="paid" inputmode="decimal" data-pos-paid data-money-commas placeholder="0 = full credit" autocomplete="off">
        <p class="hint">Type 0 for credit. Unpaid stays on the stock bill — it does not reduce day profit.</p>
      </div>
      <div class="pos-sum">
        <span>Subtotal <strong data-pos-sub><?= h(money_behind(0)) ?></strong></span>
        <span><?= h($taxName) ?> <strong data-pos-tax><?= h(money_behind(0)) ?></strong></span>
        <span>Total <strong data-pos-grand><?= h(money_behind(0)) ?></strong></span>
        <span>Due <strong data-pos-due><?= h(money_behind(0)) ?></strong></span>
      </div>
    </div>
    <div class="actions">
      <button class="btn pos-save" type="submit"><?= icon('check') ?>Save purchase</button>
    </div>
  </form>
  <span hidden data-pos-x><?= icon('x', 14) ?></span>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Purchase bills</h2></div>
  <div class="pad-form"><?php stock_search_bar('purchases.php', function_exists('desk_branch_keep') ? desk_branch_keep() : [], 'Search bill or supplier'); ?></div>
  <?php render_stock_docs_table($buyPage, 'purchases.php', 'p', 'No stock purchases yet.'); ?>
</div>
<?php
layout_end('<script src="' . h(asset('js/stock-pos.js')) . '"></script>');
