<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\User;
use App\Services\EmailDomainGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controlador para gestionar las notificaciones por email.
 */
class ControladorNotificacionesEmail extends Controller
{
    /**
     * Listar usuarios para gestión de notificaciones.
     * Solo accesible por administradores.
     *
     * Muestra una tabla con todos los usuarios, permitiendo filtrar por:
     * - Búsqueda de texto (nombre/email)
     * - Estado de notificación Email
     * - Estado de verificación de Email
     *
     * @return View
     */
    public function listarUsuariosNotificaciones(Request $request)
    {
        $query = User::query()
            ->orderBy('created_at', 'desc');

        // Filtro por búsqueda (nombre o email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
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
        $ediciones = Edicion::orderBy('id', 'desc')->get();
        $EdicionSeleccionada = $ediciones->firstWhere('id', (int) $request->query('idEdicion')) ?? $ediciones->first();

        $resumenEdicionActual = $ediciones->first();
        $resumenIdEdicion = (int) ($resumenEdicionActual->id ?? 0);
        $resumenNombreEdicion = (string) ($resumenEdicionActual->nombre ?? 'Edición vigente');
        $resumenIdCategoria = $request->query('resumen_idCategoria');
        $resumenIdFecha = $request->query('resumen_idFecha');

        $categoriasResumen = collect();
        $fechasResumen = collect();
        $fechasEdicion = collect();

        if ($resumenIdEdicion > 0) {
            $categoriasResumen = Categoria::query()
                ->where('idEdicion', $resumenIdEdicion)
                ->orderBy('nombreCategoria')
                ->get(['id', 'nombreCategoria']);

            $consultaFechas = Fecha::query()
                ->where('idEdicion', $resumenIdEdicion);

            if (! empty($resumenIdCategoria)) {
                $consultaFechas->where('idCategoria', $resumenIdCategoria);
            }

            $fechasResumen = $consultaFechas
                ->orderBy('id')
                ->get(['id', 'nombre', 'idCategoria']);

            // La próxima fecha filtra por categoría en el navegador: necesita todas las fechas.
            $fechasEdicion = Fecha::query()
                ->where('idEdicion', $resumenIdEdicion)
                ->orderBy('id')
                ->get(['id', 'nombre', 'idCategoria']);
        }

        return view('Panel.suscriptores-email', compact(
            'usuarios',
            'ediciones',
            'EdicionSeleccionada',
            'categoriasResumen',
            'fechasResumen',
            'fechasEdicion',
            'resumenIdEdicion',
            'resumenNombreEdicion',
            'resumenIdCategoria',
            'resumenIdFecha',
        ));
    }

    /**
     * Reenviar manualmente el correo de verificación a un usuario.
     * Solo accesible por administradores.
     *
     * @param  int  $id  ID del usuario
     * @return RedirectResponse
     */
    public function reenviarVerificacionManual(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', "El usuario {$user->name} ya tiene el email verificado.");
        }

        if (EmailDomainGuard::isBlocked((string) $user->email)) {
            return back()->with('error', 'El dominio del correo del usuario está bloqueado para envíos automáticos.');
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
     * @return JsonResponse
     */
    public function toggleEmailSubscription(Request $request)
    {
        $user = $request->user();

        // Alternar el estado de suscripción
        $user->email_subscribed = ! $user->email_subscribed;
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
                : 'Notificaciones por email desactivadas',
        ]);
    }

    /**
     * Obtener el estado actual de suscripción a emails del usuario.
     */
    public function estadoEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'email_subscribed' => (bool) $user->email_subscribed,
            'email_verified' => $user->hasVerifiedEmail(),
            'puede_recibir_emails' => $user->quiereRecibirEmails(),
        ]);
    }

    /**
     * Dar de baja a un usuario de notificaciones por email.
     *
     * Ruta pública que permite a usuarios darse de baja sin iniciar sesión,
     * usando un token de verificación basado en sha1 del email.
     * Esto se usa en los enlaces de baja incluidos en cada email.
     *
     * @param  int  $id  ID del usuario
     * @param  string  $token  Token de verificación (sha1 del email)
     * @return RedirectResponse
     */
    public function darseDeBajaEmail(Request $request)
    {
        $id = $request->query('id');
        $token = $request->query('token');

        if (! $id || ! $token) {
            return redirect('/')->with('error', 'Enlace incompleto.');
        }

        $user = User::find($id);

        // Verificar que el usuario existe
        if (! $user) {
            return redirect('/')->with('error', 'Usuario no encontrado.');
        }

        // Verificar el token de seguridad
        $tokenEsperado = sha1($user->email);
        if (! hash_equals($tokenEsperado, $token)) {
            Log::channel('single')->warning('Intento de baja con token inválido', [
                'user_id' => $id,
                'ip' => request()->ip(),
            ]);

            return redirect('/')->with('error', 'Enlace inválido o expirado.');
        }

        // Si ya está dado de baja, informar
        if (! $user->email_subscribed) {
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

        $usuario = User::query()
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
     * Endpoint POST para one-click unsubscribe segun RFC 8058.
     *
     * Gmail, Outlook y otros clientes envian un POST automatico cuando
     * el usuario hace clic en "Cancelar suscripcion" desde la interfaz del
     * proveedor de correo. Este handler procesa esa solicitud sin
     * redireccion (responde 200 OK).
     *
     * @param  Request  $request  Con query params 'id' y 'token'
     * @return Response
     */
    public function oneClickUnsubscribe(Request $request)
    {
        $id = $request->query('id') ?? $request->input('id');
        $token = $request->query('token') ?? $request->input('token');

        if (! $id || ! $token) {
            return response('Enlace incompleto.', 400);
        }

        $user = User::find($id);

        if (! $user) {
            return response('OK', 200);
        }

        $tokenEsperado = sha1($user->email);
        if (! hash_equals($tokenEsperado, $token)) {
            Log::channel('single')->warning('Intento de one-click unsubscribe con token inválido', [
                'user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return response('OK', 200);
        }

        if ($user->email_subscribed) {
            $user->email_subscribed = false;
            $user->save();

            Log::channel('single')->info('Usuario dado de baja via one-click unsubscribe (RFC 8058)', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
        }

        return response('OK', 200);
    }
}
