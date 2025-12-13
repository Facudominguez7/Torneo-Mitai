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
    if (url.pathname.startsWith('/Panel') || url.pathname.startsWith('/api') || url.pathname.startsWith('/push')) return;

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

// Push notification
self.addEventListener('push', (event) => {
    let data = { title: 'Torneo Mitai', body: 'Nueva notificación', icon: '/fotos/Logo_Mitai_SinFondo.png', data: { url: '/' } };

    if (event.data) {
        try {
            const p = event.data.json();
            data = { title: p.title || data.title, body: p.body || data.body, icon: p.icon || data.icon, data: { url: p.url || p.data?.url || '/' } };
        } catch (e) { data.body = event.data.text(); }
    }

    event.waitUntil(self.registration.showNotification(data.title, {
        body: data.body, icon: data.icon, badge: data.icon, tag: 'torneo-mitai', data: data.data,
        vibrate: [100, 50, 100], actions: [{ action: 'open', title: 'Ver' }, { action: 'close', title: 'Cerrar' }]
    }));
});

// Click en notificación
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    if (event.action === 'close') return;

    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(list => {
                for (const c of list) if (c.url.includes(self.location.origin) && 'focus' in c) { c.navigate(url); return c.focus(); }
                if (clients.openWindow) return clients.openWindow(url);
            })
    );
});
