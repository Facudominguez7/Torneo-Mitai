/**
 * Service Worker para Torneo Mitai
 * 
 * Funcionalidades:
 * - Cache de recursos estáticos para funcionamiento offline
 * - Recepción de notificaciones push
 * - Manejo de clics en notificaciones
 * 
 * SEGURIDAD: Solo se cachean assets estáticos, nunca datos de usuario
 */

const CACHE_NAME = 'torneo-mitai-v1';

// Recursos a cachear para funcionamiento offline básico
// NO incluir rutas de API ni datos de usuario
const STATIC_ASSETS = [
    '/fotos/Logo_Mitai_SinFondo.png',
    '/offline.html'
];

/**
 * Evento de instalación del Service Worker
 * Cachea los recursos estáticos necesarios
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Cacheando recursos estáticos');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Instalación completada');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Error en instalación:', error);
            })
    );
});

/**
 * Evento de activación
 * Limpia caches antiguos
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activando Service Worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => {
                            console.log('[SW] Eliminando cache antiguo:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Activación completada');
                return self.clients.claim();
            })
    );
});

/**
 * Interceptor de fetch
 * Estrategia: Network First, fallback a cache
 * SEGURIDAD: Solo cachear recursos estáticos, nunca respuestas de API
 */
self.addEventListener('fetch', (event) => {
    // Solo interceptar requests GET
    if (event.request.method !== 'GET') {
        return;
    }

    // No cachear requests a rutas de API o Panel
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/Panel') ||
        url.pathname.startsWith('/api') ||
        url.pathname.startsWith('/push')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Solo cachear respuestas exitosas de assets estáticos
                if (response.ok && isStaticAsset(url.pathname)) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME)
                        .then((cache) => cache.put(event.request, responseClone));
                }
                return response;
            })
            .catch(() => {
                // Si la red falla, buscar en cache
                return caches.match(event.request)
                    .then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // Si no hay cache, mostrar página offline
                        if (event.request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        return new Response('Offline', { status: 503 });
                    });
            })
    );
});

/**
 * Verificar si una ruta es un asset estático cacheable
 */
function isStaticAsset(pathname) {
    const staticExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.css', '.js', '.woff', '.woff2'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

/**
 * Evento de notificación push
 * Muestra la notificación al usuario
 */
self.addEventListener('push', (event) => {
    console.log('[SW] Push recibido:', event);

    let data = {
        title: 'Torneo Mitai',
        body: 'Nueva notificación',
        icon: '/fotos/Logo_Mitai_SinFondo.png',
        badge: '/fotos/Logo_Mitai_SinFondo.png',
        tag: 'torneo-mitai-notification',
        data: {
            url: '/'
        }
    };

    // Intentar parsear datos del push
    if (event.data) {
        try {
            const pushData = event.data.json();
            data = {
                title: pushData.title || data.title,
                body: pushData.body || data.body,
                icon: pushData.icon || data.icon,
                badge: pushData.badge || data.badge,
                tag: pushData.tag || data.tag,
                data: {
                    url: pushData.url || pushData.data?.url || '/'
                }
            };
        } catch (e) {
            console.error('[SW] Error parseando datos push:', e);
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        tag: data.tag,
        data: data.data,
        vibrate: [100, 50, 100],
        requireInteraction: false,
        actions: [
            {
                action: 'open',
                title: 'Ver'
            },
            {
                action: 'close',
                title: 'Cerrar'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

/**
 * Evento de clic en notificación
 * Abre la URL asociada a la notificación
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Clic en notificación:', event);

    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Si ya hay una ventana abierta, enfocarla
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(urlToOpen);
                        return client.focus();
                    }
                }
                // Si no hay ventana abierta, abrir una nueva
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

/**
 * Evento de cierre de notificación
 */
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notificación cerrada:', event.notification.tag);
});
