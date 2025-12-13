/**
 * Push Notifications - Torneo Mitai
 */
(function () {
    const btn = document.getElementById('push-toggle-btn');
    if (!btn) return;

    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    const canPush = 'PushManager' in window && 'serviceWorker' in navigator && (!isIOS || isPWA);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const isAuth = btn.dataset.authenticated === 'true';
    let subscribed = false;
    let swReg = null;

    // UI del botón
    function updateUI() {
        const bell = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>';
        if (subscribed) {
            btn.innerHTML = bell + '<span class="w-2 h-2 bg-green-400 rounded-full -ml-2 -mt-3"></span><span class="hidden sm:inline">Activas</span>';
            btn.className = 'inline-flex items-center gap-1 rounded-full border border-green-400/50 bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-green-700';
        } else {
            btn.innerHTML = bell + '<span class="hidden sm:inline">Notificaciones</span>';
            btn.className = 'inline-flex items-center gap-2 rounded-full border border-amber-400/50 bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-lg transition hover:bg-amber-600';
        }
    }

    // Inicializar
    async function init() {
        if (canPush) {
            try {
                swReg = await navigator.serviceWorker.register('/sw.js');
                const sub = await swReg.pushManager.getSubscription();
                subscribed = !!sub;
            } catch (e) { }
        }
        updateUI();
    }

    // Click handler
    btn.onclick = async (e) => {
        e.preventDefault();

        if (!isAuth) {
            if (confirm('Para activar notificaciones necesitás iniciar sesión. ¿Ir al login?'))
                window.location.href = '/login';
            return;
        }

        if (isIOS && !isPWA) {
            alert('Tocá Compartir 📤 → "Agregar a inicio" para instalar la app.');
            return;
        }

        if (!canPush) {
            alert('Tu navegador no soporta notificaciones push');
            return;
        }

        btn.disabled = true;
        try {
            if (subscribed) {
                const sub = await swReg.pushManager.getSubscription();
                if (sub) {
                    await fetch('/Panel/push/unsubscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ endpoint: sub.endpoint })
                    });
                    await sub.unsubscribe();
                }
                subscribed = false;
            } else {
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') { alert('Permiso denegado'); btn.disabled = false; return; }

                const vapid = await (await fetch('/push/vapid-public-key')).json();
                const sub = await swReg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: Uint8Array.from(atob(vapid.publicKey.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0))
                });

                await fetch('/Panel/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        endpoint: sub.endpoint,
                        keys: {
                            p256dh: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('p256dh')))),
                            auth: btoa(String.fromCharCode(...new Uint8Array(sub.getKey('auth'))))
                        }
                    })
                });
                subscribed = true;
            }
            updateUI();
        } catch (e) { alert('Error: ' + e.message); }
        btn.disabled = false;
    };

    // Iniciar cuando DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
