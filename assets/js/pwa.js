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
  var isIos = /iphone|ipad|ipod/i.test(ua)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var dismissKey = 'vellisys-install-banner-dismissed';
  var installedKey = 'vellisys-pwa-installed';

  function hideBanner(persist) {
    if (!banner) return;
    banner.classList.remove('is-in');
    banner.classList.remove('is-steps');
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
    if (isInstalledApp()) {
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
    banner.hidden = false;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        banner.classList.add('is-in');
      });
    });
  }

  function showBannerIosSteps() {
    if (!banner) return;
    var steps = banner.querySelector('[data-pwa-banner-ios]');
    if (steps) {
      steps.hidden = false;
      banner.classList.add('is-steps');
    }
  }

  function markInstalled() {
    setStorageFlag(installedKey);
    try {
      localStorage.setItem(dismissKey, '1');
    } catch (err) {}
    hideBanner(true);
    if (wrap) {
      wrap.hidden = true;
    }
  }

  function showLoginSteps() {
    if (!wrap) return;
    var ios = wrap.querySelector('[data-pwa-ios]');
    var fallback = wrap.querySelector('[data-pwa-fallback]');
    if (isIos) {
      if (ios) ios.hidden = false;
      if (fallback) fallback.hidden = true;
    } else {
      if (fallback) fallback.hidden = false;
      if (ios) ios.hidden = true;
    }
    try {
      wrap.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    } catch (err) {
      wrap.scrollIntoView(true);
    }
  }

  /** Returns true when the native install dialog was opened. */
  function runInstall(btn) {
    if (deferred) {
      var promptEvent = deferred;
      deferred = null;
      promptEvent.prompt();
      promptEvent.userChoice.then(function (choice) {
        if (choice && choice.outcome === 'accepted') {
          markInstalled();
        } else {
          // Keep listening for a later prompt if they cancelled.
          deferred = null;
        }
      }).catch(function () {});
      return true;
    }
    return false;
  }

  if (alreadyInstalled()) {
    if (wrap) {
      wrap.hidden = true;
    }
    if (banner) {
      banner.hidden = true;
    }
    return;
  }

  if (navigator.getInstalledRelatedApps) {
    try {
      navigator.getInstalledRelatedApps().then(function (apps) {
        if (apps && apps.length) {
          markInstalled();
        }
      }).catch(function () {});
    } catch (err) {}
  }

  // Soft slide-down on the public site for everyone who has not installed / dismissed.
  if (banner && !bannerDismissed()) {
    window.setTimeout(showBannerGently, 900);
  }

  if (wrap) {
    var btn = wrap.querySelector('[data-pwa-install-btn]');
    var ios = wrap.querySelector('[data-pwa-ios]');
    var ready = wrap.querySelector('[data-pwa-ready]');

    // iPhone / iPad: show Add to Home Screen steps right away.
    if (isIos && ios) {
      ios.hidden = false;
    }

    if (btn) {
      btn.addEventListener('click', function () {
        if (runInstall(btn)) {
          return;
        }
        // No native prompt (typical on iPhone) - show install steps.
        showLoginSteps();
      });
    }

    if (ready && deferred) {
      ready.hidden = false;
    }
  }

  if (banner) {
    var bannerBtn = banner.querySelector('[data-pwa-install-btn]');
    var dismiss = banner.querySelector('[data-pwa-install-dismiss]');
    if (bannerBtn) {
      bannerBtn.addEventListener('click', function () {
        if (runInstall(bannerBtn)) {
          return;
        }
        if (isIos) {
          showBannerIosSteps();
          return;
        }
        // Desktop/Android without a prompt yet - open sign-in install help.
        var login = bannerBtn.getAttribute('data-pwa-install-login')
          || html.getAttribute('data-pwa-login')
          || 'login.php';
        try {
          location.href = login + (login.indexOf('#') === -1 ? '#install' : '');
        } catch (err) {
          location.assign(login);
        }
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
    if (alreadyInstalled()) {
      return;
    }
    deferred = e;
    if (wrap) {
      var readyNote = wrap.querySelector('[data-pwa-ready]');
      var installBtn = wrap.querySelector('[data-pwa-install-btn]');
      if (installBtn) installBtn.disabled = false;
      if (readyNote) readyNote.hidden = false;
    }
    if (banner && !bannerDismissed()) {
      showBannerGently();
    }
  });

  window.addEventListener('appinstalled', function () {
    deferred = null;
    markInstalled();
    try {
      localStorage.setItem('vellisys-ask-push-install', '1');
      sessionStorage.removeItem('vellisys-push-asked');
    } catch (err) {}
  });

  // Deep link from the landing banner when no native prompt is available.
  if (wrap && /(?:^|#|&)install(?:&|$)/.test(location.hash || '')) {
    window.setTimeout(function () {
      showLoginSteps();
      var installBtn = wrap.querySelector('[data-pwa-install-btn]');
      if (installBtn && typeof installBtn.focus === 'function') {
        try { installBtn.focus(); } catch (err) {}
      }
    }, 200);
  }
})();
