(function () {
  function displayMode(mode) {
    try {
      return window.matchMedia('(display-mode: ' + mode + ')').matches;
    } catch (e) {
      return false;
    }
  }
  var installed = window.navigator.standalone === true
    || displayMode('standalone')
    || displayMode('fullscreen')
    || displayMode('minimal-ui')
    || displayMode('window-controls-overlay');
  if (!installed) {
    return;
  }
  document.documentElement.classList.add('is-pwa');
  var hash = (location.hash || '').toLowerCase();
  var wantSite = hash === '#pricing' || /(?:^|[?&])website=1(?:&|$)/.test(location.search || '');
  if (wantSite) {
    return;
  }
  var target = document.documentElement.getAttribute('data-pwa-login') || 'login.php';
  location.replace(target);
})();
