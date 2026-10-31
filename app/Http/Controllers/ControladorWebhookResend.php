<?php

namespace App\Http\Controllers;

use App\Services\FirmaWebhookResend;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recibe de Resend las bajas y cambios de tópicos que el suscriptor hace desde allá
 * (enlace de baja de Resend, botón de Gmail), para que el estado local no los pise al sincronizar.
 */
class ControladorWebhookResend extends Controller
{
    private const EVENTOS = ['contact.updated', 'contact.deleted', 'contact.topics.updated'];

    public function __invoke(Request $request, FirmaWebhookResend $firma, SuscripcionService $suscripciones): Response
    {
        $idEvento = $request->header('svix-id');

        if (! $firma->esValida($request->getContent(), $idEvento, $request->header('svix-timestamp'), $request->header('svix-signature'))) {
            Log::warning('Webhook de Resend con firma inválida.', ['ip' => $request->ip()]);

            return response('', 401);
        }

        $tipo = (string) $request->json('type');
        $datos = $request->json('data');

        if (! in_array($tipo, self::EVENTOS, true) || ! is_array($datos)) {
            return response('', 204);
        }

        // Resend reintenta los envíos que no confirmamos: el mismo evento puede llegar más de una vez.
        $claveEvento = 'resend-webhook:'.$idEvento;
        if (! Cache::add($claveEvento, true, now()->addDay())) {
            return response('', 204);
        }

        try {
            $suscripciones->aplicarCambioDesdeResend($tipo, $datos, Carbon::parse((string) $request->json('created_at', 'now')));
        } catch (Throwable $error) {
            // Sin marcarlo como procesado: Resend lo vuelve a mandar.
            Cache::forget($claveEvento);

            throw $error;
        }

        return response('', 204);
    }
}
