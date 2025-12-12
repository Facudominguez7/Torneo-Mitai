/**
 * Push Notifications Manager para Torneo Mitai
 * 
 * Este módulo gestiona:
 * - Registro del Service Worker
 * - Suscripción/desuscripción de notificaciones push
 * - UI de preferencias de notificaciones
 * 
 * @author Sistema Torneo Mitai
 */

class PushNotificationManager {
    constructor() {
        // Detectar si es iOS
        this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

        // Detectar si está corriendo como PWA instalada
        this.isPWA = window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;

        // En iOS, push solo funciona en Safari como PWA instalada con iOS 16.4+
        this.isIOSSafari = this.isIOS && /Safari/.test(navigator.userAgent) && !/Chrome|CriOS|Firefox|FxiOS/.test(navigator.userAgent);

        // Verificar soporte de push
        this.isSupported = this.checkPushSupport();

        this.swRegistration = null;
        this.isSubscribed = false;
        this.isAuthenticated = false;

        // Elementos del DOM
        this.toggleButton = null;
        this.statusText = null;

        // CSRF Token para requests
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        console.log('[Push] iOS:', this.isIOS, 'PWA:', this.isPWA, 'Safari:', this.isIOSSafari, 'Soportado:', this.isSupported);
    }

    /**
     * Verificar si push notifications están soportadas
     */
    checkPushSupport() {
        // Verificar APIs básicas
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return false;
        }

        // En iOS, solo lo soportamos si es PWA instalada
        if (this.isIOS && !this.isPWA) {
            console.log('[Push] iOS detectado pero no es PWA instalada');
            return false;
        }

        return true;
    }

    /**
     * Inicializar el gestor de notificaciones push
     */
    async init() {
        // Configurar UI primero para tener referencia al botón
        this.setupUI();

        // Si es iOS sin PWA, mostrar mensaje especial
        if (this.isIOS && !this.isPWA) {
            console.warn('[Push] iOS sin PWA - mostrando instrucciones de instalación');
            this.updateUI('ios-no-pwa');
            return;
        }

        if (!this.isSupported) {
            console.warn('[Push] Push notifications no soportadas en este navegador');
            this.updateUI('not-supported');
            return;
        }

        try {
            // Registrar Service Worker
            this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });
            console.log('[Push] Service Worker registrado:', this.swRegistration);

            // Verificar estado de suscripción actual
            await this.checkSubscriptionStatus();

            // Mostrar diálogo de activación si no está suscrito
            this.showAutoPromptIfNeeded();

        } catch (error) {
            console.error('[Push] Error inicializando:', error);
            this.updateUI('error');
        }
    }

    /**
     * Mostrar diálogo automático para activar notificaciones si el usuario no está suscrito.
     * Se muestra cada vez que entra a la página si no tiene notificaciones activadas.
     * Limitado a una vez por día para no ser excesivamente invasivo.
     */
    showAutoPromptIfNeeded() {
        // Si el usuario no está autenticado, invitarlo a loguearse
        if (!this.isAuthenticated) {
            console.log('[Push] Usuario no autenticado, mostrando invitación a login');
            this.showLoginPrompt();
            return;
        }

        // No mostrar si ya está suscrito
        if (this.isSubscribed) {
            console.log('[Push] No se muestra prompt: ya está suscrito');
            return;
        }

        // No mostrar si el permiso ya fue denegado
        if (Notification.permission === 'denied') {
            console.log('[Push] No se muestra prompt: permiso denegado');
            return;
        }

        // Limitar a una vez por día para no ser muy invasivo
        const lastPrompt = localStorage.getItem('pushPromptLastShown');
        if (lastPrompt) {
            const lastDate = new Date(parseInt(lastPrompt));
            const now = new Date();
            const hoursDiff = (now - lastDate) / (1000 * 60 * 60);

            // Si pasaron menos de 24 horas, no mostrar
            if (hoursDiff < 24) {
                console.log('[Push] No se muestra prompt: mostrado hace menos de 24h');
                return;
            }
        }

        // Guardar timestamp del último prompt
        localStorage.setItem('pushPromptLastShown', Date.now().toString());

        // Esperar un poco para no interrumpir inmediatamente
        setTimeout(() => {
            this.showActivationPrompt();
        }, 2000);
    }

    /**
     * Mostrar diálogo invitando a usuarios no logueados a iniciar sesión
     * para poder recibir notificaciones.
     */
    showLoginPrompt() {
        // Limitar a una vez por día
        const lastLoginPrompt = localStorage.getItem('pushLoginPromptLastShown');
        if (lastLoginPrompt) {
            const lastDate = new Date(parseInt(lastLoginPrompt));
            const now = new Date();
            const hoursDiff = (now - lastDate) / (1000 * 60 * 60);

            if (hoursDiff < 24) {
                console.log('[Push] No se muestra prompt de login: mostrado hace menos de 24h');
                return;
            }
        }

        localStorage.setItem('pushLoginPromptLastShown', Date.now().toString());

        // Esperar a que SweetAlert2 esté disponible
        setTimeout(() => {
            this.showLoginPromptDialog();
        }, 2500);
    }

    /**
     * Mostrar el diálogo de invitación a login
     */
    showLoginPromptDialog(retryCount = 0) {
        if (typeof Swal === 'undefined') {
            if (retryCount < 5) {
                setTimeout(() => this.showLoginPromptDialog(retryCount + 1), 500);
                return;
            }
            return;
        }

        Swal.fire({
            title: '🔔 ¡No te pierdas nada!',
            html: `
                <p class="text-gray-600 mb-3">Iniciá sesión para recibir notificaciones de:</p>
                <ul class="text-left text-sm text-gray-500 space-y-1 mb-3">
                    <li>⚽ Resultados de partidos en vivo</li>
                    <li>📅 Próximos encuentros de tu equipo</li>
                    <li>🏆 Novedades del torneo</li>
                </ul>
                <p class="text-xs text-gray-400">¡Creá tu cuenta gratis en segundos!</p>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Iniciar sesión',
            cancelButtonText: 'Ahora no',
            customClass: {
                popup: 'rounded-2xl',
                confirmButton: 'rounded-full px-6',
                cancelButton: 'rounded-full px-6'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/login';
            }
        });
    }

    /**
     * Mostrar el diálogo de activación de notificaciones
     */
    showActivationPrompt(retryCount = 0) {
        // Si SweetAlert2 aún no está cargado, reintentar hasta 5 veces
        if (typeof Swal === 'undefined') {
            if (retryCount < 5) {
                console.log('[Push] SweetAlert2 aún no cargado, reintentando en 500ms...');
                setTimeout(() => this.showActivationPrompt(retryCount + 1), 500);
                return;
            }
            console.warn('[Push] SweetAlert2 no disponible después de varios intentos');
            return;
        }

        Swal.fire({
            title: '🔔 ¡Activá las notificaciones!',
            html: `
                <p class="text-gray-600 mb-2">Recibí alertas instantáneas de:</p>
                <ul class="text-left text-sm text-gray-500 space-y-1 mb-3">
                    <li>⚽ Resultados de partidos</li>
                    <li>📅 Próximos encuentros</li>
                    <li>🏆 Novedades del torneo</li>
                </ul>
                <p class="text-xs text-gray-400">Podés desactivarlas en cualquier momento</p>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '¡Sí, activar!',
            cancelButtonText: 'Ahora no',
            customClass: {
                popup: 'rounded-2xl',
                confirmButton: 'rounded-full px-6',
                cancelButton: 'rounded-full px-6'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                this.subscribe();
            }
        });
    }

    /**
     * Verificar si el usuario ya está suscrito
     */
    async checkSubscriptionStatus() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;

            // Verificar en el servidor (esto también nos dice si el usuario está autenticado)
            const response = await fetch('/Panel/push/status', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                this.isSubscribed = data.subscribed;
                this.isAuthenticated = true; // Usuario está logueado
            } else if (response.status === 401 || response.status === 403) {
                // Usuario no autenticado, no mostrar prompt
                this.isAuthenticated = false;
                console.log('[Push] Usuario no autenticado, no se mostrará prompt');
            }

            console.log('[Push] Estado de suscripción:', this.isSubscribed, 'Autenticado:', this.isAuthenticated);
            this.updateUI(this.isSubscribed ? 'subscribed' : 'not-subscribed');

        } catch (error) {
            console.error('[Push] Error verificando suscripción:', error);
            this.isAuthenticated = false;
        }
    }

    /**
     * Configurar la interfaz de usuario
     */
    setupUI() {
        this.toggleButton = document.getElementById('push-toggle-btn');
        this.statusText = document.getElementById('push-status-text');

        if (this.toggleButton) {
            this.toggleButton.addEventListener('click', () => this.toggleSubscription());
        }

        this.updateUI(this.isSubscribed ? 'subscribed' : 'not-subscribed');
    }

    /**
     * Alternar suscripción (suscribir/desuscribir)
     */
    async toggleSubscription() {
        if (this.toggleButton) {
            this.toggleButton.disabled = true;
        }

        try {
            if (this.isSubscribed) {
                await this.unsubscribe();
            } else {
                await this.subscribe();
            }
        } catch (error) {
            console.error('[Push] Error alternando suscripción:', error);
            this.showNotification('Error', 'No se pudo cambiar el estado de las notificaciones', 'error');
        } finally {
            if (this.toggleButton) {
                this.toggleButton.disabled = false;
            }
        }
    }

    /**
     * Suscribir al usuario a notificaciones push
     */
    async subscribe() {
        try {
            // Solicitar permiso
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                console.warn('[Push] Permiso denegado');
                this.showNotification('Permiso Denegado', 'Debes permitir las notificaciones en tu navegador', 'warning');
                return;
            }

            // Obtener clave VAPID del servidor
            const vapidResponse = await fetch('/push/vapid-public-key');
            const vapidData = await vapidResponse.json();

            if (!vapidData.publicKey) {
                throw new Error('No se pudo obtener la clave VAPID');
            }

            // Crear suscripción push
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidData.publicKey)
            });

            console.log('[Push] Suscripción creada:', subscription);

            // Enviar suscripción al servidor
            const response = await fetch('/Panel/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                        auth: this.arrayBufferToBase64(subscription.getKey('auth'))
                    }
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Error del servidor');
            }

            const data = await response.json();
            this.isSubscribed = true;
            this.updateUI('subscribed');
            this.showNotification('¡Listo!', data.message || 'Notificaciones activadas', 'success');

        } catch (error) {
            console.error('[Push] Error suscribiendo:', error);
            throw error;
        }
    }

    /**
     * Desuscribir al usuario de notificaciones push
     */
    async unsubscribe() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();

            if (subscription) {
                // Notificar al servidor
                await fetch('/Panel/push/unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        endpoint: subscription.endpoint
                    })
                });

                // Cancelar suscripción local
                await subscription.unsubscribe();
            }

            this.isSubscribed = false;
            this.updateUI('not-subscribed');
            this.showNotification('Listo', 'Notificaciones desactivadas', 'info');

        } catch (error) {
            console.error('[Push] Error desuscribiendo:', error);
            throw error;
        }
    }

    /**
     * Actualizar la interfaz según el estado
     */
    updateUI(state) {
        if (!this.toggleButton) return;

        switch (state) {
            case 'subscribed':
                this.toggleButton.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                    </svg>
                    Notificaciones Activas
                `;
                this.toggleButton.className = 'flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors duration-200 font-medium text-sm';
                break;

            case 'not-subscribed':
                this.toggleButton.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                    </svg>
                    Activar Notificaciones
                `;
                this.toggleButton.className = 'flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors duration-200 font-medium text-sm';
                break;

            case 'not-supported':
                this.toggleButton.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v2h-2v-2zm0-8h2v6h-2V9z"/>
                    </svg>
                    No Soportado
                `;
                this.toggleButton.className = 'flex items-center px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed font-medium text-sm';
                this.toggleButton.disabled = true;
                break;

            case 'ios-no-pwa':
                this.toggleButton.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                    </svg>
                    Instalar App
                `;
                this.toggleButton.className = 'flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium text-sm';
                this.toggleButton.onclick = () => this.showIOSInstallInstructions();
                break;

            case 'error':
                this.toggleButton.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    Error
                `;
                this.toggleButton.className = 'flex items-center px-4 py-2 bg-red-500 text-white rounded-lg font-medium text-sm';
                break;
        }
    }

    /**
     * Mostrar instrucciones para instalar PWA en iOS
     */
    showIOSInstallInstructions() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '📲 Instalá la App',
                html: `
                    <div class="text-left text-sm">
                        <p class="mb-3">Para recibir notificaciones en iPhone:</p>
                        <ol class="list-decimal pl-5 space-y-2">
                            <li>Tocá el botón <strong>Compartir</strong> 📤 (abajo)</li>
                            <li>Seleccioná <strong>"Agregar a inicio"</strong></li>
                            <li>Abrí la app desde el ícono</li>
                            <li>Activá las notificaciones</li>
                        </ol>
                        <p class="mt-3 text-xs text-gray-500">Requiere iOS 16.4 o superior</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3b82f6'
            });
        }
    }

    /**
     * Mostrar notificación usando SweetAlert2 si está disponible
     */
    showNotification(title, message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            const iconMap = {
                'success': 'success',
                'error': 'error',
                'warning': 'warning',
                'info': 'info'
            };

            Swal.fire({
                icon: iconMap[type] || 'info',
                title: title,
                text: message,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
        }
    }

    /**
     * Convertir clave VAPID de Base64 URL a Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    /**
     * Convertir ArrayBuffer a Base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar siempre si hay soporte para push
    // El auto-prompt solo se mostrará si el usuario está logueado (hay CSRF token válido)
    // y el servidor responde al status check
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        const pushManager = new PushNotificationManager();
        pushManager.init();

        // Exponer para debugging
        window.pushManager = pushManager;
    }
});

// Exportar para uso modular
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PushNotificationManager;
}

