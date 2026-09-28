/* シオヨミ PWA — 静的シェル程度のオフライン対応 */
const CACHE_NAME = 'shioyomi-shell-v1';
const SHELL = [
  './',
  './index.php',
  './offline.html',
  './manifest.webmanifest',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/js/webmcp.js',
  './assets/js/pwa.js',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png',
  './assets/img/favicon-32.png',
  './assets/img/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  // 同一オリジン以外は触らない
  if (url.origin !== self.location.origin) return;

  // API・外部データ依存ページはネットワーク優先
  if (url.pathname.includes('/api/') || /\/(chart|calendar)\.php$/.test(url.pathname)) {
    event.respondWith(
      fetch(req).catch(() => caches.match('./offline.html'))
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req)
        .then((res) => {
          const copy = res.clone();
          if (res.ok && (req.destination === 'style' || req.destination === 'script' || req.destination === 'image' || req.destination === 'manifest')) {
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return res;
        })
        .catch(() => cached || caches.match('./offline.html'));
      return cached || network;
    })
  );
});
