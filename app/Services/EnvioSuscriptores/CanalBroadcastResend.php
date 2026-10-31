<?php

namespace App\Services\EnvioSuscriptores;

use App\Mail\ResumenResultadosFechaMail;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Services\EmailDeliverabilityHeaders;
use Resend\Client;
use Resend\Exceptions\ErrorException;

/**
 * Producción: un único broadcast de Resend al segmento, acotado al tópico del aviso; no consume el
 * cupo diario de correos individuales. Se crea como borrador, se guarda su id y recién después se
 * manda, así un reintento reutiliza ese borrador y no lo manda dos veces.
 */
class CanalBroadcastResend implements CanalEnvioSuscriptores
{
    /** El broadcast lleva el mismo HTML para todos: el enlace de cada uno sale de su contacto. */
    public const URL_PREFERENCIAS_CONTACTO = '{{{contact.url_preferencias}}}';

    public function __construct(private readonly Client $resend) {}

    public function enviarResumenFecha(EnvioSuscriptores $envio, array $resumen): int
    {
        $contenido = $this->contenido($resumen);
        $estadoEnResend = $envio->broadcast_id === null ? null : $this->estado($envio->broadcast_id);

        if ($estadoEnResend === null) {
            $borrador = $this->resend->broadcasts->create([
                'segment_id' => (string) config('suscripciones.resend.segmento'),
                'topic_id' => (string) config('suscripciones.resend.topicos.resumen_fecha'),
                ...$contenido,
            ]);
            $envio->forceFill(['broadcast_id' => $borrador->id])->save();
        } elseif ($estadoEnResend === 'draft') {
            // El borrador de un intento anterior se actualiza: los resultados pueden haber cambiado.
            $this->resend->broadcasts->update($envio->broadcast_id, $contenido);
        } else {
            // El intento anterior lo mandó pero no llegó a guardarlo.
            return Suscriptor::query()->recibenResumenFecha()->count();
        }

        $this->resend->broadcasts->send($envio->broadcast_id, []);

        return Suscriptor::query()->recibenResumenFecha()->count();
    }

    /** null si el borrador ya no existe en Resend (lo borraron): se crea otro. */
    private function estado(string $idBroadcast): ?string
    {
        try {
            return $this->resend->broadcasts->get($idBroadcast)->status;
        } catch (ErrorException $error) {
            if ($error->getErrorCode() === 404) {
                return null;
            }

            throw $error;
        }
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array<string, string>
     */
    private function contenido(array $resumen): array
    {
        $correo = new ResumenResultadosFechaMail($resumen, self::URL_PREFERENCIAS_CONTACTO);

        return [
            'from' => EmailDeliverabilityHeaders::fromName().' <'.config('suscripciones.remitente').'>',
            'reply_to' => EmailDeliverabilityHeaders::replyToAddress(),
            'subject' => $correo->asunto(),
            'name' => $correo->asunto(),
            'html' => $correo->render(),
            'text' => view('emails.resumen-resultados-fecha-text', [
                'resumen' => $resumen,
                'urlBajaGeneral' => self::URL_PREFERENCIAS_CONTACTO,
            ])->render(),
        ];
    }
}
