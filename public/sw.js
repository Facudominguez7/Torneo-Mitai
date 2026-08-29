/**
 * Service Worker para Torneo Mitai
 */

const CACHE_NAME = 'torneo-mitai-v1';
const STATIC_ASSETS = ['/fotos/Logo_Mitai_SinFondo.png', '/offline.html'];

// Instalación
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
            .catch(() => { })
    );
});

// Activación
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

// Fetch - Network First
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/Panel') || url.pathname.startsWith('/api')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response.ok && /\.(png|jpg|jpeg|gif|svg|ico|css|js|woff2?)$/.test(url.pathname)) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request).then(r => r || (event.request.mode === 'navigate' ? caches.match('/offline.html') : new Response('Offline', { status: 503 }))))
    );
});
