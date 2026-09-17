(function () {
  var html = document.documentElement;
  var vapid = html.getAttribute('data-vapid') || '';
  var pushUrl = html.getAttribute('data-push') || '';
  var csrf = html.getAttribute('data-csrf') || '';

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

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) {
      out[i] = raw.charCodeAt(i);
    }
    return out;
  }

  function postJson(payload) {
    return fetch(pushUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false };
      });
    });
  }

  function setStatus(text, tone) {
    document.querySelectorAll('[data-push-status]').forEach(function (el) {
      el.textContent = text;
      el.classList.toggle('flash-err', tone === 'err');
    });
  }

  function setButtons(state) {
    document.querySelectorAll('[data-push-allow]').forEach(function (btn) {
      btn.hidden = state === 'on' || state === 'blocked' || state === 'unsupported';
    });
    document.querySelectorAll('[data-push-off]').forEach(function (btn) {
      btn.hidden = state !== 'on';
    });
  }

  function showLocalItems(reg, items) {
    if (!reg || !items || !items.length) {
      return Promise.resolve();
    }
    return Promise.all(items.map(function (item) {
      return reg.showNotification(item.title || 'Vellisys', {
        body: item.body || '',
        icon: '/assets/img/pwa-192.png',
        badge: '/assets/img/pwa-192.png',
        tag: item.key || 'vellisys',
        data: { url: item.url || '/dashboard.php' },
      });
    }));
  }

  function subscribe(reg) {
    if (!vapid || !pushUrl) {
      return Promise.reject(new Error('Sign in to allow notifications.'));
    }
    return reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapid),
    }).then(function (sub) {
      var json = sub.toJSON();
      return postJson({
        action: 'subscribe',
        endpoint: json.endpoint,
        keys: json.keys,
      }).then(function (res) {
        if (!res || !res.ok) {
          throw new Error((res && res.error) || 'Could not save this device.');
        }
        return showLocalItems(reg, res.items || []).then(function () {
          try {
            var n = (res.items || []).length;
            if (n > 0 && navigator.setAppBadge) navigator.setAppBadge(n);
            else if (navigator.clearAppBadge) navigator.clearAppBadge();
          } catch (err) {}
          return res;
        });
      });
    });
  }

  function unsubscribe(reg) {
    return reg.pushManager.getSubscription().then(function (sub) {
      if (!sub) {
        return { ok: true };
      }
      var endpoint = sub.endpoint;
      return sub.unsubscribe().then(function () {
        return postJson({ action: 'unsubscribe', endpoint: endpoint });
      });
    });
  }

  function refreshPanel() {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
      setStatus('This browser cannot show push alerts. Install the Vellisys app on your phone.', 'err');
      setButtons('unsupported');
      return;
    }
    if (!window.isSecureContext) {
      setStatus('Notifications need the installed app or a secure (https) address.', 'err');
      setButtons('unsupported');
      return;
    }
    var perm = Notification.permission;
    navigator.serviceWorker.ready.then(function (reg) {
      return reg.pushManager.getSubscription().then(function (sub) {
        if (perm === 'denied') {
          setStatus('Notifications are blocked for this app. Open the system settings for Vellisys and allow them, then try again.');
          setButtons('blocked');
          return;
        }
        if (perm === 'granted' && sub) {
          setStatus('Notifications are on for this device. New desk alerts will pop up here.');
          setButtons('on');
          return;
        }
        setStatus('Turn on alerts for this device. The prompt also appears after you install the app.');
        setButtons('off');
      });
    }).catch(function () {
      setStatus('Could not reach the notification service on this device.', 'err');
    });
  }

  function askPush(opts) {
    opts = opts || {};
    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
      if (opts.fromSettings) {
        setStatus('This browser cannot show push alerts.', 'err');
      }
      return Promise.resolve(false);
    }
    if (Notification.permission === 'denied') {
      if (opts.fromSettings) {
        setStatus('Notifications are blocked. Allow them in the system settings for this app.', 'err');
        setButtons('blocked');
      }
      return Promise.resolve(false);
    }
    return navigator.serviceWorker.ready.then(function (reg) {
      var req = Notification.permission === 'granted'
        ? Promise.resolve('granted')
        : Notification.requestPermission();
      return req.then(function (perm) {
        if (perm !== 'granted') {
          localStorage.setItem('vellisys-push-denied', '1');
          if (opts.fromSettings) {
            setStatus('You can allow notifications later from Settings.', 'err');
          }
          return false;
        }
        localStorage.removeItem('vellisys-push-denied');
        if (!vapid || !pushUrl) {
          localStorage.setItem('vellisys-push-pending', '1');
          if (opts.fromSettings) {
            setStatus('Sign in, then tap Allow notifications again.');
          }
          return true;
        }
        return subscribe(reg).then(function () {
          setStatus('Notifications are on for this device.');
          setButtons('on');
          return true;
        });
      });
    }).catch(function (err) {
      if (opts.fromSettings) {
        setStatus(err && err.message ? err.message : 'Could not allow notifications.', 'err');
      }
      return false;
    });
  }

  window.vellisysAskPush = askPush;

  function showInstallAsk() {
    if (document.querySelector('[data-push-toast]')) {
      return;
    }
    var bar = document.createElement('div');
    bar.className = 'push-toast';
    bar.setAttribute('data-push-toast', '1');
    bar.innerHTML = '<span>Turn on alerts for this app, the way WhatsApp does.</span><button type="button" class="btn sm">Allow notifications</button>';
    bar.querySelector('button').addEventListener('click', function () {
      askPush({ fromSettings: true, reason: 'install' });
      bar.remove();
    });
    document.body.appendChild(bar);
  }

  function maybeAskAfterInstall() {
    if (localStorage.getItem('vellisys-push-denied') === '1') {
      return;
    }
    if (sessionStorage.getItem('vellisys-push-asked') === '1') {
      return;
    }
    var pending = localStorage.getItem('vellisys-push-pending') === '1'
      || localStorage.getItem('vellisys-ask-push-install') === '1';
    var firstStandalone = false;
    if (isInstalledApp() && localStorage.getItem('vellisys-pwa-installed') !== '1') {
      localStorage.setItem('vellisys-pwa-installed', '1');
      firstStandalone = true;
    }
    if (!pending && !firstStandalone) {
      return;
    }
    sessionStorage.setItem('vellisys-push-asked', '1');
    localStorage.removeItem('vellisys-ask-push-install');
    window.setTimeout(function () {
      askPush({ reason: 'install' }).then(function (ok) {
        if (ok) {
          localStorage.removeItem('vellisys-push-pending');
          var toast = document.querySelector('[data-push-toast]');
          if (toast) toast.remove();
          return;
        }
        if (Notification.permission === 'default') {
          showInstallAsk();
        }
      });
    }, 600);
  }

  document.querySelectorAll('[data-push-allow]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      askPush({ fromSettings: true });
    });
  });
  document.querySelectorAll('[data-push-off]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      navigator.serviceWorker.ready.then(unsubscribe).then(function () {
        setStatus('This device will not get pop-up alerts until you allow them again.');
        setButtons('off');
      });
    });
  });

  if (document.querySelector('[data-push-panel]')) {
    refreshPanel();
  }

  window.addEventListener('appinstalled', function () {
    localStorage.setItem('vellisys-ask-push-install', '1');
    localStorage.setItem('vellisys-pwa-installed', '1');
    sessionStorage.removeItem('vellisys-push-asked');
    window.setTimeout(function () {
      askPush({ reason: 'install' }).then(function (ok) {
        if (!ok && Notification.permission === 'default') {
          showInstallAsk();
        }
      });
    }, 400);
  });

  maybeAskAfterInstall();
})();
