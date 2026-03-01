<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
     * Límite operativo de destinatarios por corrida de resumen por fecha.
     *
     * Se mantiene por debajo del límite diario del proveedor de correo para
     * evitar cortes por cuota y dejar margen para otros envíos del sistema.
     */
    private const MAX_DESTINATARIOS_RESUMEN_FECHA = 900;

    /**
     * Máximo de destinatarios por correo usando BCC según proveedor SMTP.
     */
    private const MAX_DESTINATARIOS_BCC = 100;

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
        // - Usuarios con suscripción a email activa (sin exigir verificación)
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
        $EdicionSeleccionada = $ediciones->firstWhere('id', (int) $request->query('idEdicion')) ?? $ediciones->first();

        $resumenEdicionActual = $ediciones->first();
        $resumenIdEdicion = (int) ($resumenEdicionActual->id ?? 0);
        $resumenNombreEdicion = (string) ($resumenEdicionActual->nombre ?? 'Edición vigente');
        $resumenIdCategoria = $request->query('resumen_idCategoria');
        $resumenIdFecha = $request->query('resumen_idFecha');

        $categoriasResumen = collect();
        $fechasResumen = collect();

        if ($resumenIdEdicion > 0) {
            $categoriasResumen = \App\Models\Categoria::query()
                ->where('idEdicion', $resumenIdEdicion)
                ->orderBy('nombreCategoria')
                ->get(['id', 'nombreCategoria']);

            $consultaFechas = \App\Models\Fecha::query()
                ->where('idEdicion', $resumenIdEdicion);

            if (! empty($resumenIdCategoria)) {
                $consultaFechas->where('idCategoria', $resumenIdCategoria);
            }

            $fechasResumen = $consultaFechas
                ->orderBy('id')
                ->get(['id', 'nombre', 'idCategoria']);
        }

        return view('Panel.suscriptores-push', compact(
            'usuarios',
            'ediciones',
            'EdicionSeleccionada',
            'categoriasResumen',
            'fechasResumen',
            'resumenIdEdicion',
            'resumenNombreEdicion',
            'resumenIdCategoria',
            'resumenIdFecha'
        ));
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
     * Muestra formulario simple para baja general desde correos BCC.
     *
     * Este flujo no requiere token por usuario porque el correo se envía
     * en lote y comparte el mismo contenido para múltiples destinatarios.
     */
    public function formularioBajaGeneral()
    {
        return view('emails.baja-general');
    }

    /**
     * Procesa la baja general usando email ingresado por el usuario.
     *
     * Se usa una respuesta neutral cuando no existe coincidencia para evitar
     * enumeración de cuentas y mantener consistencia de privacidad.
     */
    public function procesarBajaGeneral(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));

        $usuario = \App\Models\User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($usuario) {
            $usuario->forceFill([
                'email_subscribed' => false,
            ])->saveQuietly();

            Log::channel('single')->info('Usuario dado de baja por formulario general', [
                'user_id' => $usuario->id,
                'ip' => $request->ip(),
            ]);
        }

        return redirect('/')->with('success', 'Si el correo está registrado, la baja se procesó correctamente.');
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
    /**
     * Obtener cantidad de usuarios pendientes de recordatorio.
     * Usado por AJAX para iniciar el proceso de envío por lotes.
     */
    public function contarUsuariosPendientes(Request $request)
    {
        $count = \App\Models\User::query()
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return response()->json([
            'count' => $count,
            'message' => $count > 0 
                ? "Hay {$count} usuarios pendientes de recordatorio."
                : 'No hay usuarios pendientes de recordatorio.'
        ]);
    }

    /**
     * Enviar recordatorios en lote (10 usuarios por request).
     * Llamado repetidamente por AJAX hasta completar todos.
     */
    public function enviarLoteRecordatorios(Request $request)
    {
        $batchSize = 10; // Emails por request

        // Obtener siguiente lote de usuarios
        $usuarios = \App\Models\User::query()
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->where('created_at', '<', now()->subHours(24))
            ->where(function($q) {
                $q->whereNull('last_verification_reminder_at')
                  ->orWhere('last_verification_reminder_at', '<', now()->subDays(7));
            })
            ->take($batchSize)
            ->get();
            
        Log::channel('single')->info('DEBUG ENVIO RECORDATORIOS:', [
            'total_encontrados' => $usuarios->count(),
            // 'sql' => $usuarios->toSql(), // Comentado porque da error en colección
        ]);

        if ($usuarios->isEmpty()) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'message' => 'Todos los recordatorios fueron enviados.'
            ]);
        }

        $enviados = 0;
        $errores = [];
        $lastError = null;

        foreach ($usuarios as $usuario) {
            try {
                $usuario->notify(new \App\Notifications\RecordatorioVerificacionEmail());
                $usuario->last_verification_reminder_at = now();
                $usuario->save();
                $enviados++;
                
                Log::channel('single')->info('Recordatorio enviado', [
                    'email' => $usuario->email,
                    'nombre' => $usuario->name,
                ]);
            } catch (\Exception $e) {
                $errores[] = $usuario->email;
                $lastError = $e->getMessage(); // Capturar último error
                
                Log::channel('single')->error('Error enviando recordatorio', [
                    'email' => $usuario->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Ver cuántos quedan
        $remaining = \App\Models\User::query()
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->where('created_at', '<', now()->subHours(24))
            ->where(function($q) {
                $q->whereNull('last_verification_reminder_at')
                  ->orWhere('last_verification_reminder_at', '<', now()->subDays(7));
            })
            ->count();

        return response()->json([
            'done' => $remaining === 0,
            'sent' => $enviados,
            'remaining' => $remaining,
            'errors' => count($errores),
            'last_error' => $lastError, // Enviar error al front
            'message' => "Enviados: {$enviados}. Restantes: {$remaining}"
        ]);
    }

    /**
     * Cuenta destinatarios de correo para notificaciones masivas manuales.
     *
     * Este conteo refleja exactamente la base que luego se usa en el envío
     * por BCC. Así el panel puede mostrar una confirmación realista del
     * alcance, cantidad estimada de correos y límites operativos.
     */
    public function contarSuscriptores(Request $request)
    {
        $totalNotificables = $this->usuariosEmailResumenQuery()->count();
        $totalPermitidos = min($totalNotificables, self::MAX_DESTINATARIOS_RESUMEN_FECHA);
        $correosEstimados = (int) ceil($totalPermitidos / self::MAX_DESTINATARIOS_BCC);

        return response()->json([
            'success' => true,
            'count' => $totalPermitidos,
            'limite_operativo' => self::MAX_DESTINATARIOS_RESUMEN_FECHA,
            'lote_bcc' => self::MAX_DESTINATARIOS_BCC,
            'correos_estimados' => $correosEstimados,
            'message' => "Total usuarios a notificar: {$totalPermitidos}",
        ]);
    }

    /**
     * Envía notificación manual por lotes usando BCC para cuidar cuota SMTP.
     *
     * Se procesa en bloques de hasta 100 destinatarios por request y limita
     * el universo máximo por corrida para respetar restricciones del proveedor.
     */
    public function enviarLoteNotificacionManual(Request $request)
    {
        // Validar datos de la notificación y paginación
        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:100'],
            'mensaje' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:10'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:' . self::MAX_DESTINATARIOS_BCC],
        ]);

        $limit = $validated['limit'];
        $offset = $validated['offset'];
        
        $idsDestinatarios = $this->usuariosEmailResumenQuery()
            ->orderBy('id')
            ->limit(self::MAX_DESTINATARIOS_RESUMEN_FECHA)
            ->pluck('id')
            ->values();

        $idsLote = $idsDestinatarios->slice($offset, $limit)->values();

        $usuarios = \App\Models\User::query()
            ->whereIn('id', $idsLote)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        if ($usuarios->isEmpty()) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'message' => 'Fin del proceso.'
            ]);
        }

        $enviados = 0;
        $errores = [];
        $lastError = null;
        $contadorEmail = 0;

        $emails = $usuarios->pluck('email')
            ->filter(static fn ($email) => ! empty($email))
            ->unique()
            ->values()
            ->all();

        if ($emails !== []) {
            try {
                $resumenManual = [
                    'edicion_nombre' => 'Comunicado general',
                    'fecha_nombre' => 'Notificación del torneo',
                    'categoria_nombre' => null,
                    'url_fixture' => $validated['url'] ?? '/',
                    'partidos' => [],
                    'partidos_count' => 0,
                    'titulo_personalizado' => $this->construirTituloPersonalizado(
                        $validated['titulo'],
                        $validated['icono'] ?? '📢'
                    ),
                    'mensaje_personalizado' => $validated['mensaje'],
                ];

                $mailFrom = config('mail.from.address') ?: 'info@torneosmitai.com';
                Mail::to($mailFrom)
                    ->bcc($emails)
                    ->send(new \App\Mail\ResumenResultadosFechaMail($resumenManual, route('email.baja-general.form')));

                $enviados = count($emails);
                $contadorEmail = $enviados;

                \App\Models\User::query()
                    ->whereIn('id', $usuarios->pluck('id'))
                    ->update(['last_notification_at' => now()]);
            } catch (\Exception $e) {
                $errores = $emails;
                $lastError = $e->getMessage();
                Log::channel('single')->error('Error notif manual por BCC', ['error' => $e->getMessage()]);
            }
        }

        $remaining = max(0, $idsDestinatarios->count() - ($offset + $usuarios->count()));

        return response()->json([
            'done' => $remaining === 0,
            'sent' => $enviados,
            'errors' => count($errores),
            'last_error' => $lastError,
            'push_count' => 0,
            'email_count' => $contadorEmail,
            'remaining' => $remaining,
        ]);
    }

    /**
     * Cuenta destinatarios y valida disponibilidad del resumen por fecha.
     *
     * Se usa antes de iniciar el proceso en lotes para dar feedback claro
     * al administrador sobre alcance del envío y partidos incluidos.
     */
    public function contarDestinatariosResumenFecha(Request $request)
    {
        $validated = $request->validate([
            'idFecha' => ['required', 'integer', 'exists:fechas,id'],
            'idCategoria' => ['nullable', 'integer', 'exists:categorias,id'],
        ]);

        $idEdicion = $this->obtenerIdUltimaEdicion();
        if ($idEdicion === null) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => 'No hay ediciones disponibles para enviar notificaciones.',
            ], 422);
        }

        $resumen = $this->obtenerResumenResultadosFecha(
            $idEdicion,
            (int) $validated['idFecha'],
            isset($validated['idCategoria']) ? (int) $validated['idCategoria'] : null
        );

        if ($resumen === null) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => 'No hay partidos con resultado cargado para la fecha seleccionada.',
            ], 422);
        }

        $totalNotificables = $this->usuariosEmailResumenQuery()->count();
        $totalPermitidos = min($totalNotificables, self::MAX_DESTINATARIOS_RESUMEN_FECHA);
        $correosEstimados = (int) ceil($totalPermitidos / self::MAX_DESTINATARIOS_BCC);

        return response()->json([
            'success' => true,
            'count' => $totalPermitidos,
            'partidos_count' => $resumen['partidos_count'],
            'fecha_nombre' => $resumen['fecha_nombre'],
            'categoria_nombre' => $resumen['categoria_nombre'],
            'limite_operativo' => self::MAX_DESTINATARIOS_RESUMEN_FECHA,
            'lote_bcc' => self::MAX_DESTINATARIOS_BCC,
            'correos_estimados' => $correosEstimados,
            'message' => "Se enviará a {$totalPermitidos} usuario(s).",
        ]);
    }

    /**
     * Envía en lotes el correo resumen con todos los resultados de una fecha.
     *
     * El envío está acotado por un límite operativo para respetar cuotas
     * diarias del proveedor SMTP y reducir riesgo de bloqueos.
     */
    public function enviarLoteResumenFecha(Request $request)
    {
        $validated = $request->validate([
            'idFecha' => ['required', 'integer', 'exists:fechas,id'],
            'idCategoria' => ['nullable', 'integer', 'exists:categorias,id'],
            'titulo' => ['nullable', 'string', 'max:120'],
            'mensaje' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:255'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:' . self::MAX_DESTINATARIOS_BCC],
        ]);

        $idEdicion = $this->obtenerIdUltimaEdicion();
        if ($idEdicion === null) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 1,
                'remaining' => 0,
                'message' => 'No hay edición activa para enviar resúmenes.',
            ], 422);
        }

        $resumen = $this->obtenerResumenResultadosFecha(
            $idEdicion,
            (int) $validated['idFecha'],
            isset($validated['idCategoria']) ? (int) $validated['idCategoria'] : null
        );

        if ($resumen !== null) {
            if (! empty($validated['titulo'])) {
                $resumen['titulo_personalizado'] = $validated['titulo'];
            }

            if (! empty($validated['mensaje'])) {
                $resumen['mensaje_personalizado'] = $validated['mensaje'];
            }

            if (! empty($validated['url'])) {
                $resumen['url_fixture'] = $validated['url'];
            }
        }

        if ($resumen === null) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'remaining' => 0,
                'message' => 'No hay resultados para enviar en esa fecha.',
            ], 422);
        }

        $offset = (int) $validated['offset'];
        $limit = (int) $validated['limit'];

        $idsDestinatarios = $this->usuariosEmailResumenQuery()
            ->orderBy('id')
            ->limit(self::MAX_DESTINATARIOS_RESUMEN_FECHA)
            ->pluck('id')
            ->values();

        $totalDestinatarios = $idsDestinatarios->count();
        if ($totalDestinatarios === 0) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'remaining' => 0,
                'message' => 'No hay destinatarios activos para notificar.',
            ]);
        }

        $idsLote = $idsDestinatarios->slice($offset, $limit)->values();
        if ($idsLote->isEmpty()) {
            return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'remaining' => 0,
                'message' => 'Fin del proceso.',
            ]);
        }

        $usuarios = \App\Models\User::query()
            ->whereIn('id', $idsLote)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $enviados = 0;
        $errores = [];
        $lastError = null;
        $contadorEmail = 0;

        $emails = $usuarios->pluck('email')
            ->filter(static fn ($email) => ! empty($email))
            ->unique()
            ->values()
            ->all();

        if ($emails !== []) {
            try {
                $mailFrom = config('mail.from.address') ?: 'info@torneosmitai.com';

                Mail::to($mailFrom)
                    ->bcc($emails)
                    ->send(new \App\Mail\ResumenResultadosFechaMail($resumen, route('email.baja-general.form')));

                $enviados = count($emails);
                $contadorEmail = $enviados;

                \App\Models\User::query()
                    ->whereIn('id', $usuarios->pluck('id'))
                    ->update(['last_notification_at' => now()]);
            } catch (\Exception $e) {
                $errores = $emails;
                $lastError = $e->getMessage();
                Log::channel('single')->error('Error enviando resumen fecha por BCC', [
                    'error' => $e->getMessage(),
                    'idEdicion' => $idEdicion,
                    'idFecha' => $validated['idFecha'],
                    'idCategoria' => $validated['idCategoria'] ?? null,
                ]);
            }
        }

        $remaining = max(0, $totalDestinatarios - ($offset + $usuarios->count()));

        return response()->json([
            'done' => $remaining === 0,
            'sent' => $enviados,
            'errors' => count($errores),
            'last_error' => $lastError,
            'remaining' => $remaining,
            'push_count' => 0,
            'email_count' => $contadorEmail,
            'partidos_count' => $resumen['partidos_count'],
            'message' => "Lote procesado ({$contadorEmail} emails enviados por BCC).",
        ]);
    }

    /**
     * Obtiene el identificador de la última edición disponible.
     *
     * Se centraliza esta consulta porque el resumen consolidado siempre debe
     * enviarse sobre la edición más reciente, evitando errores por filtros
     * de UI desactualizados o parámetros manipulados manualmente.
     */
    private function obtenerIdUltimaEdicion(): ?int
    {
        $idEdicion = \App\Models\Edicion::query()
            ->orderByDesc('id')
            ->value('id');

        return $idEdicion !== null ? (int) $idEdicion : null;
    }

    /**
     * Registrar finalización del envío masivo en el log.
     */
    public function logEnvioCompleto(Request $request)
    {
        $enviados = $request->input('enviados', 0);
        $errores = $request->input('errores', 0);
        $tipo = $request->input('tipo', 'Gerenal');

        Log::channel('single')->info('==========================================');
        Log::channel('single')->info("PROCESO DE ENVÍO MASIVO COMPLETADO ($tipo)");
        Log::channel('single')->info("Total Enviados: {$enviados}");
        Log::channel('single')->info("Total Errores: {$errores}");
        Log::channel('single')->info('Admin Responsable: ' . $request->user()->name . ' (ID: ' . $request->user()->id . ')');
        Log::channel('single')->info('==========================================');

        return response()->json(['status' => 'ok']);
    }

    /**
     * Enviar notificación de resultado cargado por lotes.
     */
    public function enviarLoteResultado(Request $request)
    {
        $validated = $request->validate([
            'partido_id' => ['required', 'integer', 'exists:partidos,id'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'];
        $offset = $validated['offset'];
        $partidoId = $validated['partido_id'];

        $partido = \App\Models\Partido::with(['equipoLocal', 'equipoVisitante', 'categoria'])
            ->findOrFail($partidoId);
        
        // Preparar datos del partido para la notificación
        $datosPartido = (object) [
                'id' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'nombre_local' => $partido->equipoLocal->nombre ?? 'Equipo Local',
                'nombre_visitante' => $partido->equipoVisitante->nombre ?? 'Equipo Visitante',
                'golesEquipoLocal' => $partido->golesEquipoLocal,
                'golesEquipoVisitante' => $partido->golesEquipoVisitante,
                'nombre_categoria' => $partido->categoria->nombreCategoria ?? '',
        ];

        $notificacion = new \App\Notifications\ResultadoCargado($datosPartido);

        $usuarios = \App\Models\User::query()
            ->whereIn('id', $this->usuariosNotificablesQuery()->select('id'))
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($usuarios->isEmpty()) {
             return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'message' => 'Fin del proceso.'
            ]);
        }

        $enviados = 0;
        $errores = [];
        $lastError = null;
        $contadorPush = 0;
        $contadorEmail = 0;

        foreach ($usuarios as $usuario) {
            try {
                $usuario->notify($notificacion);
                // Actualizar timestamp
                $usuario->forceFill(['last_notification_at' => now()])->saveQuietly();
                $enviados++;
                 if ($usuario->pushSubscriptions()->exists()) $contadorPush++;
                 if ($usuario->quiereRecibirEmails()) $contadorEmail++;
            } catch (\Exception $e) {
                $errores[] = $usuario->email;
                $lastError = $e->getMessage();
                Log::channel('single')->error("Error notif resultado user {$usuario->id}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'done' => $usuarios->count() < $limit,
            'sent' => $enviados,
            'errors' => count($errores),
            'last_error' => $lastError,
            'push_count' => $contadorPush,
            'email_count' => $contadorEmail
        ]);
    }

    /**
     * Enviar notificación de resultado de instancia final cargado por lotes.
     */
    public function enviarLoteResultadoInstancia(Request $request)
    {
        $validated = $request->validate([
            'partido_id' => ['required', 'integer', 'exists:instancias_finales,id'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'];
        $offset = $validated['offset'];
        $partidoId = $validated['partido_id'];

        $partido = \App\Models\InstanciaFinal::with(['equipoLocal', 'equipoVisitante', 'categoria', 'fase'])
            ->findOrFail($partidoId);
        
        // Preparar datos del partido para la notificación
        $nombreFase = $partido->fase->nombre ?? '';
        $datosPartido = (object) [
                'id' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'nombre_local' => $partido->equipoLocal->nombre ?? 'Equipo Local',
                'nombre_visitante' => $partido->equipoVisitante->nombre ?? 'Equipo Visitante',
                'golesEquipoLocal' => $partido->golesEquipoLocal,
                'golesEquipoVisitante' => $partido->golesEquipoVisitante,
                'nombre_categoria' => ($partido->categoria->nombreCategoria ?? '') . ($nombreFase ? " - {$nombreFase}" : ''),
        ];

        $notificacion = new \App\Notifications\ResultadoCargado($datosPartido);

        $usuarios = \App\Models\User::query()
            ->whereIn('id', $this->usuariosNotificablesQuery()->select('id'))
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($usuarios->isEmpty()) {
             return response()->json([
                'done' => true,
                'sent' => 0,
                'errors' => 0,
                'message' => 'Fin del proceso.'
            ]);
        }

        $enviados = 0;
        $errores = [];
        $lastError = null;
        $contadorPush = 0;
        $contadorEmail = 0;

        foreach ($usuarios as $usuario) {
            try {
                $usuario->notify($notificacion);
                // Actualizar timestamp
                $usuario->forceFill(['last_notification_at' => now()])->saveQuietly();
                $enviados++;
                 if ($usuario->pushSubscriptions()->exists()) $contadorPush++;
                 if ($usuario->quiereRecibirEmails()) $contadorEmail++;
            } catch (\Exception $e) {
                $errores[] = $usuario->email;
                $lastError = $e->getMessage();
                Log::channel('single')->error("Error notif resultado instancia user {$usuario->id}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'done' => $usuarios->count() < $limit,
            'sent' => $enviados,
            'errors' => count($errores),
            'last_error' => $lastError,
            'push_count' => $contadorPush,
            'email_count' => $contadorEmail
        ]);
    }

    /**
     * Obtiene el resumen consolidado de resultados para una fecha de liga.
     *
     * Se usa para enviar un único correo por usuario con todos los marcadores
     * de la fecha seleccionada y así reducir el volumen total de envíos.
     *
     * @return array<string, mixed>|null
     */
    private function obtenerResumenResultadosFecha(int $idEdicion, int $idFecha, ?int $idCategoria = null): ?array
    {
        $fecha = \App\Models\Fecha::query()
            ->where('idEdicion', $idEdicion)
            ->whereKey($idFecha)
            ->first();

        if (! $fecha) {
            return null;
        }

        $idCategoriaFinal = $idCategoria ?: (int) $fecha->idCategoria;

        $consulta = \App\Models\Partido::query()
            ->with(['equipoLocal:id,nombre', 'equipoVisitante:id,nombre', 'grupo:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $idFecha)
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante');

        if ($idCategoriaFinal > 0) {
            $consulta->where('idCategoria', $idCategoriaFinal);
        }

        $partidos = $consulta
            ->orderBy('idGrupo')
            ->orderBy('horario_datetime')
            ->orderBy('id')
            ->get();

        if ($partidos->isEmpty()) {
            return null;
        }

        $edicionNombre = \App\Models\Edicion::query()
            ->whereKey($idEdicion)
            ->value('nombre') ?? 'Edición vigente';

        $categoriaNombre = null;
        if ($idCategoriaFinal > 0) {
            $categoriaNombre = \App\Models\Categoria::query()
                ->whereKey($idCategoriaFinal)
                ->value('nombreCategoria');
        }

        return [
            'id_edicion' => $idEdicion,
            'id_fecha' => $idFecha,
            'id_categoria' => $idCategoriaFinal > 0 ? $idCategoriaFinal : null,
            'edicion_nombre' => $edicionNombre,
            'fecha_nombre' => $fecha->nombre,
            'categoria_nombre' => $categoriaNombre,
            'url_fixture' => '/fixture?idEdicion=' . $idEdicion,
            'partidos_count' => $partidos->count(),
            'partidos' => $partidos->map(function ($partido): array {
                return [
                    'id' => $partido->id,
                    'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
                    'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
                    'goles_local' => (int) $partido->golesEquipoLocal,
                    'goles_visitante' => (int) $partido->golesEquipoVisitante,
                    'grupo' => $this->formatearEtiquetaGrupo($partido->grupo->nombre ?? null),
                    'cancha' => $partido->cancha ? ('Cancha ' . $partido->cancha) : null,
                    'horario' => $partido->horario_datetime
                        ? \Carbon\Carbon::parse($partido->horario_datetime)->format('d/m H:i')
                        : ($partido->horario ?: null),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Formatea el nombre de grupo evitando duplicar el prefijo "Grupo".
     *
     * Hay ediciones legacy donde el nombre ya viene como "Grupo Único" y en
     * otras solo viene la letra o código. Este helper unifica la etiqueta para
     * la plantilla sin mostrar textos repetidos.
     */
    private function formatearEtiquetaGrupo(?string $nombreGrupo): ?string
    {
        if ($nombreGrupo === null) {
            return null;
        }

        $nombreNormalizado = trim($nombreGrupo);
        if ($nombreNormalizado === '') {
            return null;
        }

        if (preg_match('/^grupo\b/i', $nombreNormalizado) === 1) {
            return $nombreNormalizado;
        }

        return 'Grupo ' . $nombreNormalizado;
    }

    /**
     * Construye el título final evitando repetir el ícono al inicio.
     *
     * Algunas acciones predefinidas ya traen un título con emoji y, además,
     * envían el ícono en un campo separado. Este helper evita duplicados
     * visuales como "📅 📅 Fixture Actualizado" en asunto y encabezado.
     */
    private function construirTituloPersonalizado(string $titulo, ?string $icono = null): string
    {
        $tituloNormalizado = trim($titulo);
        $iconoNormalizado = trim((string) $icono);

        if ($iconoNormalizado === '') {
            return $tituloNormalizado;
        }

        if ($tituloNormalizado === '') {
            return $iconoNormalizado;
        }

        if (str_starts_with($tituloNormalizado, $iconoNormalizado)) {
            return $tituloNormalizado;
        }

        return $iconoNormalizado . ' ' . $tituloNormalizado;
    }

    /**
     * Base de usuarios destinatarios para resúmenes por email.
     *
     * Solo considera usuarios con email cargado y suscripción activa,
     * ya que el resumen se envía por BCC para optimizar cupos SMTP.
     */
    private function usuariosEmailResumenQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\User::query()
            ->where('email_subscribed', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');
    }

    /**
     * Define la base de usuarios aptos para recibir notificaciones.
     *
     * Se considera notificable a quien cumpla al menos una condición:
     * - Tiene email habilitado (`email_subscribed`) y correo cargado.
     * - Tiene una o más suscripciones push activas.
     */
    private function usuariosNotificablesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\User::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('email_subscribed', true)
                        ->whereNotNull('email');
                })
                    ->orWhereHas('pushSubscriptions');
            });
    }
}
