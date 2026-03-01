/**
 * Push Notifications - Torneo Mitai
 */
(function () {
    const btn = document.getElementById('push-toggle-btn');
    if (!btn) {
        return;
    }

    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    const canPush = 'PushManager' in window && 'serviceWorker' in navigator && (!isIOS || isPWA);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const isAuth = btn.dataset.authenticated === 'true';
    let subscribed = false;
    let swReg = null;
    let processing = false;

    /**
     * Muestra mensajes usando SweetAlert cuando está disponible.
     */
    const showMessage = async (title, text, icon = 'info') => {
        if (typeof window.Swal !== 'undefined') {
            await window.Swal.fire({
                title,
                text,
                icon,
                confirmButtonText: 'Aceptar',
            });
            return;
        }

        window.alert(`${title}\n\n${text}`);
    };

    /**
     * Solicita inicio de sesión para habilitar notificaciones.
     */
    const askLoginRedirect = async () => {
        if (typeof window.Swal !== 'undefined') {
            const result = await window.Swal.fire({
                title: 'Inicia sesion para activar notificaciones',
                text: 'Necesitas una cuenta para recibir avisos del torneo.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Ir a login',
                cancelButtonText: 'Ahora no',
            });

            if (result.isConfirmed) {
                window.location.href = '/login';
            }

            return;
        }

        if (window.confirm('Para activar notificaciones necesitas iniciar sesion. Ir a login?')) {
            window.location.href = '/login';
        }
    };

    /**
     * Convierte clave VAPID URL-safe base64 a Uint8Array.
     */
    const base64ToUint8Array = (value) => {
        const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
        const raw = window.atob(padded);
        return Uint8Array.from(raw, (char) => char.charCodeAt(0));
    };

    /**
     * Registra o reutiliza el service worker para push.
     */
    const ensureServiceWorker = async () => {
        if (swReg) {
            return swReg;
        }

        swReg = await navigator.serviceWorker.register('/sw.js');
        return swReg;
    };

    /**
     * Ajusta estado visual del botón según autenticación/suscripción.
     */
    const updateUI = () => {
        const bell = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>';

        if (!isAuth) {
            btn.innerHTML = `${bell}<span class="hidden sm:inline">Iniciar sesion</span>`;
            btn.className = 'inline-flex items-center gap-2 rounded-full border border-amber-400/50 bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-amber-600';
            btn.title = 'Iniciar sesion para activar notificaciones';
            return;
        }

        if (subscribed) {
            btn.innerHTML = `${bell}<span class="w-2 h-2 bg-green-400 rounded-full -ml-2 -mt-3"></span><span class="hidden sm:inline">Activas</span>`;
            btn.className = 'inline-flex items-center gap-1 rounded-full border border-green-400/50 bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-green-700';
            btn.title = 'Notificaciones activas';
            return;
        }

        btn.innerHTML = `${bell}<span class="hidden sm:inline">Notificaciones</span>`;
        btn.className = 'inline-flex items-center gap-2 rounded-full border border-amber-400/50 bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-amber-600';
        btn.title = 'Activar notificaciones';
    };

    /**
     * Intenta sincronizar estado local con suscripción registrada en backend.
     */
    const syncStatusFromServer = async () => {
        try {
            const response = await fetch('/Panel/push/status', {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            subscribed = Boolean(data?.subscribed);
        } catch (error) {
            // Fallback silencioso: queda estado local del browser.
        }
    };

    /**
     * Inicializa estado del botón y del navegador.
     */
    const init = async () => {
        if (isAuth && canPush) {
            try {
                const reg = await ensureServiceWorker();
                const sub = await reg.pushManager.getSubscription();
                subscribed = Boolean(sub);
                await syncStatusFromServer();
            } catch (error) {
                subscribed = false;
            }
        }

        updateUI();
    };

    btn.addEventListener('click', async (e) => {
        e.preventDefault();

        if (processing) {
            return;
        }

        if (!isAuth) {
            await askLoginRedirect();
            return;
        }

        if (isIOS && !isPWA) {
            await showMessage('Instala la app en iPhone', 'Toca Compartir y luego "Agregar a inicio" para habilitar notificaciones push.', 'info');
            return;
        }

        if (!canPush) {
            await showMessage('Push no disponible', 'Tu navegador no soporta notificaciones push.', 'warning');
            return;
        }

        processing = true;
        btn.disabled = true;

        try {
            const reg = await ensureServiceWorker();

            if (subscribed) {
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    const response = await fetch('/Panel/push/unsubscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ endpoint: sub.endpoint }),
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo desactivar la suscripcion en el servidor.');
                    }

                    await sub.unsubscribe();
                }

                subscribed = false;
                await showMessage('Notificaciones desactivadas', 'Ya no recibiras avisos push en este dispositivo.', 'success');
            } else {
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') {
                    await showMessage('Permiso denegado', 'No se pudo activar push porque el navegador rechazo el permiso.', 'warning');
                    return;
                }

                const vapidResponse = await fetch('/push/vapid-public-key', {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!vapidResponse.ok) {
                    throw new Error('No se pudo obtener la clave de notificaciones.');
                }

                const vapid = await vapidResponse.json();
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64ToUint8Array(vapid.publicKey),
                });

                const response = await fetch('/Panel/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        endpoint: sub.endpoint,
                        keys: {
                            p256dh: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('p256dh')))),
                            auth: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('auth')))),
                        },
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'No se pudo registrar la suscripcion push.');
                }

                subscribed = true;
                await showMessage('Notificaciones activadas', 'Recibiras avisos de resultados y novedades del torneo.', 'success');
            }

            updateUI();
        } catch (error) {
            await showMessage('Error', error?.message || 'No se pudo completar la operacion.', 'error');
        }

        processing = false;
        btn.disabled = false;
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
