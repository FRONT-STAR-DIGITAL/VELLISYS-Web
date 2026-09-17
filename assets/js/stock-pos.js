(function () {
  var form = document.querySelector('[data-pos-till]');
  if (!form) return;
  var prefix = form.getAttribute('data-pos-prefix') || 's';
  var mode = form.getAttribute('data-pos-mode') || 'sale';
  var cat = [];
  var tax = { rate: 0 };
  var catEl = document.getElementById('pos-catalog');
  var taxEl = document.getElementById('pos-tax');
  try { if (catEl) cat = JSON.parse(catEl.textContent || '[]'); } catch (e) {}
  try { if (taxEl) tax = JSON.parse(taxEl.textContent || '{}'); } catch (e2) {}
  var q = form.querySelector('[data-pos-q]');
  var box = form.querySelector('[data-pos-suggest]');
  var body = form.querySelector('[data-pos-body]');
  var n = 0;
  function currency() { return String(form.getAttribute('data-pos-currency') || '').toUpperCase(); }
  function money(v) {
    v = Math.round((Number(v) || 0) * 100) / 100;
    var formatted = v.toLocaleString('en-US', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
    var cur = currency();
    return cur ? (formatted + ' ' + cur) : formatted;
  }
  function lines() { return Array.prototype.slice.call(body.querySelectorAll('tr[data-pos-line]')); }
  function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
  function totals() {
    var sub = 0, taxedNet = 0;
    lines().forEach(function (row) {
      var qty = parseFloat(row.querySelector('[data-line-qty]').value || '0') || 0;
      var rate = parseFloat(row.querySelector('[data-line-rate]').value || '0') || 0;
      var tot = qty * rate;
      var totEl = row.querySelector('[data-line-total]');
      if (totEl) totEl.textContent = money(tot);
      sub += tot;
      var taxBox = row.querySelector('[data-vat-box]');
      if (taxBox && taxBox.checked) taxedNet += tot;
    });
    var discEl = form.querySelector('[data-pos-discount]');
    var disc = discEl ? (parseFloat(discEl.value || '0') || 0) : 0;
    if (disc > sub) disc = sub;
    var after = sub - disc;
    var factor = sub > 0 ? after / sub : 1;
    var taxAmt = taxedNet * factor * (Number(tax.rate) || 0);
    var grand = after + taxAmt;
    var paidInp = form.querySelector('[data-pos-paid]');
    var paid = paidInp ? parseFloat(paidInp.value || '') : grand;
    if (!paidInp || paidInp.value === '' || isNaN(paid)) paid = grand;
    var set = function (sel, v) {
      var el = form.querySelector(sel);
      if (el) el.textContent = money(v);
    };
    set('[data-pos-sub]', sub);
    set('[data-pos-tax]', taxAmt);
    set('[data-pos-grand]', grand);
    set('[data-pos-due]', Math.max(0, grand - paid));
  }
  function addItem(p, isNew) {
    var empty = body.querySelector('[data-pos-empty]');
    if (empty) empty.remove();
    if (p.id && !isNew) {
      var exist = lines().find(function (row) {
        return String(row.querySelector('[data-sid]').value) === String(p.id);
      });
      if (exist) {
        var qty = exist.querySelector('[data-line-qty]');
        qty.value = String((parseFloat(qty.value || '0') || 0) + 1);
        totals();
        qty.focus();
        return;
      }
    }
    var i = n++;
    var price = mode === 'buy' ? (p.buy || p.sell || 0) : (p.sell || 0);
    var tr = document.createElement('tr');
    tr.setAttribute('data-pos-line', '1');
    tr.innerHTML = '<td>' +
      '<input type="hidden" name="' + prefix + '_item[' + i + ']" value="' + (p.id || '') + '" data-sid>' +
      '<input type="hidden" name="' + prefix + '_name[' + i + ']" value="' + esc(p.name) + '">' +
      '<strong>' + esc(p.name) + '</strong>' +
      '<div class="muted">' + esc(p.sku || (isNew ? 'New product' : '')) + (p.qty != null && !isNew ? ' · ' + p.qty + ' left' : '') + '</div></td>' +
      '<td class="line-qty"><input name="' + prefix + '_qty[' + i + ']" type="number" min="0" step="any" value="1" data-line-qty></td>' +
      '<td class="line-rate"><input name="' + prefix + '_price[' + i + ']" type="number" min="0" step="any" value="' + price + '" data-line-rate></td>' +
      '<td class="right mono"><span data-line-total>' + money(price) + '</span></td>' +
      '<td class="center"><label class="vat-yn"><input type="checkbox" name="' + prefix + '_taxed[' + i + ']" value="1" data-vat-box ' + (p.taxed ? 'checked' : '') + '><span>' + (p.taxed ? 'Y' : 'N') + '</span></label></td>' +
      '<td class="center"><button type="button" class="btn ghost sm icon-only" data-pos-remove aria-label="Remove">' +
      (document.querySelector('[data-pos-x]') ? document.querySelector('[data-pos-x]').innerHTML : '×') +
      '</button></td>';
    body.appendChild(tr);
    totals();
  }
  function show(list, typed) {
    if (!box) return;
    var html = list.map(function (p) {
      return '<button type="button" class="pos-opt" data-id="' + p.id + '"><strong>' + esc(p.name) + '</strong><span>' + esc(p.sku || '') + ' · ' + (p.qty != null ? p.qty + ' · ' : '') + money(mode === 'buy' ? p.buy : p.sell) + '</span></button>';
    }).join('');
    if (mode === 'buy' && typed) {
      var exact = list.some(function (p) { return String(p.name).toLowerCase() === typed.toLowerCase(); });
      if (!exact) {
        html += '<button type="button" class="pos-opt" data-new="' + esc(typed) + '"><strong>Add new: ' + esc(typed) + '</strong><span>Not in stock yet. We will add it on save.</span></button>';
      }
    }
    if (!html) { box.hidden = true; box.innerHTML = ''; return; }
    box.innerHTML = html;
    box.hidden = false;
  }
  if (q) {
    q.addEventListener('input', function () {
      var s = q.value.trim().toLowerCase();
      if (!s) { show([]); return; }
      var list = cat.filter(function (p) {
        return String(p.name).toLowerCase().indexOf(s) !== -1 || String(p.sku).toLowerCase().indexOf(s) !== -1;
      }).slice(0, 8);
      show(list, q.value.trim());
    });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = box && box.querySelector('[data-id], [data-new]');
        if (first) first.click();
      }
    });
  }
  if (box) {
    box.addEventListener('click', function (e) {
      var neu = e.target.closest('[data-new]');
      if (neu) {
        addItem({ id: 0, name: neu.getAttribute('data-new'), taxed: !!tax.default }, true);
        if (q) q.value = '';
        show([]);
        if (q) q.focus();
        return;
      }
      var btn = e.target.closest('[data-id]');
      if (!btn) return;
      var p = cat.find(function (x) { return String(x.id) === String(btn.getAttribute('data-id')); });
      if (p) addItem(p, false);
      if (q) q.value = '';
      show([]);
      if (q) q.focus();
    });
  }
  form.addEventListener('click', function (e) {
    var rm = e.target.closest('[data-pos-remove]');
    if (!rm) return;
    e.preventDefault();
    var row = rm.closest('tr');
    if (row) row.remove();
    if (!lines().length && body) {
      body.innerHTML = '<tr data-pos-empty><td colspan="6" class="empty">Type a product above. It drops onto this list.</td></tr>';
    }
    totals();
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
  totals();
})();
