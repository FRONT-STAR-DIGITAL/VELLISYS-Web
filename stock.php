<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_stock();

$tab = (string) ($_GET['tab'] ?? 'items');
if ($tab === 'day') {
    $qs = $_GET;
    unset($qs['tab']);
    redirect($qs ? ('dashboard.php?' . http_build_query($qs)) : 'dashboard.php');
}
if (!isset(stock_tabs()[$tab])) {
    $tab = 'items';
}
if ($tab === 'purchases' && !stock_can_buy()) {
    $tab = 'items';
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? stock_item($editId) : null;
$error = '';

if (isset($_GET['template'])) {
    stock_send_template();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save_item') {
        $id = (int) post('item_id') ?: null;
        $saved = stock_save_item([
            'name' => post('name'),
            'sku' => post('sku'),
            'description' => post('description'),
            'unit' => post('unit') ?: 'pc',
            'buy_price' => money_parse(post('buy_price')),
            'sell_price' => money_parse(post('sell_price')),
            'reorder_level' => money_parse(post('reorder_level')),
            'qty_on_hand' => money_parse(post('qty_on_hand')),
            'is_service' => post('item_kind') === 'service' ? 1 : 0,
            'taxed' => post('taxed') === '1' ? 1 : 0,
            'active' => post('active') === '0' ? 0 : 1,
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save that item.');
        } else {
            $kindLabel = post('item_kind') === 'service' ? 'Service' : 'Product';
            flash($id ? $kindLabel . ' updated.' : $kindLabel . ' added.');
            redirect('stock.php?tab=items');
        }
        $tab = 'items';
    } elseif ($action === 'delete_item') {
        $id = (int) post('item_id');
        $gone = stock_delete_item($id);
        if (empty($gone['ok'])) {
            $error = (string) ($gone['error'] ?? 'Could not delete that product.');
        } else {
            flash('Deleted ' . $gone['name'] . '.');
            redirect('stock.php?tab=items');
        }
        $tab = 'items';
    } elseif ($action === 'import') {
        $file = $_FILES['file'] ?? [];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'Choose the Excel or CSV file to upload.';
            $tab = 'items';
        } else {
            $rows = stock_parse_upload($file['tmp_name'], (string) ($file['name'] ?? ''));
            if (!$rows) {
                $error = 'Could not read that file. Use the template.';
                $tab = 'items';
            } else {
                $res = stock_import_rows($rows);
                flash('Imported: ' . $res['added'] . ' new, ' . $res['updated'] . ' updated, ' . $res['skipped'] . ' skipped.');
                redirect('stock.php?tab=items');
            }
        }
    } elseif ($action === 'count') {
        $posted = $_POST['count'] ?? [];
        $counted = [];
        if (is_array($posted)) {
            foreach ($posted as $id => $qty) {
                $counted[(int) $id] = money_parse((string) $qty);
            }
        }
        if (!$counted) {
            $error = 'Enter counted quantities.';
            $tab = 'counts';
        } else {
            stock_post_count($counted);
            flash('Stock count saved. Quantities now match what you counted.');
            redirect('stock.php?tab=counts');
        }
    } elseif ($action === 'purchase') {
        if (!stock_can_buy()) {
            $error = 'Your login cannot record purchases.';
            $tab = 'items';
        } else {
            stock_require_open_day();
            $ids = $_POST['p_item'] ?? [];
            $names = $_POST['p_name'] ?? [];
            $qtys = $_POST['p_qty'] ?? [];
            $prices = $_POST['p_price'] ?? [];
            $taxed = $_POST['p_taxed'] ?? [];
            $lines = [];
            foreach (array_keys((array) $ids + (array) $names) as $i) {
                $lines[] = [
                    'stock_item_id' => (int) ($ids[$i] ?? 0),
                    'name' => (string) ($names[$i] ?? ''),
                    'qty' => money_parse((string) ($qtys[$i] ?? 0)),
                    'price' => money_parse((string) ($prices[$i] ?? 0)),
                    'taxed' => !empty($taxed[$i]),
                ];
            }
            $paidRaw = post('paid');
            $done = stock_complete_purchase([
                'supplier' => post('supplier'),
                'party_id' => (int) post('party_id'),
                'paid' => $paidRaw === '' ? 0 : money_parse($paidRaw),
                'method' => post('method') ?: 'cash',
                'lines' => $lines,
            ]);
            if (empty($done['ok'])) {
                $error = (string) ($done['error'] ?? 'Could not save that purchase.');
                $tab = 'purchases';
            } else {
                $msg = 'Purchase saved as an expense.';
                if (($done['balance'] ?? 0) > 0.009) {
                    $msg .= ' Balance ' . money($done['balance']) . ' sits on Creditors.';
                }
                flash($msg);
                redirect('stock.php?tab=purchases');
            }
        }
    }
}

$items = stock_items(false);
$stats = stock_stats();
$low = stock_low_items();
$todayDay = stock_today();
$dayOpen = stock_day_is_open();
$dayLive = stock_day_totals(today());
$taxName = company_tax_name();
$catalog = stock_catalog_payload();
$suppliers = db_all("SELECT id, name FROM parties WHERE company_id = ? AND kind = 'supplier' ORDER BY name LIMIT 250", 'i', [current_company_id()]);
$q = stock_q();
$extraJs = '';

layout_start('Stock', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('package') ?>Stock</h1>
    <p class="lede">Products and services. Counts, purchases and stock value cover goods only. Sales and invoices can pick either.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?>Sale</a>
  </div>
</div>
<?php render_stock_subnav($tab); ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if (!$dayOpen): ?>
  <p class="flash" style="margin:0 0 16px"><?= icon('clock', 16) ?>Open the day before selling or buying. <a href="<?= h(url(desk_day_url())) ?>">Open day</a></p>
<?php endif; ?>

<?php if ($tab === 'items'):
    $filtered = stock_filter_items($items, $q);
    $page = stock_slice($filtered, stock_page_key('p'));
    $n = (int) $page['from'];
    ?>
<div class="stats">
  <div class="card stat"><?= icon('package', 20) ?><span>Products</span><strong><?= (int) $stats['items'] ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Stock at cost</span><strong><?= h(money($stats['cost'])) ?></strong></div>
  <div class="card stat"><?= icon('invoice', 20) ?><span>Stock at sell</span><strong><?= h(money($stats['sell'])) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Low stock</span><strong><?= (int) $stats['low'] ?></strong></div>
</div>
<?php if ($low): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h2><?= icon('alert', 16) ?>Low stock</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>#</th><th>Item</th><th class="right">On hand</th><th class="right">Reorder at</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $ln = 1; foreach ($low as $row): ?>
          <tr>
            <td class="mono"><?= $ln++ ?></td>
            <td><?= h($row['name']) ?></td>
            <td class="right mono"><?= h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
            <td class="right mono"><?= h(stock_qty_label((float) $row['reorder_level'])) ?></td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'])) ?>"><?= icon('pencil', 14) ?>Edit</a>
              <?php stock_delete_button((int) $row['id']); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon($edit ? 'pencil' : 'plus', 16) ?><?= $edit ? (stock_item_is_service($edit) ? 'Edit service' : 'Edit product') : 'Add item' ?></h2></div>
    <form method="post" class="pad-form" data-stock-item-form>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="item_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div class="form-grid">
        <div style="grid-column:1 / -1">
          <span class="label">Kind</span>
          <div class="radio-row" style="display:flex;gap:16px;flex-wrap:wrap;margin:6px 0 4px">
            <label class="check"><input type="radio" name="item_kind" value="product" <?= !$edit || !stock_item_is_service($edit) ? 'checked' : '' ?>> Product (stock)</label>
            <label class="check"><input type="radio" name="item_kind" value="service" <?= $edit && stock_item_is_service($edit) ? 'checked' : '' ?>> Service</label>
          </div>
          <p class="hint">Services have a selling price only. They do not use opening quantity, buying price or stock counts.</p>
        </div>
        <div>
          <label for="name">Item name</label>
          <input id="name" name="name" required value="<?= h((string) ($edit['name'] ?? '')) ?>" placeholder="Rice 25kg">
        </div>
        <div>
          <label for="sku">Code (SKU)</label>
          <input id="sku" name="sku" value="<?= h((string) ($edit['sku'] ?? '')) ?>" placeholder="RICE25">
        </div>
        <div style="grid-column:1 / -1">
          <label for="description">Description</label>
          <input id="description" name="description" value="<?= h((string) ($edit['description'] ?? '')) ?>">
        </div>
        <div>
          <label for="unit">Unit</label>
          <input id="unit" name="unit" value="<?= h((string) ($edit['unit'] ?? 'pc')) ?>">
        </div>
        <div data-stock-goods>
          <label for="buy_price">Buying price</label>
          <input id="buy_price" name="buy_price" inputmode="decimal" value="<?= h($edit && !stock_item_is_service($edit) ? (string) $edit['buy_price'] : '') ?>">
        </div>
        <div>
          <label for="sell_price">Selling price</label>
          <input id="sell_price" name="sell_price" inputmode="decimal" value="<?= h($edit ? (string) $edit['sell_price'] : '') ?>">
        </div>
        <div data-stock-goods>
          <label for="reorder_level">Reorder level</label>
          <input id="reorder_level" name="reorder_level" inputmode="decimal" value="<?= h($edit && !stock_item_is_service($edit) ? (string) $edit['reorder_level'] : '') ?>">
        </div>
        <?php if (!$edit): ?>
        <div data-stock-goods>
          <label for="qty_on_hand">Opening quantity</label>
          <input id="qty_on_hand" name="qty_on_hand" inputmode="decimal" value="0">
        </div>
        <?php endif; ?>
      </div>
      <label class="check"><input type="checkbox" name="taxed" value="1" <?= $edit ? (!empty($edit['taxed']) ? 'checked' : '') : (company_tax_default() ? 'checked' : '') ?>> <?= h($taxName) ?> on this item</label>
      <?php if ($edit): ?>
        <label class="check"><input type="checkbox" name="active" value="0" <?= empty($edit['active']) ? 'checked' : '' ?>> Hide from sales</label>
      <?php endif; ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save item</button>
        <?php if ($edit): ?><a class="btn ghost" href="<?= h(url('stock.php?tab=items')) ?>">Cancel</a><?php endif; ?>
        <?php if ($edit && user_can_delete_stock()): ?>
          <button class="btn danger" type="submit" name="action" value="delete_item" formnovalidate onclick="return confirm('Delete this item? Sheets already issued keep the name. This cannot be undone.');"><?= icon('trash') ?>Delete</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('download', 16) ?>Excel in / out</h2></div>
    <div class="pad-form">
      <p class="lede">Download the sheet, fill products and services, upload it. Type is <code>product</code> or <code>service</code>.</p>
      <p><a class="btn ghost" href="<?= h(url('stock.php?template=1')) ?>"><?= icon('download', 16) ?>Download Excel template</a></p>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <label for="file">Upload filled sheet</label>
        <input id="file" name="file" type="file" accept=".xlsx,.csv,.txt" required>
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit"><?= icon('plus') ?>Upload items</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('package', 16) ?>All items</h2></div>
  <div class="pad-form"><?php stock_search_bar('stock.php', ['tab' => 'items'], 'Search products and services'); ?></div>
  <?php if (!$page['rows']): ?>
    <p class="empty">No products match. Add one, or upload the Excel sheet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>#</th><th>Item</th><th>Kind</th><th>Code</th><th>Unit</th><th class="right">On hand</th><th class="right">Buy</th><th class="right">Sell</th><th class="right">Reorder</th><th><?= h($taxName) ?></th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($page['rows'] as $row):
              $svc = stock_item_is_service($row);
              $isLow = !$svc && (float) $row['reorder_level'] > 0 && (float) $row['qty_on_hand'] <= (float) $row['reorder_level']; ?>
            <tr>
              <td class="mono"><?= $n++ ?></td>
              <td><?= h($row['name']) ?><?= empty($row['active']) ? ' <span class="pill">Hidden</span>' : '' ?><?= $isLow ? ' <span class="pill">Low</span>' : '' ?></td>
              <td><?= $svc ? 'Service' : 'Product' ?></td>
              <td class="mono"><?= h($row['sku']) ?></td>
              <td><?= h($row['unit']) ?></td>
              <td class="right mono"><?= $svc ? '—' : h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
              <td class="right mono"><?= $svc ? '—' : h(money((float) $row['buy_price'])) ?></td>
              <td class="right mono"><?= h(money((float) $row['sell_price'])) ?></td>
              <td class="right mono"><?= $svc ? '—' : h(stock_qty_label((float) $row['reorder_level'])) ?></td>
              <td><?= !empty($row['taxed']) ? 'Y' : 'N' ?></td>
              <td class="row-actions">
                <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'])) ?>"><?= icon('pencil', 14) ?>Edit</a>
                <?php stock_delete_button((int) $row['id']); ?>
                <a class="btn ghost sm" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 14) ?>Sell</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php stock_pager('stock.php?tab=items', (int) $page['page'], (int) $page['pages'], 'p'); ?>
  <?php endif; ?>
</div>
<script>
(function () {
  var form = document.querySelector('[data-stock-item-form]');
  if (!form) return;
  function sync() {
    var svc = form.querySelector('input[name="item_kind"][value="service"]');
    var on = !!(svc && svc.checked);
    form.querySelectorAll('[data-stock-goods]').forEach(function (el) { el.hidden = on; });
  }
  form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'item_kind') sync();
  });
  sync();
})();
</script>

<?php elseif ($tab === 'counts'):
    $activeItems = array_values(array_filter($items, static fn ($r) => !empty($r['active']) && !stock_item_is_service($r)));
    $filtered = stock_filter_items($activeItems, $q);
    $page = stock_slice($filtered, stock_page_key('p'));
    $n = (int) $page['from'];
    $countPage = stock_slice(stock_recent_counts(40), stock_page_key('cp'));
    ?>
<div class="card">
  <div class="card-head"><h2><?= icon('hash', 16) ?>Count stock</h2></div>
  <?php if (!$activeItems): ?>
    <p class="empty">Add products first. Services are not counted on the shelf.</p>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="count">
      <div class="pad-form"><?php stock_search_bar('stock.php', ['tab' => 'counts'], 'Search products'); ?></div>
      <p class="lede" style="padding:0 18px 8px">Walk the shelf. Type what you see on this page. Saving sets those products to the counted number.</p>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>#</th><th>Item</th><th class="right">System</th><th class="right">Counted</th></tr></thead>
          <tbody>
            <?php foreach ($page['rows'] as $row): ?>
              <tr>
                <td class="mono"><?= $n++ ?></td>
                <td><?= h($row['name']) ?></td>
                <td class="right mono"><?= h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
                <td class="line-qty"><input name="count[<?= (int) $row['id'] ?>]" inputmode="decimal" value="<?= h((string) $row['qty_on_hand']) ?>"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php stock_pager('stock.php?tab=counts', (int) $page['page'], (int) $page['pages'], 'p'); ?>
      <div class="actions" style="padding:12px 18px 18px">
        <button class="btn" type="submit"><?= icon('check') ?>Save this page</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('clock', 16) ?>Count history</h2></div>
  <?php if (!$countPage['rows']): ?>
    <p class="empty">No counts posted yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>#</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php $cn = (int) $countPage['from']; foreach ($countPage['rows'] as $c): ?>
            <tr>
              <td class="mono"><?= $cn++ ?></td>
              <td><?= h(format_date($c['counted_on'])) ?></td>
              <td><?= h((string) $c['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php stock_pager('stock.php?tab=counts', (int) $countPage['page'], (int) $countPage['pages'], 'cp'); ?>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'purchases'):
    $buyPage = stock_search_docs('expense', $q, stock_page_key('p'), 20, null, 'Stock');
    ?>
<?php if (!$dayOpen): ?>
  <p class="flash flash-err">Open the day on the Day tab before buying stock.</p>
<?php endif; ?>
<div class="card">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Buy stock</h2></div>
  <form method="post" class="pad-form pos-sale" data-pos-till data-pos-prefix="p" data-pos-mode="buy" data-pos-currency="<?= h(default_currency()) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <div class="form-grid">
      <div>
        <label for="supplier">Supplier name</label>
        <input id="supplier" name="supplier" list="supplier-list" placeholder="Type or pick" autocomplete="off" <?= $dayOpen ? 'required' : 'disabled' ?>>
        <datalist id="supplier-list">
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= h($s['name']) ?>"></option>
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
      <input id="pos-q" class="pos-q" autocomplete="off" placeholder="Type name or code. New names can be added." <?= $dayOpen ? '' : 'disabled' ?> data-pos-q>
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
        <input id="paid" name="paid" inputmode="decimal" data-pos-paid placeholder="0 = full credit" <?= $dayOpen ? '' : 'disabled' ?>>
        <p class="hint">Pay half, or type 0 if you will pay later. Unpaid sits on Creditors.</p>
      </div>
      <div class="pos-sum">
        <span>Subtotal <strong data-pos-sub><?= h(money_behind(0)) ?></strong></span>
        <span><?= h($taxName) ?> <strong data-pos-tax><?= h(money_behind(0)) ?></strong></span>
        <span>Total <strong data-pos-grand><?= h(money_behind(0)) ?></strong></span>
        <span>Due <strong data-pos-due><?= h(money_behind(0)) ?></strong></span>
      </div>
    </div>
    <div class="actions">
      <button class="btn pos-save" type="submit" <?= $dayOpen ? '' : 'disabled' ?>><?= icon('check') ?>Save purchase</button>
    </div>
  </form>
  <span hidden data-pos-x><?= icon('x', 14) ?></span>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Purchases</h2></div>
  <div class="pad-form"><?php stock_search_bar('stock.php', ['tab' => 'purchases'], 'Search bill or supplier'); ?></div>
  <?php render_stock_docs_table($buyPage, 'stock.php?tab=purchases', 'p', 'No stock purchases yet.'); ?>
</div>
<?php $extraJs = '<script src="' . h(asset('js/stock-pos.js')) . '"></script>'; ?>

<?php endif; ?>

<?php layout_end($extraJs);
