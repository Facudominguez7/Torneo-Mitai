<?php

namespace App\Http\Controllers;

use App\Services\FirmaWebhookResend;
use App\Services\SuscripcionService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Recibe de Resend las bajas y cambios de tópicos que el suscriptor hace desde allá
 * (enlace de baja de Resend, botón de Gmail) y los rebotes y quejas de los correos enviados,
 * para que el estado local no los pise al sincronizar.
 */
class ControladorWebhookResend extends Controller
{
    private const EVENTOS_CONTACTO = ['contact.updated', 'contact.deleted', 'contact.topics.updated'];

    private const EVENTOS_ENVIO = ['email.bounced', 'email.complained'];

    public function __invoke(Request $request, FirmaWebhookResend $firma, SuscripcionService $suscripciones): Response
    {
        $idEvento = $request->header('svix-id');

        if (! $firma->esValida($request->getContent(), $idEvento, $request->header('svix-timestamp'), $request->header('svix-signature'))) {
            Log::warning('Webhook de Resend con firma inválida.', ['ip' => $request->ip()]);

            return response('', 401);
        }

        $tipo = (string) $request->json('type');
        $datos = $request->json('data');
        $esEventoDeEnvio = in_array($tipo, self::EVENTOS_ENVIO, true);

        if ((! $esEventoDeEnvio && ! in_array($tipo, self::EVENTOS_CONTACTO, true)) || ! is_array($datos)) {
            return response('', 204);
        }

        // Resend reintenta los envíos que no confirmamos: el mismo evento puede llegar más de una vez.
        // Se marca recién después de aplicarlo, así un corte a mitad de camino deja que Resend lo reintente.
        $claveEvento = 'resend-webhook:'.$idEvento;
        if (Cache::has($claveEvento)) {
            return response('', 204);
        }

        try {
            $emitido = Carbon::parse((string) $request->json('created_at'));
        } catch (InvalidFormatException) {
            Log::warning('Webhook de Resend con fecha ilegible.', ['tipo' => $tipo]);

            return response('', 204);
        }

        if ($esEventoDeEnvio) {
            $suscripciones->registrarReboteDesdeResend($tipo, $datos, $emitido);
        } else {
            $suscripciones->aplicarCambioDesdeResend($tipo, $datos, $emitido);
        }

        Cache::put($claveEvento, true, now()->addDay());

        return response('', 204);
    }
}
