(function () {
  function displayMode(mode) {
    try {
      return window.matchMedia('(display-mode: ' + mode + ')').matches;
    } catch (e) {
      return false;
    }
  }

  function isInstalledApp() {
    if (window.navigator.standalone === true) {
      return true;
    }
    return displayMode('standalone')
      || displayMode('fullscreen')
      || displayMode('minimal-ui')
      || displayMode('window-controls-overlay');
  }

  var html = document.documentElement;
  var installed = isInstalledApp();
  if (installed) {
    html.classList.add('is-pwa');
    document.cookie = 'vellisys_app=1;path=/;max-age=31536000;samesite=lax';
    var home = html.getAttribute('data-pwa-login') || 'login.php';
    document.querySelectorAll('[data-pwa-home]').forEach(function (el) {
      var dest = el.getAttribute('data-pwa-home') || home;
      el.setAttribute('href', dest);
    });
    document.querySelectorAll('[data-open-website]').forEach(function (el) {
      el.setAttribute('target', '_blank');
      el.setAttribute('rel', 'noopener noreferrer');
      try {
        el.setAttribute('href', new URL(el.getAttribute('href') || '/#pricing', location.origin).href);
      } catch (err) {}
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
  if (installed) {
    wrap.hidden = true;
    wrap.removeAttribute('open');
    return;
  }

  var btn = wrap.querySelector('[data-pwa-install-btn]');
  var ios = wrap.querySelector('[data-pwa-ios]');
  var ready = wrap.querySelector('[data-pwa-ready]');
  var fallback = wrap.querySelector('[data-pwa-fallback]');
  var deferred = null;
  var ua = navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua);

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
      wrap.open = true;
      if (deferred) {
        deferred.prompt();
        deferred.userChoice.finally(function () {
          deferred = null;
        });
        return;
      }
      if (isIos) {
        if (ios) ios.hidden = false;
      } else if (fallback) {
        fallback.hidden = false;
      }
      try {
        wrap.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } catch (err) {
        wrap.scrollIntoView(true);
      }
    });
  }
})();
