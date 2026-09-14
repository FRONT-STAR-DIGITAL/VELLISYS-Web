/* Vellisys service worker: cache images/fonts/css only. Never intercept pages or form posts. */
const CACHE = 'vellisys-shell-v6';

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE));
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
  event.waitUntil(self.registration.showNotification(title, opts));
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
