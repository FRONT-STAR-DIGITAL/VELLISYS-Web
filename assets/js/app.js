document.addEventListener('click', function (e) {
  var q = document.querySelector('[data-quick]');
  var panel = document.querySelector('[data-quick-panel]');
  if (q && q.contains(e.target) && panel) {
    panel.hidden = !panel.hidden;
  } else if (panel && !panel.contains(e.target)) {
    panel.hidden = true;
  }
});

(function () {
  var pairs = document.querySelectorAll('[data-color-pair]');
  if (!pairs.length) {
    var picker = document.querySelector('[data-color-picker]');
    var hex = document.querySelector('[data-color-hex]');
    if (picker && hex) {
      picker.addEventListener('input', function () { hex.value = picker.value.toUpperCase(); applyBrandVars(); });
      hex.addEventListener('input', function () { applyFromHex(hex, picker); });
    }
    return;
  }

  function applyFromHex(hex, picker) {
    var v = (hex.value || '').trim();
    if (v.charAt(0) !== '#') v = '#' + v;
    v = v.toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(v)) return;
    picker.value = v;
    hex.value = v;
    applyBrandVars();
  }

  function applyBrandVars() {
    var map = { primary: '--brand', accent: '--brand-2', deep: '--brand-3' };
    pairs.forEach(function (row) {
      var role = row.getAttribute('data-color-role') || 'primary';
      var picker = row.querySelector('[data-color-picker]');
      var hex = row.querySelector('[data-color-hex]');
      if (!picker) return;
      var v = (picker.value || '').toUpperCase();
      if (hex) hex.value = v;
      var prop = map[role];
      if (prop) document.documentElement.style.setProperty(prop, v);
    });
    var preview = document.querySelector('[data-color-preview]');
    var primary = document.documentElement.style.getPropertyValue('--brand');
    if (preview && primary) preview.style.borderTopColor = primary;
  }

  pairs.forEach(function (row) {
    var picker = row.querySelector('[data-color-picker]');
    var hex = row.querySelector('[data-color-hex]');
    if (picker) picker.addEventListener('input', applyBrandVars);
    if (hex && picker) {
      hex.addEventListener('input', function () { applyFromHex(hex, picker); });
      hex.addEventListener('change', function () { applyFromHex(hex, picker); });
    }
  });
})();

document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var wrap = btn.closest('.field-control');
    var input = wrap ? wrap.querySelector('input') : null;
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    var on = btn.querySelector('[data-eye]');
    var off = btn.querySelector('[data-eye-off]');
    if (on) on.hidden = show;
    if (off) off.hidden = !show;
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.setAttribute('title', show ? 'Hide password' : 'Show password');
  });
});

document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-line]');
  if (add) {
    e.preventDefault();
    var tbody = document.querySelector('#lines tbody');
    if (!tbody) return;
    var proto = tbody.querySelector('tr');
    if (!proto) return;
    var i = tbody.querySelectorAll('tr').length;
    var row = proto.cloneNode(true);
    row.querySelectorAll('input, textarea').forEach(function (inp) {
      if (inp.name) inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
      if (inp.type === 'checkbox') {
        inp.checked = false;
        var yn = row.querySelector('[data-vat-yn]');
        if (yn) yn.textContent = 'N';
        return;
      } else if (inp.name && inp.name.indexOf('item_qty') !== -1) {
        inp.value = '1';
      } else {
        inp.value = '';
      }
    });
    var total = row.querySelector('[data-line-total]');
    if (total) total.textContent = '0';
    tbody.appendChild(row);
    var focus = row.querySelector('input[name^="item_name"], textarea[name^="item_desc"]');
    if (focus) focus.focus();
    return;
  }
  var qtyBtn = e.target.closest('[data-qty-delta]');
  if (!qtyBtn) return;
  e.preventDefault();
  var wrap = qtyBtn.closest('.qty-wrap');
  var input = wrap && wrap.querySelector('input[name*="item_qty"]');
  if (!input) return;
  var n = parseFloat(String(input.value).replace(/,/g, ''));
  if (isNaN(n)) n = 0;
  n += parseFloat(qtyBtn.getAttribute('data-qty-delta')) || 0;
  if (n < 0) n = 0;
  input.value = Number.isInteger(n) ? String(n) : String(Math.round(n * 100) / 100);
  var row = wrap.closest('tr');
  if (row) updateLineTotal(row);
});

function updateLineTotal(row) {
  var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
  var rate = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
  if (isNaN(qty)) qty = 0;
  if (isNaN(rate)) rate = 0;
  var n = Math.round(qty * rate * 100) / 100;
  var out = row.querySelector('[data-line-total]');
  if (out) out.textContent = n ? n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }) : '0';
}

document.addEventListener('input', function (e) {
  var row = e.target.closest && e.target.closest('#lines tr');
  if (!row) return;
  if (e.target.matches('[data-line-qty], [data-line-rate]')) updateLineTotal(row);
});

document.addEventListener('change', function (e) {
  if (!e.target.matches('[data-vat-box]')) return;
  var yn = e.target.closest('label') && e.target.closest('label').querySelector('[data-vat-yn]');
  if (yn) yn.textContent = e.target.checked ? 'Y' : 'N';
});

document.querySelectorAll('[data-letter-templates]').forEach(function (form) {
  var subject = form.querySelector('#subject');
  var body = form.querySelector('#body');
  form.querySelectorAll('input[name="letter_template"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var card = radio.closest('.template-card');
      if (!card) return;
      if (subject) subject.value = card.getAttribute('data-subject') || '';
      if (body) body.value = card.getAttribute('data-body') || '';
    });
  });
});

document.querySelectorAll('[data-receipt-form]').forEach(function (form) {
  var sel = form.querySelector('[data-against-invoice]');
  if (!sel) return;
  sel.addEventListener('change', function () {
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    var party = form.querySelector('#party_id');
    var currency = form.querySelector('#currency');
    var amount = form.querySelector('#allocated_amount');
    if (party && opt.getAttribute('data-party')) party.value = opt.getAttribute('data-party');
    if (amount && opt.getAttribute('data-balance')) amount.value = opt.getAttribute('data-balance');
    if (currency && opt.getAttribute('data-currency')) {
      var from = currency.value;
      var to = opt.getAttribute('data-currency');
      currency.value = to;
      currency.setAttribute('data-fx-currency', to);
      if (from && to && from !== to) convertDocumentCurrency(form, from, to);
    }
    updateFxPreview(form);
  });
});

function fxRate(form) {
  var el = (form || document).querySelector('[data-fx-rate]');
  var n = parseFloat(String((el && el.value) || '0').replace(/,/g, ''));
  return n > 0 ? n : 3700;
}

function convertAmount(n, from, to, rate) {
  if (from === to) return n;
  if (from === 'USD') return Math.round(n * rate);
  return Math.round((n / rate) * 100) / 100;
}

function formatConverted(n, currency) {
  if (currency === 'USD') return n.toFixed(2);
  return Number.isInteger(n) ? String(n) : String(n);
}

function convertDocumentCurrency(form, from, to) {
  if (!from || !to || from === to) return;
  var rate = fxRate(form);
  form.querySelectorAll('[data-line-rate]').forEach(function (inp) {
    var n = parseFloat(String(inp.value || '0').replace(/,/g, ''));
    if (!n) {
      inp.value = '';
      return;
    }
    inp.value = formatConverted(convertAmount(n, from, to, rate), to);
    var row = inp.closest('tr');
    if (row) updateLineTotal(row);
  });
  var alloc = form.querySelector('#allocated_amount');
  if (alloc && alloc.value) {
    var a = parseFloat(String(alloc.value).replace(/,/g, ''));
    if (!isNaN(a) && a) alloc.value = formatConverted(convertAmount(a, from, to, rate), to);
  }
}

function updateFxPreview(form) {
  if (!form) return;
  var hint = form.querySelector('[data-fx-preview]');
  var currency = form.querySelector('#currency');
  if (!hint || !currency) return;
  var from = currency.value || 'UGX';
  var to = from === 'USD' ? 'UGX' : 'USD';
  var rate = fxRate(form);
  var net = 0;
  var vat = 0;
  form.querySelectorAll('#lines tbody tr').forEach(function (row) {
    var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
    var unit = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
    if (isNaN(qty)) qty = 0;
    if (isNaN(unit)) unit = 0;
    var line = Math.round(qty * unit * 100) / 100;
    net += line;
    var box = row.querySelector('[data-vat-box]');
    if (box && box.checked) vat += Math.round(line * 0.18 * 100) / 100;
  });
  var alloc = form.querySelector('#allocated_amount');
  if (alloc && alloc.value) {
    var a = parseFloat(String(alloc.value).replace(/,/g, ''));
    if (!isNaN(a) && a > net) net = a;
  }
  var total = net + vat;
  if (!total) {
    hint.textContent = 'Also ' + to + ' at ' + rate.toLocaleString('en-US') + ' UGX / USD.';
    return;
  }
  var conv = convertAmount(total, from, to, rate);
  var shown = to === 'USD' ? conv.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : conv.toLocaleString('en-US');
  hint.textContent = 'Also ' + to + ' ' + shown + ' at ' + rate.toLocaleString('en-US') + ' UGX / USD.';
}

document.querySelectorAll('[data-fx-form]').forEach(function (form) {
  var currency = form.querySelector('#currency');
  if (currency && !currency.getAttribute('data-fx-currency')) {
    currency.setAttribute('data-fx-currency', currency.value);
  }
  form.addEventListener('change', function (e) {
    if (e.target === currency) {
      var from = currency.getAttribute('data-fx-currency') || currency.value;
      var to = currency.value;
      convertDocumentCurrency(form, from, to);
      currency.setAttribute('data-fx-currency', to);
    }
    updateFxPreview(form);
  });
  form.addEventListener('input', function () {
    updateFxPreview(form);
  });
  updateFxPreview(form);
});

document.querySelectorAll('[data-add-template]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var list = document.querySelector('[data-tpl-list]');
    var proto = document.querySelector('#tpl-proto');
    if (!list || !proto) return;
    var html = proto.innerHTML.replace(/__KEY__/g, 'c' + Date.now());
    list.insertAdjacentHTML('beforeend', html);
    var title = list.lastElementChild && list.lastElementChild.querySelector('input[name$="[title]"]');
    if (title) title.focus();
  });
});
