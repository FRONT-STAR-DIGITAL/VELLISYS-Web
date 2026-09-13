try {
  document.cookie = 'vellisys_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || '') + ';path=/;max-age=31536000;samesite=lax';
} catch (e0) {}

document.addEventListener('click', function (e) {
  if (e.target.closest('[data-print-pdf]')) {
    e.preventDefault();
    window.print();
    return;
  }
  document.querySelectorAll('details.share-pop[open]').forEach(function (el) {
    if (!el.contains(e.target)) el.removeAttribute('open');
  });
  document.querySelectorAll('details.top-bell[open]').forEach(function (el) {
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
    var map = { primary: '--brand', accent: '--brand-2' };
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

function togglePasswordButton(btn) {
  if (!btn) return;
  var wrap = btn.closest('.field-control, .gate-pw, .pw-field');
  var input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
  if (!input && btn.parentElement) input = btn.parentElement.querySelector('input');
  if (!input) return;
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  var on = btn.querySelector('[data-eye]');
  var off = btn.querySelector('[data-eye-off]');
  if (on) on.hidden = show;
  if (off) off.hidden = !show;
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  btn.setAttribute('title', show ? 'Hide password' : 'Show password');
  btn.setAttribute('aria-pressed', show ? 'true' : 'false');
}

document.addEventListener('click', function (e) {
  var el = e.target;
  if (el && el.nodeType === 3) el = el.parentElement;
  var btn = el && el.closest ? el.closest('[data-toggle-password]') : null;
  if (!btn) return;
  e.preventDefault();
  e.stopPropagation();
  togglePasswordButton(btn);
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
    refreshLinesPreview();
    return;
  }
  var removeOne = e.target.closest('[data-remove-line]');
  if (removeOne) {
    e.preventDefault();
    var row = removeOne.closest('tr');
    var tbody = document.querySelector('#lines tbody');
    if (!row || !tbody) return;
    if (tbody.querySelectorAll('tr').length <= 1) {
      row.querySelectorAll('input, textarea').forEach(function (inp) {
        if (inp.type === 'checkbox') {
          inp.checked = false;
          var yn = row.querySelector('[data-vat-yn]');
          if (yn) yn.textContent = 'N';
        } else if (inp.name && inp.name.indexOf('item_qty') !== -1) {
          inp.value = '1';
        } else if (inp.type !== 'hidden') {
          inp.value = '';
        }
      });
      var total = row.querySelector('[data-line-total]');
      if (total) total.textContent = '0';
    } else {
      row.remove();
      renumberLines();
    }
    refreshLinesPreview();
    return;
  }
  var removeLast = e.target.closest('[data-remove-last-line]');
  if (removeLast) {
    e.preventDefault();
    var tbody = document.querySelector('#lines tbody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    if (!rows.length) return;
    var btn = rows[rows.length - 1].querySelector('[data-remove-line]');
    if (btn) btn.click();
    return;
  }
  var addField = e.target.closest('[data-add-custom-field]');
  if (addField) {
    e.preventDefault();
    var box = document.querySelector('[data-custom-fields]');
    if (!box) return;
    var row = document.createElement('div');
    row.className = 'custom-field-row';
    row.innerHTML = '<input name="custom_field_label[]" placeholder="Field label"><input name="custom_field_key[]" placeholder="key (optional)">';
    box.appendChild(row);
    var inp = row.querySelector('input');
    if (inp) inp.focus();
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
  refreshLinesPreview();
});

function renumberLines() {
  var tbody = document.querySelector('#lines tbody');
  if (!tbody) return;
  tbody.querySelectorAll('tr').forEach(function (row, i) {
    row.querySelectorAll('input, textarea').forEach(function (inp) {
      if (inp.name) inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
    });
  });
}

function updateLineTotal(row) {
  var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
  var rate = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
  if (isNaN(qty)) qty = 0;
  if (isNaN(rate)) rate = 0;
  var n = Math.round(qty * rate * 100) / 100;
  var out = row.querySelector('[data-line-total]');
  if (out) out.textContent = n ? n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }) : '0';
}

function formatPreviewMoney(n) {
  if (!n) return '0';
  return n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
}

function refreshLinesPreview() {
  var body = document.querySelector('[data-lines-preview-body]');
  var panel = document.querySelector('[data-lines-panel]');
  if (!body || !panel) return;
  var delivery = panel.getAttribute('data-delivery') === '1';
  var rows = document.querySelectorAll('#lines tbody tr');
  var html = '';
  var shown = 0;
  rows.forEach(function (row) {
    var name = ((row.querySelector('input[name^="item_name"]') || {}).value || '').trim();
    var desc = ((row.querySelector('textarea[name^="item_desc"]') || {}).value || '').trim();
    if (!name && !desc) return;
    shown += 1;
    var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
    var rate = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
    if (isNaN(qty)) qty = 0;
    if (isNaN(rate)) rate = 0;
    var taxed = !!(row.querySelector('[data-vat-box]') || {}).checked;
    html += '<tr>';
    html += '<td>' + escapeHtml(name || '-') + '</td>';
    html += '<td>' + escapeHtml(desc).replace(/\n/g, '<br>') + '</td>';
    html += '<td class="center mono">' + escapeHtml(String(qty || '')) + '</td>';
    if (!delivery) {
      html += '<td class="right mono">' + escapeHtml(formatPreviewMoney(rate)) + '</td>';
      html += '<td class="right mono">' + escapeHtml(formatPreviewMoney(Math.round(qty * rate * 100) / 100)) + '</td>';
      html += '<td class="center">' + (taxed ? 'Y' : 'N') + '</td>';
    }
    html += '</tr>';
  });
  if (!shown) {
    html = '<tr><td colspan="' + (delivery ? '3' : '6') + '" class="muted">Add an item above to preview the document table.</td></tr>';
  }
  body.innerHTML = html;
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

document.addEventListener('input', function (e) {
  var row = e.target.closest && e.target.closest('#lines tr');
  if (!row) return;
  if (e.target.matches('[data-line-qty], [data-line-rate]')) updateLineTotal(row);
  if (e.target.matches('input[name^="item_name"], textarea[name^="item_desc"], [data-line-qty], [data-line-rate]')) {
    refreshLinesPreview();
  }
});

document.addEventListener('change', function (e) {
  if (!e.target.matches('[data-vat-box]')) return;
  var yn = e.target.closest('label') && e.target.closest('label').querySelector('[data-vat-yn]');
  if (yn) yn.textContent = e.target.checked ? 'Y' : 'N';
  refreshLinesPreview();
});

if (document.querySelector('[data-lines-preview-body]')) {
  refreshLinesPreview();
}

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

function fxTaxRate(form) {
  var n = parseFloat(String((form && form.getAttribute('data-tax-rate')) || '0.18'));
  if (isNaN(n) || n < 0) n = 0.18;
  if (n > 1) n = n / 100;
  return n;
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
    if (box && box.checked) vat += Math.round(line * fxTaxRate(form) * 100) / 100;
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
    var wrap = inp.closest('[data-currency-pick]');
    var sel = wrap && wrap.querySelector('[data-currency-select]');
    if (sel) {
      var code = inp.value;
      var match = false;
      Array.prototype.forEach.call(sel.options, function (opt) {
        if (opt.value === code) match = true;
      });
      sel.value = match ? code : 'other';
    }
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

document.querySelectorAll('[data-currency-pick]').forEach(function (wrap) {
  var sel = wrap.querySelector('[data-currency-select]');
  var custom = wrap.querySelector('[data-currency-custom]');
  if (!sel || !custom) return;
  sel.addEventListener('change', function () {
    if (sel.value && sel.value !== 'other') {
      custom.value = sel.value;
      custom.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
      custom.focus();
      custom.select();
    }
  });
});

document.querySelectorAll('[data-add-template]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var list = document.querySelector('[data-tpl-list]');
    var proto = document.querySelector('#tpl-proto');
    var bar = document.querySelector('[data-tpl-tab-bar]');
    if (!list || !proto) return;
    var key = 'c' + Date.now();
    var html = proto.innerHTML.replace(/__KEY__/g, key);
    list.insertAdjacentHTML('beforeend', html);
    if (bar) {
      var tab = document.createElement('button');
      tab.type = 'button';
      tab.className = 'tpl-tab';
      tab.setAttribute('data-tpl-tab', key);
      tab.textContent = 'New template';
      bar.appendChild(tab);
    }
    var card = list.lastElementChild;
    var title = card && card.querySelector('input[name$="[title]"]');
    if (window.folioActivateTpl) window.folioActivateTpl(key);
    if (title) title.focus();
  });
});

(function () {
  var root = document.querySelector('[data-tpl-tabs]');
  if (!root) return;
  function activate(key) {
    root.querySelectorAll('[data-tpl-tab]').forEach(function (t) {
      t.classList.toggle('is-on', t.getAttribute('data-tpl-tab') === key);
    });
    root.querySelectorAll('[data-tpl-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-tpl-panel') !== key;
    });
  }
  window.folioActivateTpl = activate;
  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-tpl-tab]');
    if (!t || !root.contains(t)) return;
    activate(t.getAttribute('data-tpl-tab'));
  });
  var first = root.querySelector('[data-tpl-tab]');
  if (first) activate(first.getAttribute('data-tpl-tab'));
})();

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
  if (!dateEl) return;
  function partsOf(now) {
    try {
      var fmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Kampala',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });
      var map = {};
      fmt.formatToParts(now).forEach(function (p) { map[p.type] = p.value; });
      return [map.weekday, map.day, map.month, map.year].filter(Boolean).join(' ');
    } catch (err) {
      return null;
    }
  }
  function tick() {
    var p = partsOf(new Date());
    if (p) dateEl.textContent = p;
  }
  tick();
  setInterval(tick, 60000);
})();

document.querySelectorAll('[data-kinds-form]').forEach(function (form) {
  var box = form.querySelector('[data-custom-doc]');
  var toggle = form.querySelector('[data-custom-kind]');
  function sync() {
    if (!box || !toggle) return;
    box.hidden = !toggle.checked;
  }
  if (toggle) toggle.addEventListener('change', sync);
  sync();
});

(function () {
  var form = document.querySelector('[data-party-book]');
  if (!form) return;
  var sel = form.querySelector('#party_id');
  if (!sel) return;
  var book = {};
  try {
    book = JSON.parse(form.getAttribute('data-party-book') || '{}');
  } catch (e) {
    return;
  }
  function fill(id) {
    var row = book[id] || book[String(id)] || {};
    var map = {
      to_name: row.name || '',
      to_contact: row.contact || '',
      to_tin: row.tin || '',
      to_phone: row.phone || '',
      to_phone2: row.phone2 || '',
      to_email: row.email || '',
      to_address: row.address || '',
      to_city: row.city || '',
      to_country: row.country || ''
    };
    Object.keys(map).forEach(function (name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (el) el.value = map[name];
    });
  }
  sel.addEventListener('change', function () {
    fill(sel.value);
  });
})();

(function () {
  var plan = document.querySelector('[data-planner-plan]');
  var toggle = document.querySelector('[data-planner-toggle]');
  if (!plan || !toggle) return;
  function syncPlanner() {
    if (plan.value === 'sme' || plan.value === 'office') {
      toggle.checked = true;
    }
  }
  plan.addEventListener('change', syncPlanner);
})();


(function () {
  var plan = document.querySelector('[data-planner-plan]');
  var pnl = document.querySelector('[data-pnl-toggle]');
  if (!plan || !pnl) return;
  function syncPnl() {
    if (plan.value === 'office') {
      pnl.checked = true;
    }
  }
  plan.addEventListener('change', syncPnl);
})();
