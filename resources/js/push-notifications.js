/**
 * Push Notifications Manager para Torneo Mitai - Versión optimizada
 */

class PushNotificationManager {
    constructor() {
        this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        this.isPWA = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        this.isSupported = ('serviceWorker' in navigator) && ('PushManager' in window) && (!this.isIOS || this.isPWA);
        this.swRegistration = null;
        this.isSubscribed = false;
        this.isAuthenticated = false;
        this.toggleButton = document.getElementById('push-toggle-btn');
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    async init() {
        if (!this.toggleButton) return;

        const isAuthFromDOM = this.toggleButton.dataset.authenticated === 'true';

        // Siempre configurar el click handler
        this.toggleButton.onclick = (e) => {
            e.preventDefault();
            if (!isAuthFromDOM && !this.isAuthenticated) {
                this.showLoginPopup();
            } else if (this.isIOS && !this.isPWA) {
                this.showIOSInstructions();
            } else if (!this.isSupported) {
                this.showAlert('No soportado', 'Tu navegador no soporta notificaciones push', 'warning');
            } else {
                this.toggleSubscription();
            }
        };

        // Si hay soporte, registrar SW y verificar estado
        if (this.isSupported) {
            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                await this.checkStatus();
            } catch (e) { /* silenciar errores */ }
        }

        this.updateButtonUI();
    }

    async checkStatus() {
        try {
            const sub = await this.swRegistration?.pushManager?.getSubscription();
            this.isSubscribed = sub !== null;

            const res = await fetch('/Panel/push/status', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                credentials: 'same-origin'
            });

            if (res.ok) {
                const data = await res.json();
                this.isSubscribed = data.subscribed;
                this.isAuthenticated = true;
            }
        } catch (e) { /* silenciar */ }

        this.updateButtonUI();
    }

    async toggleSubscription() {
        if (!this.toggleButton) return;
        this.toggleButton.disabled = true;

        try {
            if (this.isSubscribed) {
                await this.unsubscribe();
            } else {
                await this.subscribe();
            }
        } catch (e) {
            this.showAlert('Error', 'No se pudo cambiar el estado', 'error');
        }

        this.toggleButton.disabled = false;
    }

    async subscribe() {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            this.showAlert('Permiso Denegado', 'Debes permitir las notificaciones', 'warning');
            return;
        }

        const vapidRes = await fetch('/push/vapid-public-key');
        const { publicKey } = await vapidRes.json();
        if (!publicKey) throw new Error('No VAPID key');

        const sub = await this.swRegistration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlB64ToUint8(publicKey)
        });

        const res = await fetch('/Panel/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({
                endpoint: sub.endpoint,
                keys: { p256dh: this.bufferToB64(sub.getKey('p256dh')), auth: this.bufferToB64(sub.getKey('auth')) }
            })
        });

        if (!res.ok) throw new Error('Server error');

        this.isSubscribed = true;
        this.updateButtonUI();
        this.showAlert('¡Listo!', 'Notificaciones activadas', 'success');
    }

    async unsubscribe() {
        const sub = await this.swRegistration?.pushManager?.getSubscription();
        if (sub) {
            await fetch('/Panel/push/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify({ endpoint: sub.endpoint })
            });
            await sub.unsubscribe();
        }

        this.isSubscribed = false;
        this.updateButtonUI();
        this.showAlert('Listo', 'Notificaciones desactivadas', 'info');
    }

    updateButtonUI() {
        if (!this.toggleButton) return;

        const icon = `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>`;
        const text = this.isSubscribed ? 'Activas' : 'Notificaciones';

        this.toggleButton.innerHTML = `${icon}<span class="hidden sm:inline">${text}</span>`;
    }

    showLoginPopup() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '🔔 Iniciá sesión',
                html: '<p class="text-gray-600">Para activar notificaciones necesitás iniciar sesión.</p><p class="text-xs text-gray-400 mt-2">¡Es gratis!</p>',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Iniciar sesión',
                cancelButtonText: 'Ahora no'
            }).then(r => { if (r.isConfirmed) window.location.href = '/login'; });
        } else {
            if (confirm('Para activar notificaciones necesitás iniciar sesión. ¿Ir al login?')) {
                window.location.href = '/login';
            }
        }
    }

    showIOSInstructions() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '📲 Instalá la App',
                html: '<div class="text-left text-sm"><p class="mb-2">Para notificaciones en iPhone:</p><ol class="list-decimal pl-5 space-y-1"><li>Tocá <strong>Compartir</strong> 📤</li><li>Seleccioná <strong>"Agregar a inicio"</strong></li><li>Abrí desde el ícono</li></ol><p class="mt-2 text-xs text-gray-500">Requiere iOS 16.4+</p></div>',
                icon: 'info',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3b82f6'
            });
        } else {
            alert('Para recibir notificaciones en iPhone, agregá esta página a tu pantalla de inicio usando el botón Compartir.');
        }
    }

    showAlert(title, text, icon) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon, title, text, timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
        }
    }

    urlB64ToUint8(b64) {
        const padding = '='.repeat((4 - b64.length % 4) % 4);
        const base64 = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const arr = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    bufferToB64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return window.btoa(binary);
    }
}

// Inicializar SIEMPRE cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    const pm = new PushNotificationManager();
    pm.init();
    window.pushManager = pm;
});
