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
if ($tab === 'purchases' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $qs = $_GET;
    unset($qs['tab']);
    redirect($qs ? ('purchases.php?' . http_build_query($qs)) : 'purchases.php');
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
        $done = stock_post_purchase_from_request();
        if (empty($done['ok'])) {
            flash((string) ($done['error'] ?? 'Could not save that purchase.'), 'err');
            redirect('purchases.php');
        }
    }
}

$items = stock_items(false);
$stats = stock_stats();
$low = stock_low_items();
$taxName = company_tax_name();
$q = stock_q();
$extraJs = '';
$postedAction = (string) ($_POST['action'] ?? '');
$showStockAdd = (bool) $edit || isset($_GET['add']) || ($error !== '' && $postedAction === 'save_item');
$showStockImport = isset($_GET['import']) || ($error !== '' && $postedAction === 'import');

layout_start('Stock', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('package') ?>Stock</h1>
    <p class="lede">Products and services. Counts and stock value cover goods only. Restock on Purchases. Sales and invoices can pick either.</p>
  </div>
  <div class="actions page-actions">
    <?php if ($tab === 'items'): ?>
      <a class="btn" href="<?= h(url('stock.php?tab=items&add=1#stock-add')) ?>"><?= icon('plus', 16) ?>Add item</a>
      <a class="btn ghost" href="<?= h(url('stock.php?tab=items&import=1#stock-import')) ?>"><?= icon('download', 16) ?>Import stock</a>
    <?php endif; ?>
    <?php if (stock_can_buy()): ?>
    <a class="btn ghost" href="<?= h(url('purchases.php')) ?>"><?= icon('expense', 16) ?>Purchases</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?>Sale</a>
  </div>
</div>
<?php render_stock_subnav($tab); ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if ($tab === 'items'):
    $filtered = stock_filter_items($items, $q);
    $page = stock_slice($filtered, stock_page_key('p'));
    ?>
<div class="stats stock-stats">
  <div class="card stat"><?= icon('package', 20) ?><span>Products</span><strong><?= (int) $stats['items'] ?></strong></div>
  <div class="card stat"><?= icon('bank', 20) ?><span>Stock at cost</span><strong><?= h(money($stats['cost'])) ?></strong></div>
  <div class="card stat"><?= icon('invoice', 20) ?><span>Stock at sell</span><strong><?= h(money($stats['sell'])) ?></strong></div>
  <div class="card stat"><?= icon('alert', 20) ?><span>Low stock</span><strong><?= (int) $stats['low'] ?></strong></div>
</div>

<div class="card stock-items-card" id="stock-items">
  <div class="card-head stock-items-head">
    <h2><?= icon('package', 16) ?>All items</h2>
    <div class="actions">
      <a class="btn sm" href="<?= h(url('stock.php?tab=items&add=1#stock-add')) ?>"><?= icon('plus', 14) ?>Add item</a>
      <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&import=1#stock-import')) ?>"><?= icon('download', 14) ?>Import stock</a>
    </div>
  </div>
  <div class="pad-form"><?php stock_search_bar('stock.php', ['tab' => 'items'], 'Search products and services'); ?></div>
  <?php if (!$page['rows']): ?>
    <p class="empty">No products match. Use Add item or Import stock.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid"<?= (int) $page['from'] > 1 ? ' style="counter-reset: grid-row ' . ((int) $page['from'] - 1) . '"' : '' ?>>
        <thead>
          <tr>
            <th>Item</th><th>Kind</th><th>Code</th><th>Unit</th><th class="right">On hand</th><th class="right">Buy</th><th class="right">Sell</th><th class="right">Reorder</th><th><?= h($taxName) ?></th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($page['rows'] as $row):
              $svc = stock_item_is_service($row);
              $isLow = !$svc && (float) $row['reorder_level'] > 0 && (float) $row['qty_on_hand'] <= (float) $row['reorder_level']; ?>
            <tr>
              <td><?= h($row['name']) ?><?= empty($row['active']) ? ' <span class="pill">Hidden</span>' : '' ?><?= $isLow ? ' <span class="pill">Low</span>' : '' ?></td>
              <td><?= $svc ? 'Service' : 'Product' ?></td>
              <td class="mono"><?= h($row['sku']) ?></td>
              <td><?= h($row['unit']) ?></td>
              <td class="right mono"><?= $svc ? '-' : h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
              <td class="right mono"><?= $svc ? '-' : h(money((float) $row['buy_price'])) ?></td>
              <td class="right mono"><?= h(money((float) $row['sell_price'])) ?></td>
              <td class="right mono"><?= $svc ? '-' : h(stock_qty_label((float) $row['reorder_level'])) ?></td>
              <td><?= !empty($row['taxed']) ? 'Y' : 'N' ?></td>
              <td class="row-actions">
                <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'] . '#stock-add')) ?>"><?= icon('pencil', 14) ?>Edit</a>
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

<?php if ($low): ?>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('alert', 16) ?>Low stock</h2></div>
  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>Item</th><th class="right">On hand</th><th class="right">Reorder at</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($low as $row): ?>
          <tr>
            <td><?= h($row['name']) ?></td>
            <td class="right mono"><?= h(stock_qty_label((float) $row['qty_on_hand'])) ?></td>
            <td class="right mono"><?= h(stock_qty_label((float) $row['reorder_level'])) ?></td>
            <td class="row-actions">
              <a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'] . '#stock-add')) ?>"><?= icon('pencil', 14) ?>Edit</a>
              <?php stock_delete_button((int) $row['id']); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($showStockAdd): ?>
<div class="card" style="margin-top:16px" id="stock-add">
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
      <a class="btn ghost" href="<?= h(url('stock.php?tab=items')) ?>">Cancel</a>
      <?php if ($edit && user_can_delete_stock()): ?>
        <button class="btn danger" type="submit" name="action" value="delete_item" formnovalidate onclick="return confirm('Delete this item? Sheets already issued keep the name. This cannot be undone.');"><?= icon('trash') ?>Delete</button>
      <?php endif; ?>
    </div>
  </form>
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
<?php endif; ?>

<?php if ($showStockImport): ?>
<div class="card" style="margin-top:16px" id="stock-import">
  <div class="card-head"><h2><?= icon('download', 16) ?>Import stock</h2></div>
  <div class="pad-form">
    <p class="lede">Download the sheet, fill products and services, upload it. Keep the header row. Opening qty is not the buying price. Type is <code>product</code> or <code>service</code>.</p>
    <p><a class="btn ghost" href="<?= h(url('stock.php?template=1')) ?>"><?= icon('download', 16) ?>Download Excel template</a></p>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <label for="file">Upload filled sheet</label>
      <input id="file" name="file" type="file" accept=".xlsx,.csv,.txt" required>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('plus') ?>Upload items</button>
        <a class="btn ghost" href="<?= h(url('stock.php?tab=items')) ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'counts'):
    $activeItems = array_values(array_filter($items, static fn ($r) => !empty($r['active']) && !stock_item_is_service($r)));
    $filtered = stock_filter_items($activeItems, $q);
    $page = stock_slice($filtered, stock_page_key('p'));
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
        <table class="grid"<?= (int) $page['from'] > 1 ? ' style="counter-reset: grid-row ' . ((int) $page['from'] - 1) . '"' : '' ?>>
          <thead><tr><th>Item</th><th class="right">System</th><th class="right">Counted</th></tr></thead>
          <tbody>
            <?php foreach ($page['rows'] as $row): ?>
              <tr>
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
      <table class="grid"<?= (int) $countPage['from'] > 1 ? ' style="counter-reset: grid-row ' . ((int) $countPage['from'] - 1) . '"' : '' ?>>
        <thead><tr><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($countPage['rows'] as $c): ?>
            <tr>
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

<?php endif; ?>

<?php layout_end($extraJs);
