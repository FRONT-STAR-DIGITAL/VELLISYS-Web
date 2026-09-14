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
    html += '<td data-label="Item">' + escapeHtml(name || '-') + '</td>';
    html += '<td data-label="Description">' + escapeHtml(desc).replace(/\n/g, '<br>') + '</td>';
    html += '<td class="center mono" data-label="Qty">' + escapeHtml(String(qty || '')) + '</td>';
    if (!delivery) {
      html += '<td class="right mono" data-label="Unit price">' + escapeHtml(formatPreviewMoney(rate)) + '</td>';
      html += '<td class="right mono" data-label="Total Amt">' + escapeHtml(formatPreviewMoney(Math.round(qty * rate * 100) / 100)) + '</td>';
      html += '<td class="center" data-label="VAT">' + (taxed ? 'Y' : 'N') + '</td>';
    }
    html += '</tr>';
  });
  if (!shown) {
    html = '<tr class="lines-preview-empty"><td colspan="' + (delivery ? '3' : '6') + '" class="muted">Add an item above to preview the document table.</td></tr>';
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

document.querySelectorAll('[data-signature-pad]').forEach(function (root) {
  var canvas = root.querySelector('[data-sig-canvas]');
  var preview = root.querySelector('[data-sig-preview]');
  var status = root.querySelector('[data-sig-status]');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var drawing = false;
  var last = null;
  function sizeCanvas() {
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
  }
  sizeCanvas();
  window.addEventListener('resize', sizeCanvas);
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
  var approve = root.querySelector('[data-sig-approve]');
  if (cancel) {
    cancel.addEventListener('click', function () {
      sizeCanvas();
      setStatus('Pad cleared.');
    });
  }
  if (retake) {
    retake.addEventListener('click', function () {
      postAction('clear_signature').then(function () {
        if (preview) {
          preview.hidden = true;
          preview.querySelectorAll('img').forEach(function (img) { img.remove(); });
        }
        canvas.hidden = false;
        sizeCanvas();
        setStatus('Draw a new signature, then approve.');
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
      to_email: row.email || '',
      to_address: row.address || ''
    };
    Object.keys(map).forEach(function (name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (el) el.value = map[name];
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
  var toggle = root.querySelector('[data-calc-toggle]');
  if (!pad || !screen || !toggle) return;
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
  toggle.addEventListener('click', function () {
    pad.hidden = !pad.hidden;
    toggle.setAttribute('aria-label', pad.hidden ? 'Open calculator' : 'Close calculator');
  });
})();
