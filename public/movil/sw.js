// Service worker de la PWA móvil. Se sirve desde /m/sw.js (MovilController::serviceWorker)
// para que su alcance sea /m/. Bump CACHE_NAME cuando cambien app.css / app.js.
const CACHE_NAME = 'movil-v1';
const ASSETS_ESTATICOS = [
  '/movil/app.css',
  '/movil/app.js',
  '/movil/icons/icon-192.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS_ESTATICOS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((nombres) => Promise.all(nombres.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

// network-first para páginas y JSON (datos siempre frescos), cache-first para /movil/*.
self.addEventListener('fetch', (event) => {
  // Los POST (vender, compra, movimiento) nunca pasan por la caché.
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/movil/')) {
    event.respondWith(
      caches.match(event.request, { ignoreSearch: true }).then((cached) => cached || fetch(event.request))
    );
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((resp) => {
        // Guarda una copia de las páginas /m para poder abrirlas sin conexión
        if (resp.ok && !resp.redirected && event.request.mode === 'navigate') {
          const copia = resp.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copia));
        }
        return resp;
      })
      .catch(() => caches.match(event.request))
  );
});
