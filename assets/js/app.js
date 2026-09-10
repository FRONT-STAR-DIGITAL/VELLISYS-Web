document.addEventListener('click', function (e) {
  document.querySelectorAll('details.share-pop[open]').forEach(function (el) {
    if (!el.contains(e.target)) el.removeAttribute('open');
  });
  var q = document.querySelector('[data-quick]');
  var panel = document.querySelector('[data-quick-panel]');
  if (q && q.contains(e.target) && panel) {
    panel.hidden = !panel.hidden;
  } else if (panel && !panel.contains(e.target)) {
    panel.hidden = true;
  }

  var toggle = e.target.closest('[data-nav-toggle]');
  var scrim = document.querySelector('[data-nav-scrim]');
  function closeNav() {
    document.body.classList.remove('nav-open');
    document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
    if (scrim) scrim.hidden = true;
  }
  if (toggle) {
    var open = !document.body.classList.contains('nav-open');
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (scrim) scrim.hidden = !open;
    return;
  }
  if (e.target.closest('[data-nav-scrim]')) {
    closeNav();
    return;
  }
  if (e.target.closest('[data-nav] a') && window.matchMedia('(max-width: 820px)').matches) {
    closeNav();
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

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape' || !document.body.classList.contains('nav-open')) return;
  document.body.classList.remove('nav-open');
  document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
    btn.setAttribute('aria-expanded', 'false');
  });
  var scrim = document.querySelector('[data-nav-scrim]');
  if (scrim) scrim.hidden = true;
});

document.querySelectorAll('[data-fill-login]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var email = document.getElementById('email');
    var pass = document.getElementById('password');
    if (email) email.value = btn.getAttribute('data-fill-email') || '';
    if (pass) pass.value = btn.getAttribute('data-fill-password') || '';
    if (email) email.focus();
  });
});

document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var wrap = btn.closest('.field-control, .gate-pw');
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

function fxHome(form) {
  return String((form && form.getAttribute('data-fx-home')) || 'USD').toUpperCase();
}

function fxRate(form) {
  var el = (form || document).querySelector('[data-fx-rate]');
  var n = parseFloat(String((el && el.value) || '0').replace(/,/g, ''));
  return n > 0 ? n : 1;
}

function convertAmount(n, from, to, rate, home) {
  from = String(from || '').toUpperCase();
  to = String(to || '').toUpperCase();
  home = String(home || 'USD').toUpperCase();
  if (from === to) return n;
  var usd = from === 'USD' ? n : n / rate;
  if (to === 'USD') return Math.round(usd * 100) / 100;
  return Math.round(usd * rate * 100) / 100;
}

function formatConverted(n, currency) {
  currency = String(currency || '').toUpperCase();
  var zero = { BIF:1, CLP:1, DJF:1, GNF:1, ISK:1, JPY:1, KMF:1, KRW:1, PYG:1, RWF:1, UGX:1, VND:1, VUV:1, XAF:1, XOF:1, XPF:1 };
  if (zero[currency] && Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
  return n.toFixed(2);
}

function convertDocumentCurrency(form, from, to) {
  if (!from || !to || from === to) return;
  var rate = fxRate(form);
  var home = fxHome(form);
  form.querySelectorAll('[data-line-rate]').forEach(function (inp) {
    var n = parseFloat(String(inp.value || '0').replace(/,/g, ''));
    if (!n) {
      inp.value = '';
      return;
    }
    inp.value = formatConverted(convertAmount(n, from, to, rate, home), to);
    var row = inp.closest('tr');
    if (row) updateLineTotal(row);
  });
  var alloc = form.querySelector('#allocated_amount');
  if (alloc && alloc.value) {
    var a = parseFloat(String(alloc.value).replace(/,/g, ''));
    if (!isNaN(a) && a) alloc.value = formatConverted(convertAmount(a, from, to, rate, home), to);
  }
}

function updateFxPreview(form) {
  if (!form) return;
  var hint = form.querySelector('[data-fx-preview]');
  var currency = form.querySelector('#currency');
  if (!hint || !currency) return;
  var home = fxHome(form);
  var from = (currency.value || home).toUpperCase();
  var to = from === 'USD' ? home : 'USD';
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
  if (from === to) {
    hint.textContent = 'Working in ' + from + '.';
    return;
  }
  if (!total) {
    hint.textContent = 'Also ' + to + ' at ' + rate.toLocaleString('en-US') + ' ' + home + ' / USD.';
    return;
  }
  var conv = convertAmount(total, from, to, rate, home);
  var shown = formatConverted(conv, to);
  hint.textContent = 'Also ' + to + ' ' + shown + ' at ' + rate.toLocaleString('en-US') + ' ' + home + ' / USD.';
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

document.querySelectorAll('.currency-code').forEach(function (inp) {
  var tidy = function () {
    inp.value = String(inp.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
    if (inp.hasAttribute('data-fx-home-input')) {
      document.querySelectorAll('[data-fx-home-label]').forEach(function (el) {
        el.textContent = inp.value || 'USD';
      });
      var form = inp.closest('[data-fx-form]');
      if (form) form.setAttribute('data-fx-home', inp.value || 'USD');
    }
  };
  inp.addEventListener('input', tidy);
  inp.addEventListener('blur', tidy);
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

(function () {
  var box = document.querySelector('[data-mail-box]');
  if (!box) return;
  var sel = box.querySelector('[data-mail-provider]');
  var json = box.querySelector('[data-mail-presets]');
  if (!sel || !json) return;
  var presets = {};
  try {
    presets = JSON.parse(json.textContent || '{}');
  } catch (e) {
    return;
  }
  sel.addEventListener('change', function () {
    var p = presets[sel.value];
    if (!p) return;
    Object.keys(p).forEach(function (key) {
      if (key === 'label') return;
      var el = box.querySelector('[data-mail-field="' + key + '"]');
      if (el) el.value = p[key];
    });
  });
})();

(function () {
  var root = document.querySelector('[data-clock]');
  if (!root) return;
  var dateEl = root.querySelector('[data-clock-date]');
  var timeEl = root.querySelector('[data-clock-time]');
  function partsOf(now) {
    try {
      var fmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Kampala',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      });
      var map = {};
      fmt.formatToParts(now).forEach(function (p) { map[p.type] = p.value; });
      return {
        date: [map.weekday, map.day, map.month, map.year].filter(Boolean).join(' '),
        time: [map.hour, map.minute, map.second].join(':')
      };
    } catch (err) {
      return null;
    }
  }
  function tick() {
    var now = new Date();
    var p = partsOf(now);
    if (!p) return;
    if (dateEl) dateEl.textContent = p.date;
    if (timeEl) {
      timeEl.textContent = p.time;
      if (timeEl.tagName === 'TIME') timeEl.setAttribute('datetime', now.toISOString());
    }
  }
  tick();
  setInterval(tick, 1000);
})();
