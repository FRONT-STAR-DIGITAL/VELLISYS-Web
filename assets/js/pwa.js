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

  function storageFlag(key) {
    try {
      return localStorage.getItem(key) === '1';
    } catch (err) {
      return false;
    }
  }

  function setStorageFlag(key) {
    try {
      localStorage.setItem(key, '1');
    } catch (err) {}
    try {
      document.cookie = 'vellisys_app=1;path=/;max-age=31536000;samesite=lax';
    } catch (err2) {}
  }

  var html = document.documentElement;
  var installed = isInstalledApp() || storageFlag('vellisys-pwa-installed');
  if (isInstalledApp()) {
    html.classList.add('is-pwa');
    setStorageFlag('vellisys-pwa-installed');
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
  var installedKey = 'vellisys-pwa-installed';

  function hideBanner(persist) {
    if (!banner) return;
    banner.classList.remove('is-in');
    window.setTimeout(function () {
      banner.hidden = true;
    }, 520);
    if (persist) {
      try {
        localStorage.setItem(dismissKey, '1');
      } catch (err) {}
    }
  }

  function bannerDismissed() {
    try {
      return localStorage.getItem(dismissKey) === '1';
    } catch (err) {
      return false;
    }
  }

  function alreadyInstalled() {
    if (isInstalledApp() || storageFlag(installedKey)) {
      return true;
    }
    try {
      return /(?:^|;\s*)vellisys_app=1(?:;|$)/.test(document.cookie || '');
    } catch (err) {
      return false;
    }
  }

  function showBannerGently() {
    if (!banner || alreadyInstalled() || bannerDismissed()) {
      return;
    }
    // Only open when install can run now (Chrome/Edge deferred prompt), or on iOS.
    if (!deferred && !isIos) {
      return;
    }
    banner.hidden = false;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        banner.classList.add('is-in');
      });
    });
  }

  function markInstalled() {
    setStorageFlag(installedKey);
    try {
      localStorage.setItem(dismissKey, '1');
    } catch (err) {}
    hideBanner(true);
  }

  function runInstall(btn) {
    if (deferred) {
      var promptEvent = deferred;
      deferred = null;
      promptEvent.prompt();
      promptEvent.userChoice.then(function (choice) {
        if (choice && choice.outcome === 'accepted') {
          markInstalled();
        }
      }).catch(function () {});
      return true;
    }
    // No native prompt yet — on iOS keep the login install help; otherwise wait.
    if (banner && btn && btn.closest('[data-pwa-install-banner]')) {
      if (isIos) {
        var login = btn.getAttribute('data-pwa-install-login') || html.getAttribute('data-pwa-login') || 'login.php';
        try {
          location.href = login;
        } catch (err) {
          location.assign(login);
        }
        return true;
      }
      return false;
    }
    return false;
  }

  if (alreadyInstalled()) {
    if (wrap) {
      wrap.hidden = true;
      wrap.removeAttribute('open');
    }
    if (banner) {
      banner.hidden = true;
    }
    return;
  }

  // Detect an already-installed PWA while browsing the website.
  if (navigator.getInstalledRelatedApps) {
    try {
      navigator.getInstalledRelatedApps().then(function (apps) {
        if (apps && apps.length) {
          markInstalled();
        }
      }).catch(function () {});
    } catch (err) {}
  }

  // iOS has no beforeinstallprompt — show once if not dismissed.
  if (banner && isIos && !bannerDismissed()) {
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
    if (alreadyInstalled() || bannerDismissed()) {
      return;
    }
    deferred = e;
    if (wrap) {
      var readyNote = wrap.querySelector('[data-pwa-ready]');
      var installBtn = wrap.querySelector('[data-pwa-install-btn]');
      if (installBtn) installBtn.disabled = false;
      if (readyNote) readyNote.hidden = false;
    }
    showBannerGently();
  });

  window.addEventListener('appinstalled', function () {
    deferred = null;
    if (wrap) wrap.hidden = true;
    markInstalled();
    try {
      localStorage.setItem('vellisys-ask-push-install', '1');
      sessionStorage.removeItem('vellisys-push-asked');
    } catch (err) {}
  });
})();
