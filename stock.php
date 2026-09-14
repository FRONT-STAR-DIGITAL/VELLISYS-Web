<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_stock();

$tab = (string) ($_GET['tab'] ?? 'items');
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
            'taxed' => post('taxed') === '1' ? 1 : 0,
            'active' => post('active') === '0' ? 0 : 1,
        ], $id);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not save that product.');
        } else {
            flash($id ? 'Product updated.' : 'Product added.');
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
            $qtys = $_POST['p_qty'] ?? [];
            $prices = $_POST['p_price'] ?? [];
            $taxed = $_POST['p_taxed'] ?? [];
            $lines = [];
            foreach ((array) $ids as $i => $sid) {
                $lines[] = [
                    'stock_item_id' => (int) $sid,
                    'qty' => money_parse((string) ($qtys[$i] ?? 0)),
                    'price' => money_parse((string) ($prices[$i] ?? 0)),
                    'taxed' => !empty($taxed[$i]),
                ];
            }
            $done = stock_complete_purchase([
                'supplier' => post('supplier'),
                'party_id' => (int) post('party_id'),
                'paid' => money_parse(post('paid')),
                'method' => post('method') ?: 'Cash',
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
    } elseif ($action === 'open_day') {
        $opened = stock_day_open(money_parse(post('open_cash')));
        if (empty($opened['ok'])) {
            $error = (string) ($opened['error'] ?? 'Could not open the day.');
            $tab = 'day';
        } else {
            flash('Day opened. You can sell now.');
            redirect('sale.php');
        }
    } elseif ($action === 'close_day') {
        $closed = stock_day_close(money_parse(post('close_cash')), post('notes'));
        if (empty($closed['ok'])) {
            $error = (string) ($closed['error'] ?? 'Could not close the day.');
            $tab = 'day';
        } else {
            $t = $closed['totals'];
            flash('Day closed. Income ' . money($t['income']) . ' · Spend ' . money($t['expense']) . ' · Net ' . money($t['profit']) . ' · Tax ' . money($t['tax']));
            redirect('stock.php?tab=day');
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
$suppliers = db_all("SELECT id, name FROM parties WHERE company_id = ? AND kind = 'supplier' ORDER BY name", 'i', [current_company_id()]);

layout_start('Stock', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('package') ?>Stock</h1>
    <p class="lede">Products, counts and purchases. Sales are on Sale. Quotes and invoices pick from this list.</p>
  </div>
  <div class="actions page-actions">
    <a class="btn" href="<?= h(url('sale.php')) ?>"><?= icon('cart', 16) ?>Sale</a>
  </div>
</div>
<?php render_stock_subnav($tab); ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<?php if (!$dayOpen && $tab !== 'day'): ?>
  <p class="flash" style="margin:0 0 16px"><?= icon('clock', 16) ?>Open the day before selling or buying. <a href="<?= h(url('stock.php?tab=day')) ?>">Open day</a></p>
<?php endif; ?>

<?php if ($tab === 'items'): ?>
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
      <thead><tr><th>Item</th><th class="right">On hand</th><th class="right">Reorder at</th></tr></thead>
      <tbody>
        <?php foreach ($low as $row): ?>
          <tr>
            <td><a href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'])) ?>"><?= h($row['name']) ?></a></td>
            <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['qty_on_hand'], 2, '.', ''), '0'), '.')) ?></td>
            <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['reorder_level'], 2, '.', ''), '0'), '.')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon($edit ? 'pencil' : 'plus', 16) ?><?= $edit ? 'Edit product' : 'Add product' ?></h2></div>
    <form method="post" class="pad-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="item_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div class="form-grid">
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
        <div>
          <label for="buy_price">Buying price</label>
          <input id="buy_price" name="buy_price" inputmode="decimal" value="<?= h($edit ? (string) $edit['buy_price'] : '') ?>">
        </div>
        <div>
          <label for="sell_price">Selling price</label>
          <input id="sell_price" name="sell_price" inputmode="decimal" value="<?= h($edit ? (string) $edit['sell_price'] : '') ?>">
        </div>
        <div>
          <label for="reorder_level">Reorder level</label>
          <input id="reorder_level" name="reorder_level" inputmode="decimal" value="<?= h($edit ? (string) $edit['reorder_level'] : '') ?>">
        </div>
        <?php if (!$edit): ?>
        <div>
          <label for="qty_on_hand">Opening quantity</label>
          <input id="qty_on_hand" name="qty_on_hand" inputmode="decimal" value="0">
        </div>
        <?php endif; ?>
      </div>
      <label class="check"><input type="checkbox" name="taxed" value="1" <?= !$edit || !empty($edit['taxed']) ? 'checked' : '' ?>> <?= h($taxName) ?> on this item</label>
      <?php if ($edit): ?>
        <label class="check"><input type="checkbox" name="active" value="0" <?= empty($edit['active']) ? 'checked' : '' ?>> Hide from sales</label>
      <?php endif; ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save product</button>
        <?php if ($edit): ?><a class="btn ghost" href="<?= h(url('stock.php?tab=items')) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('download', 16) ?>Excel in / out</h2></div>
    <div class="pad-form">
      <p class="lede">Download the sheet, fill products, upload it.</p>
      <p><a class="btn ghost" href="<?= h(url('stock.php?template=1')) ?>"><?= icon('download', 16) ?>Download Excel template</a></p>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <label for="file">Upload filled sheet</label>
        <input id="file" name="file" type="file" accept=".xlsx,.csv,.txt" required>
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit"><?= icon('plus') ?>Upload products</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h2><?= icon('package', 16) ?>All products</h2></div>
  <?php if (!$items): ?>
    <p class="empty">No products yet. Add one, or upload the Excel sheet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead>
          <tr>
            <th>Item</th><th>Code</th><th>Unit</th><th class="right">On hand</th><th class="right">Buy</th><th class="right">Sell</th><th class="right">Reorder</th><th><?= h($taxName) ?></th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $row):
              $isLow = (float) $row['reorder_level'] > 0 && (float) $row['qty_on_hand'] <= (float) $row['reorder_level']; ?>
            <tr>
              <td><?= h($row['name']) ?><?= empty($row['active']) ? ' <span class="pill">Hidden</span>' : '' ?><?= $isLow ? ' <span class="pill">Low</span>' : '' ?></td>
              <td class="mono"><?= h($row['sku']) ?></td>
              <td><?= h($row['unit']) ?></td>
              <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['qty_on_hand'], 2, '.', ''), '0'), '.')) ?></td>
              <td class="right mono"><?= h(money((float) $row['buy_price'])) ?></td>
              <td class="right mono"><?= h(money((float) $row['sell_price'])) ?></td>
              <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['reorder_level'], 2, '.', ''), '0'), '.')) ?></td>
              <td><?= !empty($row['taxed']) ? 'Y' : 'N' ?></td>
              <td><a class="btn ghost sm" href="<?= h(url('stock.php?tab=items&edit=' . (int) $row['id'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php elseif ($tab === 'counts'): ?>
<div class="card">
  <div class="card-head"><h2><?= icon('hash', 16) ?>Count stock</h2></div>
  <?php if (!$items): ?>
    <p class="empty">Add products first.</p>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="count">
      <p class="lede" style="padding:0 18px 8px">Walk the shelf. Type what you see. Saving sets on-hand to that number.</p>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>Item</th><th class="right">System</th><th class="right">Counted</th></tr></thead>
          <tbody>
            <?php foreach ($items as $row): if (empty($row['active'])) { continue; } ?>
              <tr>
                <td><?= h($row['name']) ?></td>
                <td class="right mono"><?= h(rtrim(rtrim(number_format((float) $row['qty_on_hand'], 2, '.', ''), '0'), '.')) ?></td>
                <td class="line-qty"><input name="count[<?= (int) $row['id'] ?>]" inputmode="decimal" value="<?= h((string) $row['qty_on_hand']) ?>"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="actions" style="padding:12px 18px 18px">
        <button class="btn" type="submit"><?= icon('check') ?>Save count</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php elseif ($tab === 'purchases'): ?>
<?php if (!$dayOpen): ?>
  <p class="flash flash-err">Open the day on the Day tab before buying stock.</p>
<?php endif; ?>
<div class="card">
  <div class="card-head"><h2><?= icon('expense', 16) ?>Buy stock</h2></div>
  <form method="post" class="pad-form" data-stock-buy>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <div class="form-grid">
      <div>
        <label for="supplier">Supplier name</label>
        <input id="supplier" name="supplier" list="supplier-list" placeholder="Type or pick" <?= $dayOpen ? 'required' : 'disabled' ?>>
        <datalist id="supplier-list">
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= h($s['name']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label for="method">Paid how</label>
        <input id="method" name="method" value="Cash" <?= $dayOpen ? '' : 'disabled' ?>>
      </div>
    </div>
    <div class="table-scroll">
      <table class="grid lines" id="buy-lines">
        <thead><tr><th>Item</th><th>Qty</th><th class="right">Unit price</th><th class="right">Total</th><th class="center"><?= h($taxName) ?></th></tr></thead>
        <tbody>
          <?php for ($i = 0; $i < 4; $i++): ?>
            <tr>
              <td>
                <input type="hidden" name="p_item[<?= $i ?>]" value="" data-buy-id>
                <input name="p_name[<?= $i ?>]" list="stock-list" data-buy-item placeholder="Type product">
              </td>
              <td><input name="p_qty[<?= $i ?>]" inputmode="decimal" data-buy-qty></td>
              <td><input name="p_price[<?= $i ?>]" inputmode="decimal" data-buy-price></td>
              <td class="right mono" data-buy-total>0</td>
              <td class="center"><label class="vat-yn"><input type="checkbox" name="p_taxed[<?= $i ?>]" value="1" data-buy-tax><span>Y</span></label></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <datalist id="stock-list">
      <?php foreach ($catalog as $p): ?>
        <option value="<?= h($p['name']) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <script type="application/json" id="stock-buy-catalog"><?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?></script>
    <div class="form-grid" style="margin-top:12px">
      <div>
        <label for="paid">Amount paid now</label>
        <input id="paid" name="paid" inputmode="decimal" placeholder="0 = full credit" <?= $dayOpen ? '' : 'disabled' ?>>
        <p class="hint">Pay half, or leave 0 if you will pay later. Unpaid sits on Creditors.</p>
      </div>
    </div>
    <div class="actions">
      <button class="btn" type="submit" <?= $dayOpen ? '' : 'disabled' ?>><?= icon('check') ?>Save purchase</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="stats">
  <div class="card stat"><?= icon('clock', 20) ?><span>Today</span><strong><?= $dayOpen ? 'Open' : ($todayDay ? 'Closed' : 'Not opened') ?></strong></div>
  <div class="card stat"><?= icon('invoice', 20) ?><span>Income</span><strong><?= h(money($dayLive['income'])) ?></strong></div>
  <div class="card stat"><?= icon('expense', 20) ?><span>Expenditure</span><strong><?= h(money($dayLive['expense'])) ?></strong></div>
  <div class="card stat"><?= icon('wallet', 20) ?><span>Net profit</span><strong><?= h(money($dayLive['profit'])) ?></strong><em>Tax <?= h(money($dayLive['tax'])) ?></em></div>
</div>
<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('clock', 16) ?><?= $dayOpen ? 'Close this day' : 'Open this day' ?></h2></div>
    <div class="pad-form">
      <?php if (!$todayDay): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="open_day">
          <label for="open_cash">Cash you started with</label>
          <input id="open_cash" name="open_cash" inputmode="decimal" required>
          <p class="hint">Count the till. Type that amount. Then you can sell.</p>
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Open day</button></div>
        </form>
      <?php elseif ($dayOpen): ?>
        <p class="lede">Started with <?= h(money((float) $todayDay['open_cash'])) ?>.</p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="close_day">
          <label for="close_cash">Cash you closed with</label>
          <input id="close_cash" name="close_cash" inputmode="decimal" required>
          <label for="notes">Note</label>
          <input id="notes" name="notes">
          <div class="actions" style="margin-top:12px"><button class="btn" type="submit"><?= icon('check') ?>Close day</button></div>
        </form>
      <?php else: ?>
        <p class="lede">Closed with <?= h(money((float) ($todayDay['close_cash'] ?? 0))) ?>.</p>
        <p>Income <?= h(money((float) $todayDay['income'])) ?> · Spend <?= h(money((float) $todayDay['expense'])) ?> · Tax <?= h(money((float) $todayDay['tax'])) ?> · Net <?= h(money((float) $todayDay['income'] - (float) $todayDay['expense'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('reports', 16) ?>Recent days</h2></div>
    <?php $days = stock_recent_days(); ?>
    <?php if (!$days): ?>
      <p class="empty">No days recorded yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="grid">
          <thead><tr><th>Date</th><th>Status</th><th class="right">Income</th><th class="right">Spend</th><th class="right">Net</th><th class="right">Tax</th></tr></thead>
          <tbody>
            <?php foreach ($days as $d): ?>
              <tr>
                <td><?= h(format_date($d['day_date'])) ?></td>
                <td><?= $d['closed_at'] ? 'Closed' : 'Open' ?></td>
                <td class="right mono"><?= h(money((float) $d['income'])) ?></td>
                <td class="right mono"><?= h(money((float) $d['expense'])) ?></td>
                <td class="right mono"><?= h(money((float) $d['income'] - (float) $d['expense'])) ?></td>
                <td class="right mono"><?= h(money((float) $d['tax'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php
$buyJs = '<script>(function(){var form=document.querySelector("[data-stock-buy]");if(!form)return;var cat=[];try{cat=JSON.parse(document.getElementById("stock-buy-catalog").textContent||"[]");}catch(e){}function find(n){n=(n||"").trim().toLowerCase();if(!n)return null;return cat.find(function(p){return String(p.name).toLowerCase()===n||String(p.sku).toLowerCase()===n;})||null;}form.addEventListener("input",function(e){var row=e.target.closest("tr");if(!row)return;if(e.target.matches("[data-buy-item]")){var p=find(e.target.value);if(p){var hid=row.querySelector("[data-buy-id]");if(hid)hid.value=p.id;var price=row.querySelector("[data-buy-price]");if(price&&!price.value)price.value=p.buy;var tax=row.querySelector("[data-buy-tax]");if(tax)tax.checked=!!p.taxed;}}var q=parseFloat((row.querySelector("[data-buy-qty]")||{}).value||"0")||0;var r=parseFloat((row.querySelector("[data-buy-price]")||{}).value||"0")||0;var tot=row.querySelector("[data-buy-total]");if(tot)tot.textContent=(q*r).toFixed(2);});})();</script>';
layout_end($tab === 'purchases' ? $buyJs : '');
