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

        // Obtener TODOS los usuarios que deben recibir notificaciones:
        // - Usuarios con email verificado y suscripción a email activa
        // - Usuarios con push activo (independientemente del email)
        $usuariosEmail = \App\Models\User::suscritosAEmail()->get();
        $usuariosPush = \App\Models\User::whereHas('pushSubscriptions')->get();
        
        // Combinar ambos grupos sin duplicados
        $todosLosUsuarios = $usuariosEmail->merge($usuariosPush)->unique('id');

        if ($todosLosUsuarios->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay usuarios para notificar',
                'count' => 0
            ]);
        }

        // Crear la notificación
        $notificacion = new \App\Notifications\NotificacionPersonalizada($titulo, $mensaje, $url, $icono);
        
        $contadorPush = 0;
        $contadorEmail = 0;

        // Enviar notificación a cada usuario
        // Cada notificación decide qué canales usar según las preferencias del usuario
        foreach ($todosLosUsuarios as $usuario) {
            try {
                $usuario->notify($notificacion);
                
                // Contar por tipo de notificación
                if ($usuario->pushSubscriptions()->exists()) {
                    $contadorPush++;
                }
                if ($usuario->quiereRecibirEmails()) {
                    $contadorEmail++;
                }
            } catch (\Exception $e) {
                Log::channel('single')->error('Error enviando notificación a usuario', [
                    'user_id' => $usuario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Registrar evento con detalles de envío
        Log::channel('single')->info('Notificación manual enviada', [
            'admin_id' => $request->user()->id,
            'titulo' => $titulo,
            'usuarios_push' => $contadorPush,
            'usuarios_email' => $contadorEmail,
            'total_usuarios' => $todosLosUsuarios->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Notificación enviada: {$contadorPush} push, {$contadorEmail} email",
            'count' => $todosLosUsuarios->count(),
            'push_count' => $contadorPush,
            'email_count' => $contadorEmail
        ]);
    }

    /**
     * Listar usuarios con suscripciones push activas.
     * Solo accesible por administradores.
     * 
     * Muestra una tabla con todos los usuarios que tienen notificaciones push
     * activas, incluyendo su estado de suscripción a emails.
     *
     * @return \Illuminate\View\View
     */
    /**
     * Listar usuarios para gestión de notificaciones.
     * Solo accesible por administradores.
     * 
     * Muestra una tabla con todos los usuarios, permitiendo filtrar por:
     * - Búsqueda de texto (nombre/email)
     * - Estado de notificación Push
     * - Estado de notificación Email
     * - Estado de verificación de Email
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function listarSuscriptores(Request $request)
    {
        $query = \App\Models\User::withCount('pushSubscriptions')
            ->orderBy('created_at', 'desc');

        // Filtro por búsqueda (nombre o email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por estado de Push
        if ($request->filled('push_status') && $request->push_status !== 'todos') {
            if ($request->push_status === 'activo') {
                $query->has('pushSubscriptions');
            } else { // inactivo
                $query->doesntHave('pushSubscriptions');
            }
        }

        // Filtro por estado de Email (Suscripción)
        if ($request->filled('email_notif_status') && $request->email_notif_status !== 'todos') {
            if ($request->email_notif_status === 'activo') {
                $query->where('email_subscribed', true);
            } else { // inactivo
                $query->where('email_subscribed', false);
            }
        }

        // Filtro por estado de Verificación de Email
        if ($request->filled('verified_status') && $request->verified_status !== 'todos') {
            if ($request->verified_status === 'verificado') {
                $query->whereNotNull('email_verified_at');
            } else { // no_verificado
                $query->whereNull('email_verified_at');
            }
        }

        $usuarios = $query->paginate(20)->withQueryString();

        // Variables necesarias para el layout admin.blade.php
        $ediciones = \App\Models\Edicion::orderBy('id', 'desc')->get();
        $EdicionSeleccionada = $ediciones->first();

        return view('Panel.suscriptores-push', compact('usuarios', 'ediciones', 'EdicionSeleccionada'));
    }

    /**
     * Reenviar manualmente el correo de verificación a un usuario.
     * Solo accesible por administradores.
     *
     * @param Request $request
     * @param int $id ID del usuario
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reenviarVerificacionManual(Request $request, int $id)
    {
        $user = \App\Models\User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', "El usuario {$user->name} ya tiene el email verificado.");
        }

        $user->sendEmailVerificationNotification();
        
        Log::channel('single')->info('Email de verificación reenviado manualmente por admin', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
        ]);

        return back()->with('success', "Email de verificación enviado exitosamente a {$user->email}.");
    }

    /**
     * Alternar la suscripción del usuario a notificaciones por email.
     * 
     * Permite a un usuario autenticado activar o desactivar
     * la recepción de notificaciones por correo electrónico.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleEmailSubscription(Request $request)
    {
        $user = $request->user();
        
        // Alternar el estado de suscripción
        $user->email_subscribed = !$user->email_subscribed;
        $user->save();

        // Registrar el cambio para auditoría
        Log::channel('single')->info('Suscripción email modificada', [
            'user_id' => $user->id,
            'email_subscribed' => $user->email_subscribed,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'email_subscribed' => $user->email_subscribed,
            'message' => $user->email_subscribed 
                ? 'Notificaciones por email activadas' 
                : 'Notificaciones por email desactivadas'
        ]);
    }

    /**
     * Dar de baja a un usuario de notificaciones por email.
     * 
     * Ruta pública que permite a usuarios darse de baja sin iniciar sesión,
     * usando un token de verificación basado en sha1 del email.
     * Esto se usa en los enlaces de baja incluidos en cada email.
     *
     * @param int $id ID del usuario
     * @param string $token Token de verificación (sha1 del email)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function darseDeBajaEmail(Request $request)
    {
        $id = $request->query('id');
        $token = $request->query('token');

        if (!$id || !$token) {
            return redirect('/')->with('error', 'Enlace incompleto.');
        }

        $user = \App\Models\User::find($id);

        // Verificar que el usuario existe
        if (!$user) {
            return redirect('/')->with('error', 'Usuario no encontrado.');
        }

        // Verificar el token de seguridad
        $tokenEsperado = sha1($user->email);
        if (!hash_equals($tokenEsperado, $token)) {
            Log::channel('single')->warning('Intento de baja con token inválido', [
                'user_id' => $id,
                'ip' => request()->ip(),
            ]);
            return redirect('/')->with('error', 'Enlace inválido o expirado.');
        }

        // Si ya está dado de baja, informar
        if (!$user->email_subscribed) {
            return redirect('/')->with('info', 'Ya te diste de baja de las notificaciones por email.');
        }

        // Dar de baja al usuario
        $user->email_subscribed = false;
        $user->save();

        // Registrar para auditoría
        Log::channel('single')->info('Usuario dado de baja de emails via enlace', [
            'user_id' => $user->id,
            'ip' => request()->ip(),
        ]);

        return redirect('/')->with('success', '¡Te has dado de baja correctamente de las notificaciones por email!');
    }

    /**
     * Obtener el estado actual de suscripción a emails del usuario.
     * 
     * Devuelve información sobre si el usuario tiene habilitadas
     * las notificaciones por email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function estadoEmail(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'email_subscribed' => $user->email_subscribed,
            'email_verified' => $user->hasVerifiedEmail(),
            'puede_recibir_emails' => $user->quiereRecibirEmails(),
        ]);
    }

    /**
     * Ejecutar manualmente el comando de recordatorios de verificación.
     * Solo accesible por administradores.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ejecutarRecordatoriosManual(Request $request)
    {
        try {
            // Obtener usuarios sin verificar (misma lógica que el comando)
            $usuarios = \App\Models\User::query()
                ->whereNull('email_verified_at')
                ->whereNotNull('email')
                ->where('created_at', '<', now()->subHours(24))
                ->get();

            if ($usuarios->isEmpty()) {
                return back()->with('info', 'No hay usuarios pendientes de recordatorio.');
            }

            $contador = 0;
            
            // Encolar las notificaciones (se procesan en segundo plano)
            foreach ($usuarios as $usuario) {
                $usuario->notify(new \App\Notifications\RecordatorioVerificacionEmail());
                $usuario->last_verification_reminder_at = now();
                $usuario->save();
                $contador++;
            }

            // Registrar la ejecución manual
            Log::channel('single')->info('Recordatorios de verificación encolados manualmente', [
                'admin_id' => $request->user()->id,
                'usuarios_encolados' => $contador,
            ]);

            return back()->with('success', "Se encolaron {$contador} recordatorios. Se enviarán en segundo plano.");

        } catch (\Exception $e) {
            Log::channel('single')->error('Error encolando recordatorios manualmente', [
                'admin_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al encolar los recordatorios: ' . $e->getMessage());
        }
    }
}
