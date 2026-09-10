/* Vellisys service worker: cache stationery only. PHP pages stay network-first. */
const CACHE = 'vellisys-shell-v1';
const PRECACHE = [
  './assets/css/landing.css',
  './assets/css/app.css',
  './assets/img/pwa-192.png',
  './assets/img/pwa-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE.map((path) => new URL(path, self.registration.scope).toString())).catch(() => undefined))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
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

  const dest = req.destination;
  const isPage =
    req.mode === 'navigate' ||
    dest === 'document' ||
    url.pathname.endsWith('.php') ||
    url.pathname.endsWith('/');

  if (isPage) {
    event.respondWith(
      fetch(req).catch(() =>
        caches.match(req).then((cached) => cached || new Response('Vellisys needs a connection.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }))
      )
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(req).then((res) => {
        if (res && res.ok && (dest === 'style' || dest === 'script' || dest === 'image' || dest === 'font')) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy));
        }
        return res;
      });
    })
  );
});
