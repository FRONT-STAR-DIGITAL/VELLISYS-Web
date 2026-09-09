document.addEventListener('click', function (e) {
  var menu = document.querySelector('[data-menu]');
  if (menu && menu.contains(e.target)) {
    document.querySelector('.nav').classList.toggle('is-open');
  }
  var q = document.querySelector('[data-quick]');
  var panel = document.querySelector('[data-quick-panel]');
  if (q && q.contains(e.target) && panel) {
    panel.hidden = !panel.hidden;
  } else if (panel && !panel.contains(e.target)) {
    panel.hidden = true;
  }
});

(function () {
  var picker = document.querySelector('[data-color-picker]');
  var hex = document.querySelector('[data-color-hex]');
  var preview = document.querySelector('[data-color-preview]');
  if (!picker || !hex) return;

  function apply(value) {
    var v = (value || '').trim();
    if (v.charAt(0) !== '#') v = '#' + v;
    v = v.toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(v)) return;
    picker.value = v;
    hex.value = v;
    document.documentElement.style.setProperty('--brand', v);
    if (preview) preview.style.borderTopColor = v;
  }

  picker.addEventListener('input', function () { apply(picker.value); });
  hex.addEventListener('input', function () { apply(hex.value); });
  hex.addEventListener('change', function () { apply(hex.value); });
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
