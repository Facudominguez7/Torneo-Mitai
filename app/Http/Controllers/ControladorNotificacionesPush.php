<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Controlador para gestionar las suscripciones de notificaciones push.
 * 
 * Este controlador maneja:
 * - Suscripción de usuarios a notificaciones push
 * - Cancelación de suscripciones
 * - Estado de suscripción del usuario
 * - Entrega de clave pública VAPID
 * 
 * @package App\Http\Controllers
 */
class ControladorNotificacionesPush extends Controller
{
    /**
     * Servicios de push permitidos para validación de seguridad.
     * Solo se aceptan endpoints de estos proveedores legítimos.
     */
    private const ALLOWED_PUSH_SERVICES = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com', 
        'wns.windows.com',
        'push.apple.com',
        'web.push.apple.com',
        'android.googleapis.com',
    ];

    /**
     * Máximo número de suscripciones por usuario.
     * Esto previene el abuso de recursos del servidor.
     */
    private const MAX_SUBSCRIPTIONS_PER_USER = 5;

    /**
     * Suscribir al usuario autenticado a notificaciones push.
     * 
     * Validaciones de seguridad:
     * - Requiere autenticación
     * - Valida formato del endpoint
     * - Verifica que el endpoint sea de un servicio legítimo
     * - Limita cantidad de suscripciones por usuario
     * - Registra el evento para auditoría
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        // Validación de los datos de suscripción
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);

        // Validar que el endpoint sea de un servicio push legítimo
        if (!$this->isValidPushEndpoint($request->endpoint)) {
            Log::channel('single')->warning('Intento de suscripción con endpoint no válido', [
                'user_id' => $request->user()->id,
                'endpoint' => $request->endpoint,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Servicio de notificaciones no válido'
            ], 422);
        }

        $user = $request->user();

        // Limitar cantidad de suscripciones por usuario para prevenir abuso
        if ($user->pushSubscriptions()->count() >= self::MAX_SUBSCRIPTIONS_PER_USER) {
            // Eliminar la suscripción más antigua
            $user->pushSubscriptions()->oldest()->first()->delete();
        }

        // Crear o actualizar la suscripción
        $user->updatePushSubscription(
            $request->endpoint,
            $request->keys['p256dh'],
            $request->keys['auth']
        );

        // Registrar evento para auditoría de seguridad
        Log::channel('single')->info('Suscripción push creada', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Notificaciones activadas correctamente!'
        ]);
    }

    /**
     * Cancelar la suscripción del usuario a notificaciones push.
     * 
     * El usuario solo puede eliminar sus propias suscripciones.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
        ]);

        $user = $request->user();

        // Solo elimina suscripciones del usuario autenticado (seguridad)
        $deleted = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->delete();

        // Registrar evento para auditoría
        Log::channel('single')->info('Suscripción push eliminada', [
            'user_id' => $user->id,
            'deleted' => $deleted > 0,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones desactivadas'
        ]);
    }

    /**
     * Verificar el estado de suscripción del usuario.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $subscriptions = $user->pushSubscriptions()->count();

        return response()->json([
            'subscribed' => $subscriptions > 0,
            'subscriptions_count' => $subscriptions,
        ]);
    }

    /**
     * Obtener la clave pública VAPID para el cliente.
     * Esta clave es segura para exponerla públicamente.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVapidPublicKey()
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key')
        ]);
    }

    /**
     * Validar que el endpoint pertenezca a un servicio de push legítimo.
     * Esto previene ataques donde un atacante intenta usar endpoints maliciosos.
     *
     * @param string $endpoint
     * @return bool
     */
    private function isValidPushEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        
        if (!$host) {
            return false;
        }

        foreach (self::ALLOWED_PUSH_SERVICES as $allowedService) {
            if (str_ends_with($host, $allowedService)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enviar una notificación manual a todos los usuarios suscritos.
     * Solo accesible por administradores.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enviarNotificacionManual(Request $request)
    {
        // Validar datos de la notificación
        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:100'],
            'mensaje' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:10'],
        ]);

        $titulo = $validated['titulo'];
        $mensaje = $validated['mensaje'];
        $url = $validated['url'] ?? '/';
        $icono = $validated['icono'] ?? '📢';

        // Obtener usuarios con suscripciones push activas
        $usuariosSuscritos = \App\Models\User::whereHas('pushSubscriptions')->get();

        if ($usuariosSuscritos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay usuarios suscritos a notificaciones',
                'count' => 0
            ]);
        }

        // Enviar notificación a cada usuario suscrito
        $notificacion = new \App\Notifications\NotificacionPersonalizada($titulo, $mensaje, $url, $icono);
        
        foreach ($usuariosSuscritos as $usuario) {
            try {
                $usuario->notify($notificacion);
            } catch (\Exception $e) {
                Log::channel('single')->error('Error enviando notificación a usuario', [
                    'user_id' => $usuario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Registrar evento
        Log::channel('single')->info('Notificación manual enviada', [
            'admin_id' => $request->user()->id,
            'titulo' => $titulo,
            'usuarios_notificados' => $usuariosSuscritos->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notificación enviada correctamente',
            'count' => $usuariosSuscritos->count()
        ]);
    }

    /**
     * Listar usuarios con suscripciones push activas.
     * Solo accesible por administradores.
     */
    public function listarSuscriptores()
    {
        $suscriptores = \App\Models\User::whereHas('pushSubscriptions')
            ->withCount('pushSubscriptions')
            ->orderBy('created_at', 'desc')
            ->get();

        // Variables necesarias para el layout admin.blade.php
        $ediciones = \App\Models\Edicion::orderBy('id', 'desc')->get();
        $EdicionSeleccionada = $ediciones->first();

        return view('Panel.suscriptores-push', compact('suscriptores', 'ediciones', 'EdicionSeleccionada'));
    }
}
