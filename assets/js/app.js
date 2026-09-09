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
    row.querySelectorAll('input').forEach(function (inp) {
      if (inp.name) inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
      if (inp.type === 'checkbox') {
        return;
      } else if (inp.name && inp.name.indexOf('item_qty') !== -1) {
        inp.value = '1';
      } else if (inp.name && inp.name.indexOf('item_unit') !== -1) {
        inp.value = inp.value || 'lot';
      } else {
        inp.value = '';
      }
    });
    tbody.appendChild(row);
    var focus = row.querySelector('input[name^="item_desc"]');
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
    if (currency && opt.getAttribute('data-currency')) currency.value = opt.getAttribute('data-currency');
    if (amount && opt.getAttribute('data-balance')) amount.value = opt.getAttribute('data-balance');
  });
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
