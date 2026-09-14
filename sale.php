<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_stock();

if (!user_can_kind('invoice')) {
    flash('Your login cannot make sales.', 'err');
    redirect('dashboard.php');
}

$error = '';
$dayOpen = stock_day_is_open();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    stock_require_open_day();
    $ids = $_POST['s_item'] ?? [];
    $qtys = $_POST['s_qty'] ?? [];
    $prices = $_POST['s_price'] ?? [];
    $taxed = $_POST['s_taxed'] ?? [];
    $lines = [];
    foreach ((array) $ids as $i => $sid) {
        $lines[] = [
            'stock_item_id' => (int) $sid,
            'qty' => money_parse((string) ($qtys[$i] ?? 0)),
            'price' => money_parse((string) ($prices[$i] ?? 0)),
            'taxed' => !empty($taxed[$i]),
        ];
    }
    $done = stock_complete_sale([
        'customer' => post('customer'),
        'party_id' => (int) post('party_id'),
        'discount' => money_parse(post('discount')),
        'paid' => money_parse(post('paid')),
        'method' => post('method') ?: 'Cash',
        'lines' => $lines,
    ]);
    if (empty($done['ok'])) {
        $error = (string) ($done['error'] ?? 'Could not save that sale.');
    } else {
        $msg = 'Sale saved.';
        if (($done['balance'] ?? 0) > 0.009) {
            $msg .= ' Balance ' . money($done['balance']) . ' sits on Debtors.';
        }
        flash($msg);
        redirect('document_view.php?id=' . (int) $done['print_id'] . '&print=1');
    }
}

$catalog = stock_catalog_payload();
$customers = db_all("SELECT id, name FROM parties WHERE company_id = ? AND kind = 'customer' ORDER BY name LIMIT 80", 'i', [current_company_id()]);
$taxName = company_tax_name();

layout_start('Sale', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('cart') ?>Sale</h1>
    <p class="lede">Type the product. Price and tax fill in. Discount and part pay sit under the table.</p>
  </div>
</div>
<?php render_stock_subnav('sale'); ?>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>
<?php if (!$dayOpen): ?>
  <p class="flash flash-err">Open the day first. <a href="<?= h(url('stock.php?tab=day')) ?>">Open day</a></p>
<?php endif; ?>

<form method="post" class="card pos-sale" data-pos-sale>
  <?= csrf_field() ?>
  <div class="pad-form">
    <div class="form-grid">
      <div>
        <label for="customer">Customer</label>
        <input id="customer" name="customer" list="customer-list" value="Walk-in" <?= $dayOpen ? '' : 'disabled' ?>>
        <datalist id="customer-list">
          <?php foreach ($customers as $c): ?>
            <option value="<?= h($c['name']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label for="method">Paid how</label>
        <input id="method" name="method" value="Cash" <?= $dayOpen ? '' : 'disabled' ?>>
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
          </tr>
        </thead>
        <tbody data-pos-body>
          <tr data-pos-empty>
            <td colspan="5" class="empty">Type a product above. It drops onto this list.</td>
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
        <p class="hint">Pay half if they owe. Unpaid sits on Debtors.</p>
      </div>
      <div class="pos-sum">
        <span>Subtotal <strong data-pos-sub>0</strong></span>
        <span>Tax <strong data-pos-tax>0</strong></span>
        <span>Total <strong data-pos-grand>0</strong></span>
        <span>Due <strong data-pos-due>0</strong></span>
      </div>
    </div>
    <div class="actions sticky-save">
      <button class="btn pos-save" type="submit" <?= $dayOpen ? '' : 'disabled' ?>><?= icon('printer') ?>Save and print</button>
    </div>
  </div>
</form>
<script type="application/json" id="pos-catalog"><?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="pos-tax"><?= json_encode(['rate' => company_tax_rate(), 'name' => $taxName]) ?></script>
<?php
$js = <<<'JS'
<script>
(function () {
  var form = document.querySelector('[data-pos-sale]');
  if (!form) return;
  var cat = [];
  var tax = { rate: 0 };
  try { cat = JSON.parse(document.getElementById('pos-catalog').textContent || '[]'); } catch (e) {}
  try { tax = JSON.parse(document.getElementById('pos-tax').textContent || '{}'); } catch (e2) {}
  var q = form.querySelector('[data-pos-q]');
  var box = form.querySelector('[data-pos-suggest]');
  var body = form.querySelector('[data-pos-body]');
  var n = 0;
  function money(v) { return (Math.round(v * 100) / 100).toFixed(2); }
  function lines() { return Array.prototype.slice.call(body.querySelectorAll('tr[data-pos-line]')); }
  function totals() {
    var sub = 0, taxedNet = 0;
    lines().forEach(function (row) {
      var qty = parseFloat(row.querySelector('[data-line-qty]').value || '0') || 0;
      var rate = parseFloat(row.querySelector('[data-line-rate]').value || '0') || 0;
      var tot = qty * rate;
      row.querySelector('[data-line-total]').textContent = money(tot);
      sub += tot;
      if (row.querySelector('[data-vat-box]').checked) taxedNet += tot;
    });
    var disc = parseFloat((form.querySelector('[data-pos-discount]') || {}).value || '0') || 0;
    if (disc > sub) disc = sub;
    var after = sub - disc;
    var factor = sub > 0 ? after / sub : 1;
    var taxAmt = taxedNet * factor * (Number(tax.rate) || 0);
    var grand = after + taxAmt;
    var paidInp = form.querySelector('[data-pos-paid]');
    var paid = parseFloat(paidInp.value || '');
    if (paidInp.value === '' || isNaN(paid)) paid = grand;
    form.querySelector('[data-pos-sub]').textContent = money(sub);
    form.querySelector('[data-pos-tax]').textContent = money(taxAmt);
    form.querySelector('[data-pos-grand]').textContent = money(grand);
    form.querySelector('[data-pos-due]').textContent = money(Math.max(0, grand - paid));
  }
  function addItem(p) {
    var empty = body.querySelector('[data-pos-empty]');
    if (empty) empty.remove();
    var exist = lines().find(function (row) { return String(row.querySelector('[data-sid]').value) === String(p.id); });
    if (exist) {
      var qty = exist.querySelector('[data-line-qty]');
      qty.value = String((parseFloat(qty.value || '0') || 0) + 1);
      totals();
      qty.focus();
      return;
    }
    var i = n++;
    var tr = document.createElement('tr');
    tr.setAttribute('data-pos-line', '1');
    tr.innerHTML = '<td>' +
      '<input type="hidden" name="s_item[' + i + ']" value="' + p.id + '" data-sid>' +
      '<strong>' + String(p.name).replace(/</g, '') + '</strong>' +
      '<div class="muted">' + String(p.sku || '').replace(/</g, '') + ' · ' + p.qty + ' left</div></td>' +
      '<td class="line-qty"><input name="s_qty[' + i + ']" type="number" min="0" step="any" value="1" data-line-qty></td>' +
      '<td class="line-rate"><input name="s_price[' + i + ']" type="number" min="0" step="any" value="' + p.sell + '" data-line-rate></td>' +
      '<td class="right mono"><span data-line-total>' + money(p.sell) + '</span></td>' +
      '<td class="center"><label class="vat-yn"><input type="checkbox" name="s_taxed[' + i + ']" value="1" data-vat-box ' + (p.taxed ? 'checked' : '') + '><span>' + (p.taxed ? 'Y' : 'N') + '</span></label></td>';
    body.appendChild(tr);
    totals();
  }
  function show(list) {
    if (!list.length) { box.hidden = true; box.innerHTML = ''; return; }
    box.innerHTML = list.map(function (p) {
      return '<button type="button" class="pos-opt" data-id="' + p.id + '"><strong>' + String(p.name).replace(/</g, '') + '</strong><span>' + (p.sku || '') + ' · ' + p.qty + ' · ' + money(p.sell) + '</span></button>';
    }).join('');
    box.hidden = false;
  }
  if (q) {
    q.addEventListener('input', function () {
      var s = q.value.trim().toLowerCase();
      if (!s) { show([]); return; }
      var list = cat.filter(function (p) {
        return String(p.name).toLowerCase().indexOf(s) !== -1 || String(p.sku).toLowerCase().indexOf(s) !== -1;
      }).slice(0, 8);
      show(list);
    });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = box.querySelector('[data-id]');
        if (first) first.click();
      }
    });
  }
  box.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-id]');
    if (!btn) return;
    var p = cat.find(function (x) { return String(x.id) === String(btn.getAttribute('data-id')); });
    if (p) addItem(p);
    q.value = '';
    show([]);
    q.focus();
  });
  form.addEventListener('input', function (e) {
    if (e.target.matches('[data-vat-box]')) {
      var yn = e.target.parentElement.querySelector('span');
      if (yn) yn.textContent = e.target.checked ? 'Y' : 'N';
    }
    totals();
  });
  form.addEventListener('submit', function (e) {
    if (!lines().length) {
      e.preventDefault();
      alert('Add a product first.');
    }
  });
})();
</script>
JS;
layout_end($js);
