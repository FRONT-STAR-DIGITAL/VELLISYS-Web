(function () {
  var html = document.documentElement;
  var standalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    window.navigator.standalone === true;

  if (standalone) {
    html.classList.add('is-pwa');
    var home = html.getAttribute('data-pwa-login') || 'login.php';
    document.querySelectorAll('[data-pwa-home]').forEach(function (el) {
      var dest = el.getAttribute('data-pwa-home') || home;
      el.setAttribute('href', dest);
    });
  }

  var swUrl = html.getAttribute('data-sw') || 'sw.js';
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(swUrl).then(function (reg) {
      if (reg && typeof reg.update === 'function') {
        reg.update();
      }
    }).catch(function () {});
  }

  var wrap = document.querySelector('[data-pwa-install]');
  if (!wrap) {
    return;
  }
  if (standalone) {
    wrap.hidden = true;
    return;
  }

  var btn = wrap.querySelector('[data-pwa-install-btn]');
  var ios = wrap.querySelector('[data-pwa-ios]');
  var ready = wrap.querySelector('[data-pwa-ready]');
  var fallback = wrap.querySelector('[data-pwa-fallback]');
  var deferred = null;
  var ua = navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua);
  var isIosSafari = isIos && /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua);

  if (isIos && ios) {
    ios.hidden = false;
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (btn) {
      btn.disabled = false;
    }
    if (ready) {
      ready.hidden = false;
    }
  });

  window.addEventListener('appinstalled', function () {
    deferred = null;
    wrap.hidden = true;
  });

  if (btn) {
    btn.addEventListener('click', function () {
      if (deferred) {
        deferred.prompt();
        deferred.userChoice.finally(function () {
          deferred = null;
        });
        return;
      }
      if (fallback) {
        fallback.hidden = false;
      }
      if (isIosSafari && ios) {
        ios.hidden = false;
      }
    });
  }
})();
