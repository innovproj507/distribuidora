// Service worker de la PWA móvil. Se sirve desde /m/sw.js (MovilController::serviceWorker)
// para que su alcance sea /m/. Bump CACHE_NAME y el ?v= de app.css / app.js en layouts/movil.php
// cuando cambien: los archivos de /movil/ se guardan por URL exacta (con su ?v=), así una versión
// nueva nunca se confunde con la vieja guardada.
const CACHE_NAME = 'movil-v4';

self.addEventListener('install', () => {
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
    // cache-first por URL exacta; si no está, se baja saltando la caché HTTP del navegador y se guarda
    event.respondWith(
      caches.match(event.request).then((cached) => cached || fetch(event.request, { cache: 'reload' }).then((resp) => {
        if (resp.ok) {
          const copia = resp.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copia));
        }
        return resp;
      }))
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
