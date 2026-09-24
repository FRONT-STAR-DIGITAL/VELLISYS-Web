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

  function applyAppBadge(count) {
    var n = parseInt(count, 10);
    if (!isFinite(n) || n < 0) n = 0;
    try {
      if (n > 0 && typeof navigator.setAppBadge === 'function') {
        navigator.setAppBadge(n);
      } else if (typeof navigator.clearAppBadge === 'function') {
        navigator.clearAppBadge();
      }
    } catch (err) {}
  }

  if (html.hasAttribute('data-badge')) {
    applyAppBadge(html.getAttribute('data-badge'));
  } else {
    applyAppBadge(0);
  }

  var wrap = document.querySelector('[data-pwa-install]');
  var banner = document.querySelector('[data-pwa-install-banner]');
  if (!wrap && !banner) {
    return;
  }

  var deferred = null;
  var ua = navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua);
  var dismissKey = 'vellisys-install-banner-dismissed';

  function hideBanner(persist) {
    if (!banner) return;
    banner.classList.remove('is-in');
    window.setTimeout(function () {
      banner.hidden = true;
    }, 520);
    if (persist) {
      try {
        localStorage.setItem(dismissKey, String(Date.now()));
      } catch (err) {}
    }
  }

  function bannerDismissed() {
    try {
      var raw = localStorage.getItem(dismissKey);
      if (!raw) return false;
      var when = parseInt(raw, 10);
      if (!isFinite(when)) return true;
      // Stay dismissed for 14 days.
      return (Date.now() - when) < 14 * 24 * 60 * 60 * 1000;
    } catch (err) {
      return false;
    }
  }

  function showBannerGently() {
    if (!banner || installed || bannerDismissed()) {
      return;
    }
    banner.hidden = false;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        banner.classList.add('is-in');
      });
    });
  }

  function runInstall(btn) {
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.finally(function () {
        deferred = null;
      });
      return true;
    }
    var login = (btn && btn.getAttribute('data-pwa-install-login')) || html.getAttribute('data-pwa-login') || 'login.php';
    if (banner && btn && btn.closest('[data-pwa-install-banner]')) {
      try {
        location.href = login;
      } catch (err) {
        location.assign(login);
      }
      return true;
    }
    return false;
  }

  if (installed) {
    if (wrap) {
      wrap.hidden = true;
      wrap.removeAttribute('open');
    }
    if (banner) {
      banner.hidden = true;
    }
    return;
  }

  if (banner && !bannerDismissed()) {
    window.setTimeout(showBannerGently, 900);
  }

  if (wrap) {
    var btn = wrap.querySelector('[data-pwa-install-btn]');
    var ios = wrap.querySelector('[data-pwa-ios]');
    var ready = wrap.querySelector('[data-pwa-ready]');
    var fallback = wrap.querySelector('[data-pwa-fallback]');

    if (isIos && ios) {
      ios.hidden = false;
    }

    if (btn) {
      btn.addEventListener('click', function () {
        wrap.open = true;
        if (runInstall(btn)) {
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
  }

  if (banner) {
    var bannerBtn = banner.querySelector('[data-pwa-install-btn]');
    var dismiss = banner.querySelector('[data-pwa-install-dismiss]');
    if (bannerBtn) {
      bannerBtn.addEventListener('click', function () {
        runInstall(bannerBtn);
      });
    }
    if (dismiss) {
      dismiss.addEventListener('click', function () {
        hideBanner(true);
      });
    }
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (wrap) {
      var readyNote = wrap.querySelector('[data-pwa-ready]');
      var installBtn = wrap.querySelector('[data-pwa-install-btn]');
      if (installBtn) installBtn.disabled = false;
      if (readyNote) readyNote.hidden = false;
    }
    if (banner && !bannerDismissed() && !installed) {
      showBannerGently();
    }
  });

  window.addEventListener('appinstalled', function () {
    deferred = null;
    if (wrap) wrap.hidden = true;
    hideBanner(true);
    try {
      localStorage.setItem('vellisys-ask-push-install', '1');
      localStorage.setItem('vellisys-pwa-installed', '1');
      sessionStorage.removeItem('vellisys-push-asked');
    } catch (err) {}
  });
})();
