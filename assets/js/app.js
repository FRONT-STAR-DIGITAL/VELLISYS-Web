try {
  document.cookie = 'vellisys_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || '') + ';path=/;max-age=31536000;samesite=lax';
} catch (e0) {}

window.vellisysChartMoney = function (currency) {
  currency = currency || '';
  return function (v) {
    var n = Number(v);
    if (!isFinite(n)) return '';
    var sign = n < 0 ? '-' : '';
    var a = Math.abs(n);
    var unit = '';
    var x = a;
    if (a >= 1e12) { x = a / 1e12; unit = 'T'; }
    else if (a >= 1e9) { x = a / 1e9; unit = 'B'; }
    else if (a >= 1e6) { x = a / 1e6; unit = 'M'; }
    var num;
    if (unit) {
      if (x >= 100) num = String(Math.round(x));
      else num = (Math.round(x * 10) / 10).toFixed(1).replace(/\.0$/, '');
    } else {
      num = Math.round(a).toLocaleString('en-US');
    }
    return (currency ? currency + ' ' : '') + sign + num + unit;
  };
};

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
  var q = e.target.closest('[data-quick]');
  var qClose = e.target.closest('[data-quick-close], [data-quick-scrim]');
  var panel = document.querySelector('[data-quick-panel]');
  var scrim = document.querySelector('[data-quick-scrim]');
  function setQuick(open) {
    if (panel) panel.hidden = !open;
    if (scrim) scrim.hidden = !open;
    document.body.classList.toggle('quick-open', !!(panel && !panel.hidden));
  }
  if (q) {
    e.preventDefault();
    var opening = !panel || panel.hidden;
    setQuick(opening);
    if (opening) {
      var calcPad = document.querySelector('[data-calc-pad]');
      if (calcPad) calcPad.hidden = true;
      document.body.classList.remove('calc-open');
    }
    return;
  }
  if (qClose) {
    e.preventDefault();
    setQuick(false);
    return;
  }
  if (panel && !panel.hidden && !panel.contains(e.target)) {
    setQuick(false);
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

function companyTaxDefaultOn() {
  var lines = document.querySelector('#lines');
  return !!(lines && lines.getAttribute('data-tax-default') === '1');
}

function applyLineTaxDefault(row) {
  if (!row) return;
  var on = companyTaxDefaultOn();
  var inp = row.querySelector('[data-vat-box]');
  if (inp) inp.checked = on;
  var yn = row.querySelector('[data-vat-yn]');
  if (yn) yn.textContent = on ? 'Y' : 'N';
}

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
        applyLineTaxDefault(row);
        return;
      } else if (inp.name && inp.name.indexOf('item_qty') !== -1) {
        inp.value = '1';
      } else if (inp.name && inp.name.indexOf('item_stock_id') !== -1) {
        inp.value = '0';
      } else {
        inp.value = '';
      }
    });
    var total = row.querySelector('[data-line-total]');
    if (total) {
      if (total.tagName === 'INPUT') total.value = '';
      else total.textContent = '0';
    }
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
          applyLineTaxDefault(row);
        } else if (inp.name && inp.name.indexOf('item_qty') !== -1) {
          inp.value = '1';
        } else if (inp.name && inp.name.indexOf('item_stock_id') !== -1) {
          inp.value = '0';
        } else if (inp.type !== 'hidden') {
          inp.value = '';
        }
      });
      var total = row.querySelector('[data-line-total]');
      if (total) {
        if (total.tagName === 'INPUT') total.value = '';
        else total.textContent = '0';
      }
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
  var addClientField = e.target.closest('[data-add-client-field]');
  if (addClientField) {
    e.preventDefault();
    var list = addClientField.closest('[data-to-tab-panel]');
    var toOrder = (list && list.querySelector('[data-to-order]')) || document.querySelector('[data-to-order]');
    if (!toOrder) return;
    var profile = toOrder.getAttribute('data-to-order') || 'people';
    toOrder.appendChild(buildToOrderExtraRow(profile));
    var inp2 = toOrder.lastElementChild && toOrder.lastElementChild.querySelector('input[name^="to_label"]');
    if (inp2) inp2.focus();
    return;
  }
  var moveTo = e.target.closest('[data-to-move]');
  if (moveTo) {
    e.preventDefault();
    var row = moveTo.closest('[data-to-order-row]');
    var toOrder = row && row.closest('[data-to-order]');
    if (!row || !toOrder) return;
    var dir = parseInt(moveTo.getAttribute('data-to-move'), 10) || 0;
    if (dir < 0 && row.previousElementSibling) {
      toOrder.insertBefore(row, row.previousElementSibling);
    } else if (dir > 0 && row.nextElementSibling) {
      toOrder.insertBefore(row.nextElementSibling, row);
    }
    return;
  }
  var removeTo = e.target.closest('[data-to-remove]');
  if (removeTo) {
    e.preventDefault();
    var gone = removeTo.closest('[data-to-order-row]');
    var host = gone && gone.closest('[data-to-tab-panel]');
    if (gone) gone.remove();
    syncToCoreSelect(host);
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

function parseLineNumber(v) {
  var n = parseFloat(String(v || '').replace(/,/g, ''));
  return isNaN(n) ? 0 : n;
}

function setLineTotalDisplay(el, n) {
  if (!el) return;
  var text = n ? String(Math.round(n * 100) / 100) : '';
  if (el.tagName === 'INPUT') el.value = text;
  else el.textContent = n ? n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }) : '0';
}

function updateLineTotal(row, fromTotal) {
  var qtyEl = row.querySelector('[data-line-qty]');
  var rateEl = row.querySelector('[data-line-rate]');
  var totEl = row.querySelector('[data-line-total]');
  if (fromTotal && totEl) {
    var total = parseLineNumber(totEl.value !== undefined ? totEl.value : totEl.textContent);
    var qty = parseLineNumber(qtyEl && qtyEl.value);
    if (!qty) {
      qty = 1;
      if (qtyEl) qtyEl.value = '1';
    }
    if (rateEl) rateEl.value = String(Math.round((total / qty) * 100) / 100);
    return;
  }
  var qty = parseLineNumber(qtyEl && qtyEl.value);
  var rate = parseLineNumber(rateEl && rateEl.value);
  setLineTotalDisplay(totEl, Math.round(qty * rate * 100) / 100);
}

function formatPreviewMoney(n) {
  if (!n) return '0';
  return n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
}

function docCurrencyCode() {
  var sel = document.querySelector('.document-form #currency, .document-form [name="currency"]');
  if (sel && sel.value) return String(sel.value).toUpperCase();
  var form = document.querySelector('.document-form');
  return form ? String(form.getAttribute('data-fx-home') || '').toUpperCase() : '';
}

function formatDeskMoney(n, currency) {
  currency = String(currency || docCurrencyCode() || '').toUpperCase();
  n = Math.round((Number(n) || 0) * 100) / 100;
  var text = formatPreviewMoney(n);
  return currency ? (text + ' ' + currency) : text;
}

function lineMoneyTotals() {
  var sub = 0;
  var taxedNet = 0;
  document.querySelectorAll('#lines tbody tr').forEach(function (row) {
    var name = ((row.querySelector('input[name^="item_name"]') || {}).value || '').trim();
    var desc = ((row.querySelector('textarea[name^="item_desc"]') || {}).value || '').trim();
    if (!name && !desc) return;
    var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
    var rate = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
    if (isNaN(qty)) qty = 0;
    if (isNaN(rate)) rate = 0;
    var tot = qty * rate;
    sub += tot;
    var taxBox = row.querySelector('[data-vat-box]');
    if (taxBox && taxBox.checked) taxedNet += tot;
  });
  var form = document.querySelector('.document-form');
  var rate = form ? (parseFloat(form.getAttribute('data-tax-rate') || '0') || 0) : 0;
  var taxAmt = Math.round(taxedNet * rate * 100) / 100;
  sub = Math.round(sub * 100) / 100;
  return { sub: sub, tax: taxAmt, grand: Math.round((sub + taxAmt) * 100) / 100 };
}

function updateDocRunningTotals() {
  var box = document.querySelector('[data-doc-sum]');
  var form = document.querySelector('.document-form');
  if (!box || !form) return;
  var t = lineMoneyTotals();
  var cur = docCurrencyCode();
  var set = function (sel, v) {
    var el = box.querySelector(sel);
    if (el) el.textContent = formatDeskMoney(v, cur);
  };
  set('[data-doc-sub]', t.sub);
  set('[data-doc-tax]', t.tax);
  set('[data-doc-grand]', t.grand);
  var dueEl = box.querySelector('[data-doc-due]');
  var due = t.grand;
  if (dueEl) {
    var kind = form.getAttribute('data-doc-kind') || '';
    if (kind === 'receipt' || kind === 'refund') {
      var paidInp = form.querySelector('#allocated_amount');
      var paid = paidInp ? parseFloat(String(paidInp.value || '').replace(/,/g, '')) : NaN;
      if (!paidInp || paidInp.value === '' || isNaN(paid)) paid = t.grand;
      var sel = form.querySelector('#related_id');
      var opt = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
      var balance = opt ? parseFloat(opt.getAttribute('data-balance') || '') : NaN;
      var base = !isNaN(balance) ? balance : t.grand;
      due = Math.max(0, Math.round((base - paid) * 100) / 100);
    }
    dueEl.textContent = formatDeskMoney(due, cur);
  }
  var foot = document.querySelector('[data-lines-preview-foot]');
  if (foot) {
    var taxName = form.getAttribute('data-tax-name') || 'Tax';
    var cols = lineColumnFlags();
    var lead = previewLeadSpan(cols);
    var after = cols.vat ? '<td></td>' : '';
    foot.hidden = false;
    var rows =
      '<tr><td colspan="' + lead + '">Subtotal</td><td class="right mono">' + escapeHtml(formatDeskMoney(t.sub, cur)) + '</td>' + after + '</tr>' +
      '<tr><td colspan="' + lead + '">' + escapeHtml(taxName) + '</td><td class="right mono">' + escapeHtml(formatDeskMoney(t.tax, cur)) + '</td>' + after + '</tr>' +
      '<tr><td colspan="' + lead + '">Total</td><td class="right mono">' + escapeHtml(formatDeskMoney(t.grand, cur)) + '</td>' + after + '</tr>';
    if (dueEl) {
      rows += '<tr><td colspan="' + lead + '">Due</td><td class="right mono">' + escapeHtml(formatDeskMoney(due, cur)) + '</td>' + after + '</tr>';
    }
    foot.innerHTML = rows;
  }
}

function lineColumnFlags() {
  var panel = document.querySelector('[data-lines-panel]');
  var delivery = panel && panel.getAttribute('data-delivery') === '1';
  var cols = { item: true, description: true, qty: true, rate: !delivery, total: !delivery, vat: !delivery };
  if (panel) {
    try {
      var parsed = JSON.parse(panel.getAttribute('data-line-cols') || '[]');
      if (Array.isArray(parsed) && parsed.length) {
        cols.item = parsed.indexOf('item') !== -1;
        cols.description = parsed.indexOf('description') !== -1;
        cols.qty = parsed.indexOf('qty') !== -1;
        cols.rate = !delivery && parsed.indexOf('rate') !== -1;
        cols.total = !delivery && parsed.indexOf('total') !== -1;
        cols.vat = !delivery && parsed.indexOf('vat') !== -1;
      }
    } catch (err) {}
  }
  if (!cols.item && !cols.description) cols.item = true;
  return cols;
}

function previewColCount(cols) {
  var n = 0;
  if (cols.item) n++;
  if (cols.description) n++;
  if (cols.qty) n++;
  if (cols.rate) n++;
  if (cols.total) n++;
  if (cols.vat) n++;
  return Math.max(n, 1);
}

function previewLeadSpan(cols) {
  var n = previewColCount(cols);
  if (cols.vat) n -= 1;
  if (cols.total || cols.rate) n -= 1;
  return Math.max(n, 1);
}

function refreshLinesPreview() {
  var body = document.querySelector('[data-lines-preview-body]');
  var panel = document.querySelector('[data-lines-panel]');
  if (body && panel) {
  var delivery = panel.getAttribute('data-delivery') === '1';
  var cols = lineColumnFlags();
  var rows = document.querySelectorAll('#lines tbody tr');
  var html = '';
  var shown = 0;
  var cur = docCurrencyCode();
  rows.forEach(function (row) {
    var name = ((row.querySelector('input[name^="item_name"]') || {}).value || '').trim();
    var descEl = row.querySelector('textarea[name^="item_desc"]') || row.querySelector('input[name^="item_desc"]');
    var desc = ((descEl || {}).value || '').trim();
    if (!name && !desc) return;
    shown += 1;
    var qty = parseFloat(String((row.querySelector('[data-line-qty]') || {}).value || '0').replace(/,/g, ''));
    var rate = parseFloat(String((row.querySelector('[data-line-rate]') || {}).value || '0').replace(/,/g, ''));
    if (isNaN(qty)) qty = 0;
    if (isNaN(rate)) rate = 0;
    var taxed = !!(row.querySelector('[data-vat-box]') || {}).checked;
    html += '<tr>';
    if (cols.item) html += '<td data-label="Item">' + escapeHtml(name || '-') + '</td>';
    if (cols.description) html += '<td data-label="Description">' + escapeHtml(desc).replace(/\n/g, '<br>') + '</td>';
    if (cols.qty) html += '<td class="center mono" data-label="Qty">' + escapeHtml(String(qty || '')) + '</td>';
    if (cols.rate) html += '<td class="right mono" data-label="Unit price">' + escapeHtml(formatDeskMoney(rate, cur)) + '</td>';
    if (cols.total) html += '<td class="right mono" data-label="Total Amt">' + escapeHtml(formatDeskMoney(Math.round(qty * rate * 100) / 100, cur)) + '</td>';
    if (cols.vat) html += '<td class="center" data-label="VAT">' + (taxed ? 'Y' : 'N') + '</td>';
    html += '</tr>';
  });
  if (!shown) {
    html = '<tr class="lines-preview-empty"><td colspan="' + previewColCount(cols) + '" class="muted">Add an item above to preview the document table.</td></tr>';
  }
  body.innerHTML = html;
  }
  updateDocRunningTotals();
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
  if (e.target.matches('[data-line-total]')) updateLineTotal(row, true);
  if (e.target.matches('input[name^="item_name"], textarea[name^="item_desc"], [data-line-qty], [data-line-rate], [data-line-total]')) {
    refreshLinesPreview();
  }
});

document.addEventListener('input', function (e) {
  if (e.target.matches('#allocated_amount, #currency, [name="currency"]')) refreshLinesPreview();
});
document.addEventListener('change', function (e) {
  if (e.target.matches('#related_id, #currency, [name="currency"]')) refreshLinesPreview();
});

document.addEventListener('change', function (e) {
  if (!e.target.matches('[data-vat-box]')) return;
  var yn = e.target.closest('label') && e.target.closest('label').querySelector('[data-vat-yn]');
  if (yn) yn.textContent = e.target.checked ? 'Y' : 'N';
  refreshLinesPreview();
});

if (document.querySelector('[data-lines-preview-body], [data-doc-sum]')) {
  refreshLinesPreview();
}

document.querySelectorAll('[data-letter-templates]').forEach(function (form) {
  var subject = form.querySelector('#subject');
  var body = form.querySelector('#body');
  var signBox = form.querySelector('[data-sign-box]');
  var signHint = form.querySelector('[data-sign-hint]');
  function applySign(needsSign) {
    if (signBox) signBox.hidden = !needsSign;
    if (signHint) signHint.hidden = !!needsSign;
    var cb = signBox && signBox.querySelector('input[name="add_signature"]');
    if (cb && !cb.disabled) cb.checked = !!needsSign;
  }
  form.querySelectorAll('input[name="letter_template"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var card = radio.closest('.template-card');
      if (!card) return;
      if (subject) subject.value = card.getAttribute('data-subject') || '';
      setRichValue(form.querySelector('[data-rich-editor]'), card.getAttribute('data-body') || '', body);
      applySign(card.getAttribute('data-sign') === '1');
    });
  });
});

document.addEventListener('click', function (e) {
  var a = e.target.closest('[data-letter-docx]');
  if (!a) return;
  var form = document.querySelector('[data-letter-templates]');
  if (!form) return;
  e.preventDefault();
  var post = document.createElement('form');
  post.method = 'post';
  post.action = (a.getAttribute('href') || '').split('?')[0];
  function add(name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    post.appendChild(input);
  }
  var csrf = form.querySelector('[name="csrf"]');
  if (csrf) add('csrf', csrf.value);
  var id = form.querySelector('[name="document_id"]');
  if (id && id.value) add('id', id.value);
  var tpl = form.querySelector('input[name="letter_template"]:checked');
  add('template', tpl ? tpl.value : 'none');
  add('subject', subjectValue(form));
  add('body', bodyValue(form));
  var date = form.querySelector('#date');
  if (date && date.value) add('date', date.value);
  var sig = form.querySelector('[data-sign-box] input[name="add_signature"]');
  add('add_signature', sig && !sig.closest('[hidden]') && sig.checked ? '1' : '0');
  var party = form.querySelector('#party_id');
  if (party) add('party_id', party.value);
  document.body.appendChild(post);
  post.submit();
});

function subjectValue(form) {
  var el = form.querySelector('#subject');
  return el ? el.value : '';
}
function bodyValue(form) {
  var wrap = form.querySelector('[data-rich-editor]');
  syncRichEditor(wrap);
  var el = form.querySelector('#body');
  return el ? el.value : '';
}

function textToRichHtml(s) {
  s = String(s || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
  if (!s) return '';
  if (/<\/?[a-z][\s\S]*>/i.test(s)) return s;
  return s.split(/\n{2,}/).map(function (p) {
    return '<p>' + p
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>') + '</p>';
  }).join('');
}

function syncRichEditor(wrap) {
  if (!wrap) return;
  var surface = wrap.querySelector('.rich-surface');
  var ta = wrap.querySelector('textarea');
  if (!surface || !ta) return;
  ta.value = surface.innerHTML;
}

function setRichValue(wrap, value, taFallback) {
  var html = textToRichHtml(value);
  if (wrap) {
    var surface = wrap.querySelector('.rich-surface');
    var ta = wrap.querySelector('textarea');
    if (surface) surface.innerHTML = html;
    if (ta) ta.value = html;
    return;
  }
  if (taFallback) taFallback.value = value || '';
}

function richPlain(wrap) {
  if (!wrap) return '';
  var surface = wrap.querySelector('.rich-surface');
  if (!surface) return '';
  var t = (surface.innerText || surface.textContent || '').replace(/\u00a0/g, ' ').trim();
  return t;
}

document.querySelectorAll('[data-rich-editor]').forEach(function (wrap) {
  var surface = wrap.querySelector('.rich-surface');
  var form = wrap.closest('form');
  if (!surface) return;
  wrap.querySelectorAll('[data-cmd]').forEach(function (btn) {
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      surface.focus();
      try { document.execCommand(btn.getAttribute('data-cmd'), false, null); } catch (err) {}
      syncRichEditor(wrap);
    });
  });
  surface.addEventListener('input', function () { syncRichEditor(wrap); });
  surface.addEventListener('blur', function () { syncRichEditor(wrap); });
  if (form) {
    form.addEventListener('submit', function (e) {
      syncRichEditor(wrap);
      if (wrap.hasAttribute('data-rich-required') && !richPlain(wrap)) {
        e.preventDefault();
        surface.focus();
      }
    });
  }
});

document.querySelectorAll('form[data-brand-form]').forEach(function (form) {
  form.addEventListener('submit', function () {
    var logo = form.querySelector('input[name="logo"]');
    if (logo && (!logo.files || !logo.files.length)) {
      logo.disabled = true;
    }
  });
});

document.querySelectorAll('[data-signature-pad]').forEach(function (root) {
  var canvas = root.querySelector('[data-sig-canvas]');
  var preview = root.querySelector('[data-sig-preview]');
  var status = root.querySelector('[data-sig-status]');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var drawing = false;
  var last = null;
  var inked = false;
  function sizeCanvas(force) {
    if (canvas.hidden && !force) return;
    if (inked && !force) return;
    var ratio = window.devicePixelRatio || 1;
    var w = canvas.clientWidth || 560;
    var h = canvas.clientHeight || 180;
    canvas.width = Math.round(w * ratio);
    canvas.height = Math.round(h * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111';
    if (force) inked = false;
  }
  sizeCanvas(true);
  window.addEventListener('resize', function () { sizeCanvas(false); });
  function pos(ev) {
    var r = canvas.getBoundingClientRect();
    var pt = ev.touches ? ev.touches[0] : ev;
    return { x: pt.clientX - r.left, y: pt.clientY - r.top };
  }
  function start(ev) {
    ev.preventDefault();
    drawing = true;
    last = pos(ev);
  }
  function move(ev) {
    if (!drawing) return;
    ev.preventDefault();
    var p = pos(ev);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    inked = true;
  }
  function end() { drawing = false; }
  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointerleave', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);
  function csrf() {
    var field = document.querySelector('input[name="csrf"]');
    return field ? field.value : '';
  }
  var postUrl = root.getAttribute('data-sig-url') || 'settings.php';
  function postAction(action, extra) {
    var data = new FormData();
    data.append('csrf', csrf());
    data.append('action', action);
    data.append('ajax', '1');
    Object.keys(extra || {}).forEach(function (k) { data.append(k, extra[k]); });
    return fetch(postUrl, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (res) {
      return res.text().then(function (text) {
        var json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (!json || !json.ok) {
          throw new Error((json && json.error) || 'Could not save the signature.');
        }
        return json;
      });
    });
  }
  function setStatus(msg) { if (status) status.textContent = msg || ''; }
  var cancel = root.querySelector('[data-sig-cancel]');
  var retake = root.querySelector('[data-sig-retake]');
  var remove = root.querySelector('[data-sig-remove]');
  var approve = root.querySelector('[data-sig-approve]');
  if (cancel) {
    cancel.addEventListener('click', function () {
      sizeCanvas(true);
      setStatus('Pad cleared.');
    });
  }
  function showPad() {
    if (preview) preview.hidden = true;
    canvas.hidden = false;
    sizeCanvas(true);
  }
  if (retake) {
    retake.addEventListener('click', function () {
      showPad();
      setStatus('Draw a new signature, then approve. The saved mark stays until you approve or remove it.');
    });
  }
  if (remove) {
    remove.addEventListener('click', function () {
      postAction('clear_signature').then(function () {
        if (preview) {
          preview.hidden = true;
          preview.querySelectorAll('img').forEach(function (img) { img.remove(); });
        }
        showPad();
        setStatus('Saved signature removed.');
      }).catch(function (err) { setStatus(err.message); });
    });
  }
  if (approve) {
    approve.addEventListener('click', function () {
      var blank = document.createElement('canvas');
      blank.width = canvas.width;
      blank.height = canvas.height;
      if (canvas.toDataURL() === blank.toDataURL()) {
        setStatus('Draw the signature first.');
        return;
      }
      postAction('save_signature', { signature_data: canvas.toDataURL('image/png') }).then(function (json) {
        if (preview) {
          var img = preview.querySelector('img') || document.createElement('img');
          img.alt = 'Approved signature';
          img.src = json.url;
          if (!img.parentNode) preview.insertBefore(img, preview.firstChild);
          preview.hidden = false;
        }
        canvas.hidden = true;
        setStatus('Signature approved.');
      }).catch(function (err) { setStatus(err.message); });
    });
  }
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
    if (party && opt.getAttribute('data-party')) {
      party.value = opt.getAttribute('data-party');
      party.dispatchEvent(new Event('change', { bubbles: true }));
    }
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
      if (key === 'label' || key === 'hint') return;
      var el = box.querySelector('[data-mail-field="' + key + '"]');
      if (el) el.value = p[key];
    });
    var help = box.querySelector('[data-mail-help]');
    if (help && p.hint) help.textContent = p.hint;
  });
})();

(function () {
  var root = document.querySelector('[data-clock]');
  if (!root) return;
  var dateEl = root.querySelector('[data-clock-date]');
  if (!dateEl) return;
  function partsOf(now) {
    try {
      var zone = root.getAttribute('data-timezone') || 'Africa/Kampala';
      var fmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: zone,
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
  var book = {};
  try {
    book = JSON.parse(form.getAttribute('data-party-book') || '{}');
  } catch (e) {
    return;
  }
  var combo = form.querySelector('[data-client-combo]');
  var search = form.querySelector('[data-client-search]');
  var panel = form.querySelector('[data-client-panel]');
  var list = form.querySelector('[data-client-list]');
  var party = form.querySelector('#party_id');
  if (!search || !party) return;

  function fillFromId(id) {
    var row = book[id] || book[String(id)] || {};
    var map = {
      to_name: row.name || search.value,
      to_phone: row.phone || '',
      to_phone2: row.phone2 || '',
      to_email: row.email || '',
      to_address: row.address || '',
      to_contact: row.contact || '',
      to_tin: row.tin || '',
      to_city: row.city || '',
      to_country: row.country || '',
      to_entity: row.entity || ''
    };
    Object.keys(map).forEach(function (name) {
      form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        if (el && map[name] !== '') el.value = map[name];
        else if (el && name !== 'to_name') el.value = map[name];
      });
    });
    if (map.to_entity) applyToEntityProfile();
    var extras = row.extras || {};
    form.querySelectorAll('[data-to-extra]').forEach(function (el) {
      var key = el.getAttribute('data-to-extra');
      var part = el.getAttribute('data-to-extra-part');
      var val = extras[key];
      if (part) {
        el.value = (val && typeof val === 'object') ? (val[part] || '') : '';
        return;
      }
      if (val == null || typeof val === 'object') {
        el.value = '';
        return;
      }
      el.value = String(val);
    });
    party.value = id ? String(id) : '';
  }

  function entries() {
    return Object.keys(book).map(function (id) {
      return { id: id, row: book[id] || {} };
    }).filter(function (item) {
      return (item.row.name || '').trim() !== '';
    });
  }

  function renderList(q) {
    if (!list) return;
    q = String(q || '').trim().toLowerCase();
    var items = entries().filter(function (item) {
      if (!q) return true;
      var blob = [item.row.name, item.row.phone, item.row.email, item.row.address].join(' ').toLowerCase();
      return blob.indexOf(q) !== -1;
    }).slice(0, 40);
    if (!items.length) {
      list.innerHTML = '<li class="client-combo-empty">No saved match. Keep typing to add a new client</li>';
    } else {
      list.innerHTML = items.map(function (item) {
        var extra = [item.row.phone, item.row.email].filter(Boolean).join(', ');
        return '<li><button type="button" data-client-pick="' + item.id + '"><strong>' +
          String(item.row.name || '').replace(/</g, '&lt;') + '</strong>' +
          (extra ? '<span>' + String(extra).replace(/</g, '&lt;') + '</span>' : '') +
          '</button></li>';
      }).join('');
    }
    if (panel) panel.hidden = false;
    list.scrollTop = 0;
  }

  function matchExact(name) {
    name = String(name || '').trim().toLowerCase();
    if (!name) return '';
    var found = '';
    entries().forEach(function (item) {
      if ((item.row.name || '').trim().toLowerCase() === name) found = item.id;
    });
    return found;
  }

  search.addEventListener('focus', function () {
    renderList(search.value);
  });
  search.addEventListener('input', function () {
    var id = matchExact(search.value);
    party.value = id;
    if (!id) {
      // typing a new name — don't wipe address until they pick someone
    }
    renderList(search.value);
  });
  if (combo) {
    combo.addEventListener('click', function (e) {
      var sc = e.target.closest('[data-client-scroll]');
      if (sc) {
        e.preventDefault();
        var dir = parseInt(sc.getAttribute('data-client-scroll'), 10) || 0;
        if (list) list.scrollTop += dir * 80;
        return;
      }
      var pick = e.target.closest('[data-client-pick]');
      if (!pick) return;
      e.preventDefault();
      fillFromId(pick.getAttribute('data-client-pick'));
      if (panel) panel.hidden = true;
    });
  }
  party.addEventListener('change', function () {
    if (party.value) fillFromId(party.value);
  });
  document.addEventListener('click', function (e) {
    if (!combo) return;
    if (!combo.contains(e.target)) {
      if (panel) panel.hidden = true;
    }
  });
})();

(function () {
  var plan = document.querySelector('[data-planner-plan]');
  var seats = document.querySelector('[data-user-limit]');
  if (!plan || !seats) return;
  function maxForPlan() {
    var opt = plan.options[plan.selectedIndex];
    var n = opt ? parseInt(opt.getAttribute('data-max-users') || '0', 10) : 0;
    if (n > 0) return n;
    if (plan.value === 'office') return 4;
    if (plan.value === 'starter') return 2;
    return 3;
  }
  function syncSeats() {
    var max = maxForPlan();
    Array.prototype.forEach.call(seats.options, function (opt) {
      var val = parseInt(opt.value, 10);
      opt.hidden = val > max;
      opt.disabled = val > max;
    });
    if (parseInt(seats.value, 10) > max) seats.value = String(max);
  }
  plan.addEventListener('change', syncSeats);
  syncSeats();
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

(function () {
  function prettyDate(iso) {
    if (!iso) return 'Choose a date';
    var parts = String(iso).split('-');
    if (parts.length !== 3) return iso;
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var d = parseInt(parts[2], 10);
    var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var suf = 'th';
    if (d % 10 === 1 && d % 100 !== 11) suf = 'st';
    else if (d % 10 === 2 && d % 100 !== 12) suf = 'nd';
    else if (d % 10 === 3 && d % 100 !== 13) suf = 'rd';
    return months[m] + ' ' + d + suf + ', ' + y;
  }
  document.querySelectorAll('.doc-date-control').forEach(function (wrap) {
    var input = wrap.querySelector('[data-date-input]');
    var out = wrap.querySelector('[data-date-pretty]');
    if (!input || !out) return;
    function sync() {
      out.textContent = prettyDate(input.value);
    }
    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    sync();
  });
})();

(function () {
  var root = document.querySelector('[data-desk-calc]');
  if (!root) return;
  var pad = root.querySelector('[data-calc-pad]');
  var screen = root.querySelector('[data-calc-screen]');
  var histEl = root.querySelector('[data-calc-history]');
  if (!pad || !screen) return;
  var cur = '0';
  var acc = null;
  var op = null;
  var fresh = true;
  var history = [];
  function shown(n) {
    if (!isFinite(n)) return 'Error';
    var s = String(Math.round(n * 1e10) / 1e10);
    return s;
  }
  function render() {
    screen.textContent = cur;
  }
  function compute() {
    var b = parseFloat(cur);
    if (acc === null || !op || isNaN(b)) return b;
    if (op === '+') return acc + b;
    if (op === '-') return acc - b;
    if (op === '*') return acc * b;
    if (op === '/') return b === 0 ? NaN : acc / b;
    return b;
  }
  function pushHistory(expr) {
    history.unshift(expr);
    history = history.slice(0, 8);
    if (!histEl) return;
    histEl.innerHTML = history.map(function (row) {
      return '<li>' + row.replace(/</g, '&lt;') + '</li>';
    }).join('');
  }
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-calc]');
    if (!btn) return;
    e.preventDefault();
    var act = btn.getAttribute('data-calc');
    if (act === 'digit') {
      var d = btn.getAttribute('data-digit') || '';
      cur = fresh || cur === '0' ? d : cur + d;
      fresh = false;
      render();
      return;
    }
    if (act === 'dot') {
      if (fresh) {
        cur = '0.';
        fresh = false;
      } else if (cur.indexOf('.') === -1) {
        cur += '.';
      }
      render();
      return;
    }
    if (act === 'clear') {
      cur = '0';
      acc = null;
      op = null;
      fresh = true;
      render();
      return;
    }
    if (act === 'back') {
      if (fresh) return;
      cur = cur.length <= 1 ? '0' : cur.slice(0, -1);
      if (cur === '-') cur = '0';
      render();
      return;
    }
    if (act === 'op') {
      var next = btn.getAttribute('data-op');
      if (!fresh && acc !== null && op) {
        var res = compute();
        pushHistory(shown(acc) + ' ' + op + ' ' + cur + ' = ' + shown(res));
        acc = res;
        cur = shown(res);
      } else {
        acc = parseFloat(cur);
      }
      op = next;
      fresh = true;
      render();
      return;
    }
    if (act === 'eq') {
      if (acc === null || !op) return;
      var result = compute();
      pushHistory(shown(acc) + ' ' + op + ' ' + cur + ' = ' + shown(result));
      cur = shown(result);
      acc = null;
      op = null;
      fresh = true;
      render();
      return;
    }
    if (act === 'copy') {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(cur);
      }
      return;
    }
    if (act === 'history' && histEl) {
      histEl.hidden = !histEl.hidden;
    }
  });
  function setOpen(open) {
    pad.hidden = !open;
    document.body.classList.toggle('calc-open', open);
    document.querySelectorAll('[data-calc-toggle]').forEach(function (el) {
      el.setAttribute('aria-label', pad.hidden ? 'Open calculator' : 'Close calculator');
      el.classList.toggle('is-on', !pad.hidden);
    });
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-calc-toggle]');
    if (!t) return;
    e.preventDefault();
    var panel = document.querySelector('[data-quick-panel]');
    var scrim = document.querySelector('[data-quick-scrim]');
    if (panel) panel.hidden = true;
    if (scrim) scrim.hidden = true;
    document.body.classList.remove('quick-open');
    setOpen(pad.hidden);
  });
})();

(function () {
  var table = document.querySelector('#lines[data-stock-catalog]');
  var raw = document.getElementById('desk-stock-catalog');
  if (!table || !raw) return;
  var cat = [];
  try { cat = JSON.parse(raw.textContent || '[]'); } catch (e) { return; }
  var box = document.createElement('div');
  box.className = 'pos-suggest stock-line-suggest';
  box.hidden = true;
  document.body.appendChild(box);
  var currentInp = null;
  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }
  function place(inp) {
    var r = inp.getBoundingClientRect();
    box.style.position = 'fixed';
    box.style.left = Math.max(8, r.left) + 'px';
    box.style.top = (r.bottom + 4) + 'px';
    box.style.width = Math.max(r.width, 240) + 'px';
    box.style.maxWidth = 'min(92vw, 360px)';
    box.style.zIndex = '80';
  }
  function fill(inp, p) {
    var row = inp.closest('tr');
    if (!row) return;
    inp.value = p.name;
    var hid = row.querySelector('input[name^="item_stock_id"]');
    if (hid) hid.value = p.id;
    var desc = row.querySelector('textarea[name^="item_desc"]');
    if (desc && !desc.value) desc.value = p.description || '';
    var rate = row.querySelector('[data-line-rate]');
    if (rate) rate.value = p.sell;
    var tax = row.querySelector('[data-vat-box]');
    if (tax) {
      tax.checked = !!p.taxed;
      var yn = row.querySelector('[data-vat-yn]');
      if (yn) yn.textContent = p.taxed ? 'Y' : 'N';
    }
    box.hidden = true;
    if (rate) rate.dispatchEvent(new Event('input', { bubbles: true }));
    else if (typeof refreshLinesPreview === 'function') refreshLinesPreview();
  }
  function showFor(inp) {
    currentInp = inp;
    var row = inp.closest('tr');
    var hid = row && row.querySelector('input[name^="item_stock_id"]');
    if (hid) hid.value = '';
    var typed = inp.value.trim();
    var s = typed.toLowerCase();
    if (!s) { box.hidden = true; return; }
    var list = cat.filter(function (p) {
      return String(p.name).toLowerCase().indexOf(s) !== -1 || String(p.sku).toLowerCase().indexOf(s) !== -1;
    }).slice(0, 8);
    var html = list.map(function (p) {
      return '<button type="button" class="pos-opt" data-id="' + p.id + '"><strong>' + esc(p.name) + '</strong><span>' + esc(p.sku || '') + (p.qty != null ? ' · ' + p.qty + ' left' : '') + ' · ' + esc(p.sell) + '</span></button>';
    }).join('');
    var exact = list.some(function (p) { return String(p.name).toLowerCase() === s; });
    if (!exact) {
      html += '<button type="button" class="pos-opt" data-keep="1"><strong>Keep "' + esc(typed) + '"</strong><span>Not in stock. Save this name as typed.</span></button>';
    }
    place(inp);
    box.innerHTML = html;
    box.hidden = false;
  }
  table.addEventListener('input', function (e) {
    if (!e.target.matches('input[name^="item_name"]')) return;
    showFor(e.target);
  });
  table.addEventListener('focusin', function (e) {
    if (e.target.matches('input[name^="item_name"]') && e.target.value.trim()) showFor(e.target);
  });
  box.addEventListener('mousedown', function (e) {
    e.preventDefault();
  });
  box.addEventListener('click', function (e) {
    var keep = e.target.closest('[data-keep]');
    if (keep) {
      box.hidden = true;
      return;
    }
    var btn = e.target.closest('[data-id]');
    if (!btn || !currentInp) return;
    var p = cat.find(function (x) { return String(x.id) === String(btn.getAttribute('data-id')); });
    if (p) fill(currentInp, p);
  });
  document.addEventListener('click', function (e) {
    if (!box.contains(e.target) && !(currentInp && currentInp.contains(e.target))) box.hidden = true;
  });
  var wrap = table.closest('.lines-wrap');
  if (wrap) {
    wrap.addEventListener('scroll', function () {
      if (currentInp && !box.hidden) place(currentInp);
    });
  }
})();

(function () {
  function closeTutLightbox() {
    var lb = document.querySelector('.tut-lightbox');
    if (!lb) return;
    lb.remove();
    document.body.classList.remove('tut-lightbox-open');
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest ? e.target.closest('[data-tut-open]') : null;
    if (open) {
      e.preventDefault();
      var img = open.querySelector('img');
      if (!img) return;
      closeTutLightbox();
      var box = document.createElement('div');
      box.className = 'tut-lightbox';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', img.getAttribute('alt') || 'Screenshot');
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'tut-lightbox-close';
      close.setAttribute('data-tut-close', '');
      close.setAttribute('aria-label', 'Close');
      close.textContent = '\u00d7';
      var big = document.createElement('img');
      big.src = img.currentSrc || img.src;
      big.alt = img.alt || '';
      box.appendChild(close);
      box.appendChild(big);
      document.body.appendChild(box);
      document.body.classList.add('tut-lightbox-open');
      close.focus();
      return;
    }
    if (e.target.classList && e.target.classList.contains('tut-lightbox')) {
      closeTutLightbox();
      return;
    }
    if (e.target.closest && e.target.closest('[data-tut-close]')) {
      e.preventDefault();
      closeTutLightbox();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTutLightbox();
  });
})();

function toOrderMoveBtns() {
  return '<div class="to-order-move">' +
    '<button class="btn ghost sm to-order-btn" type="button" data-to-move="-1" aria-label="Move up">' +
    '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></button>' +
    '<button class="btn ghost sm to-order-btn" type="button" data-to-move="1" aria-label="Move down">' +
    '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>' +
    '</div>';
}

function toOrderRemoveBtn() {
  return '<button class="btn ghost sm to-order-remove" type="button" data-to-remove aria-label="Remove">' +
    '<svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>';
}

function toProfileName(profile, field) {
  return field + '[' + profile + '][]';
}

function buildToOrderExtraRow(profile) {
  profile = profile || 'people';
  var row = document.createElement('div');
  row.className = 'to-order-row';
  row.setAttribute('data-to-order-row', '');
  row.setAttribute('data-to-kind', 'extra');
  row.innerHTML = toOrderMoveBtns() +
    '<input type="hidden" name="' + toProfileName(profile, 'to_kind') + '" value="extra">' +
    '<input type="hidden" name="' + toProfileName(profile, 'to_key') + '" value="">' +
    '<input name="' + toProfileName(profile, 'to_label') + '" value="" placeholder="e.g. Vehicle no" aria-label="Field label">' +
    '<select name="' + toProfileName(profile, 'to_type') + '" aria-label="Field type">' +
    '<option value="text">Short text</option>' +
    '<option value="tel">Phone</option>' +
    '<option value="date">Date</option>' +
    '<option value="number">Number</option>' +
    '<option value="textarea">Long text</option>' +
    '<option value="period">Period (from–to)</option>' +
    '</select>' +
    toOrderRemoveBtn();
  return row;
}

function buildToOrderCoreRow(key, label, profile) {
  profile = profile || 'people';
  var row = document.createElement('div');
  row.className = 'to-order-row';
  row.setAttribute('data-to-order-row', '');
  row.setAttribute('data-to-kind', 'core');
  var esc = function (s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  };
  row.innerHTML = toOrderMoveBtns() +
    '<input type="hidden" name="' + toProfileName(profile, 'to_kind') + '" value="core">' +
    '<input type="hidden" name="' + toProfileName(profile, 'to_key') + '" value="' + esc(key) + '">' +
    '<input type="hidden" name="' + toProfileName(profile, 'to_label') + '" value="' + esc(label) + '">' +
    '<input type="hidden" name="' + toProfileName(profile, 'to_type') + '" value="text">' +
    '<span class="to-order-label">' + esc(label) + '</span>' +
    '<span class="to-order-kind">Usual</span>' +
    toOrderRemoveBtn();
  return row;
}

function syncToCoreSelect(panel) {
  var root = panel || document;
  var used = {};
  root.querySelectorAll('[data-to-order-row][data-to-kind="core"] input[name*="to_key"]').forEach(function (el) {
    used[el.value] = true;
  });
  var sel = root.querySelector('[data-to-add-core]');
  if (!sel) return;
  Array.prototype.forEach.call(sel.options, function (opt) {
    if (!opt.value) return;
    opt.disabled = !!used[opt.value];
  });
}

document.querySelectorAll('[data-to-add-core]').forEach(function (sel) {
  sel.addEventListener('change', function () {
    var key = sel.value;
    if (!key) return;
    var opt = sel.options[sel.selectedIndex];
    var panel = sel.closest('[data-to-tab-panel]');
    var box = panel && panel.querySelector('[data-to-order]');
    var profile = (box && box.getAttribute('data-to-order')) || 'people';
    if (box) box.appendChild(buildToOrderCoreRow(key, opt.textContent || key, profile));
    sel.value = '';
    syncToCoreSelect(panel);
  });
});

(function () {
  var tabs = document.querySelector('[data-to-field-tabs]');
  if (!tabs) return;
  tabs.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-to-tab]');
    if (!btn) return;
    var key = btn.getAttribute('data-to-tab');
    tabs.querySelectorAll('[data-to-tab]').forEach(function (b) {
      b.classList.toggle('is-on', b === btn);
    });
    document.querySelectorAll('[data-to-tab-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-to-tab-panel') !== key;
    });
  });
})();

function applyToEntityProfile() {
  var sel = document.querySelector('[data-to-entity]');
  if (!sel) return;
  var entity = sel.value || 'person';
  var profile = entity === 'organisation' ? 'organisations' : (entity === 'other' ? 'other' : 'people');
  var labels = { person: 'Client name', organisation: 'Company name', other: 'Name' };
  var nameLab = document.querySelector('[data-to-name-label]');
  if (nameLab) nameLab.textContent = labels[entity] || 'Name';
  document.querySelectorAll('[data-to-profile]').forEach(function (box) {
    var on = box.getAttribute('data-to-profile') === profile;
    box.hidden = !on;
    box.querySelectorAll('input, select, textarea').forEach(function (el) {
      el.disabled = !on;
    });
  });
}

document.addEventListener('change', function (e) {
  if (e.target && e.target.matches('[data-to-entity]')) applyToEntityProfile();
});
if (document.querySelector('[data-to-entity], [data-to-profile]')) {
  applyToEntityProfile();
}

(function () {
  var wrap = document.querySelector('[data-top-search]');
  if (!wrap) return;
  var form = wrap.querySelector('[data-search-form]');
  var input = wrap.querySelector('[data-search-input]');
  var live = wrap.querySelector('[data-search-live]');
  var toggle = wrap.querySelector('[data-search-toggle]');
  var closeBtn = wrap.querySelector('[data-search-close]');
  var timer = 0;
  function apiUrl() {
    var action = (form && form.getAttribute('action')) || 'search.php';
    return action.replace(/search\.php.*$/, 'search_api.php');
  }
  function openSearch() {
    wrap.classList.add('is-open');
    if (input) {
      input.focus();
      input.select();
    }
  }
  function closeSearch() {
    wrap.classList.remove('is-open');
    if (live) {
      live.hidden = true;
      live.innerHTML = '';
    }
  }
  if (toggle) {
    toggle.addEventListener('click', function () {
      if (wrap.classList.contains('is-open')) closeSearch();
      else openSearch();
    });
  }
  if (closeBtn) closeBtn.addEventListener('click', closeSearch);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSearch();
  });
  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) {
      if (live) live.hidden = true;
    }
  });
  function renderHits(data) {
    if (!live) return;
    var hits = (data && data.hits) || [];
    if (!hits.length) {
      live.innerHTML = '<p class="muted">No matches. Press Enter to search the full list.</p>';
      live.hidden = false;
      return;
    }
    var html = '<ul>';
    hits.forEach(function (hit) {
      var badge = hit.badge ? '<em>' + String(hit.badge).replace(/</g, '') + '</em>' : '';
      html += '<li><a href="' + String(hit.href || '#').replace(/"/g, '') + '"><strong>' +
        String(hit.title || '').replace(/</g, '&lt;') + '</strong><span>' +
        String(hit.subtitle || '').replace(/</g, '&lt;') + '</span>' + badge + '</a></li>';
    });
    html += '</ul>';
    live.innerHTML = html;
    live.hidden = false;
  }
  if (input) {
    input.addEventListener('input', function () {
      var q = input.value.trim();
      window.clearTimeout(timer);
      if (q.length < 2) {
        if (live) {
          live.hidden = true;
          live.innerHTML = '';
        }
        return;
      }
      timer = window.setTimeout(function () {
        fetch(apiUrl() + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(renderHits)
          .catch(function () {});
      }, 180);
    });
    input.addEventListener('focus', function () {
      wrap.classList.add('is-open');
      if (live && live.innerHTML) live.hidden = false;
    });
  }
})();

