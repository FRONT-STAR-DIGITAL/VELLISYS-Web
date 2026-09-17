/* Vellisys service worker: cache shell assets, and a branded offline page for failed navigations. Never intercept form posts. */
const CACHE = 'vellisys-shell-v9';
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;
const SHELL = [
  OFFLINE_URL,
  new URL('assets/img/pwa-192.png', self.registration.scope).href,
  new URL('assets/img/pwa-180.png', self.registration.scope).href,
  new URL('assets/img/vellisys-logo.png', self.registration.scope).href,
  new URL('assets/img/v-mark.png', self.registration.scope).href,
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE).then((cache) =>
      Promise.all(
        SHELL.map((url) => cache.add(new Request(url, { cache: 'reload' })).catch(() => {}))
      )
    )
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }

  if (req.mode === 'navigate' || req.destination === 'document') {
    event.respondWith(
      fetch(req).catch(() =>
        caches.match(OFFLINE_URL, { ignoreSearch: true }).then((cached) => {
          if (cached) {
            return cached;
          }
          return new Response(
            '<!DOCTYPE html><title>Offline</title><p>You’re offline.</p><p><button onclick="location.reload()">Try again</button></p>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
          );
        })
      )
    );
    return;
  }

  let url;
  try {
    url = new URL(req.url);
  } catch (e) {
    return;
  }
  if (url.origin !== self.location.origin) {
    return;
  }
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    return;
  }
  const dest = req.destination;
  if (dest !== 'style' && dest !== 'script' && dest !== 'image' && dest !== 'font') {
    return;
  }
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(req).then((res) => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy));
        }
        return res;
      });
    })
  );
});

function applyPushBadge(count) {
  var n = Number(count);
  if (!isFinite(n) || n < 0) n = 0;
  if (typeof self.registration.setAppBadge === 'function') {
    return n > 0 ? self.registration.setAppBadge(n) : self.registration.clearAppBadge();
  }
  return Promise.resolve();
}

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'Vellisys', body: event.data ? event.data.text() : '' };
  }
  const title = data.title || 'Vellisys';
  const opts = {
    body: data.body || '',
    icon: '/assets/img/pwa-192.png',
    badge: '/assets/img/pwa-192.png',
    tag: data.tag || 'vellisys',
    renotify: true,
    data: { url: data.url || '/dashboard.php' },
    vibrate: [140, 70, 140],
  };
  const count = data.badge != null ? data.badge : 1;
  event.waitUntil(Promise.all([
    self.registration.showNotification(title, opts),
    applyPushBadge(count),
  ]));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/dashboard.php';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
      for (const client of windows) {
        if ('focus' in client) {
          client.focus();
          if ('navigate' in client && target) {
            try {
              client.navigate(target);
            } catch (e) {}
          }
          return;
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(target);
      }
    })
  );
});
