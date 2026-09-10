(function () {
  var standalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    window.navigator.standalone === true;
  if (!standalone) {
    return;
  }
  var target = document.documentElement.getAttribute('data-pwa-login') || 'login.php';
  location.replace(target);
})();
