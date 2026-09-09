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
