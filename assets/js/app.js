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
