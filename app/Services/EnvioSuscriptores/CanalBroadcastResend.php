<?php

namespace App\Services\EnvioSuscriptores;

use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Services\EmailDeliverabilityHeaders;
use Closure;
use Illuminate\Mail\Mailable;
use Resend\Client;
use Resend\Exceptions\ErrorException;

/**
 * Producción: un único broadcast de Resend al segmento, acotado al tópico del aviso (sin tópico, a
 * todo el segmento: Resend igual excluye a los dados de baja); no consume el
 * cupo diario de correos individuales. Se crea como borrador, se guarda su id y recién después se
 * manda, así un reintento reutiliza ese borrador y no lo manda dos veces.
 */
class CanalBroadcastResend implements CanalEnvioSuscriptores
{
    /** El broadcast lleva el mismo HTML para todos: el enlace de cada uno sale de su contacto. */
    public const URL_PREFERENCIAS_CONTACTO = '{{{contact.url_preferencias}}}';

    public function __construct(private readonly Client $resend) {}

    public function enviar(EnvioSuscriptores $envio, ?string $aviso, Closure $correo, string $vistaTexto): int
    {
        $contenido = $this->contenido($correo(self::URL_PREFERENCIAS_CONTACTO), $vistaTexto);
        $estadoEnResend = $envio->broadcast_id === null ? null : $this->estado($envio->broadcast_id);

        if ($estadoEnResend === null) {
            $audiencia = ['segment_id' => (string) config('suscripciones.resend.segmento')];
            if ($aviso !== null) {
                $audiencia['topic_id'] = (string) config("suscripciones.resend.topicos.{$aviso}");
            }

            $borrador = $this->resend->broadcasts->create([...$audiencia, ...$contenido]);
            $envio->forceFill(['broadcast_id' => $borrador->id])->save();
        } elseif ($estadoEnResend === 'draft') {
            // El borrador de un intento anterior se actualiza: el contenido puede haber cambiado.
            $this->resend->broadcasts->update($envio->broadcast_id, $contenido);
        } else {
            // El intento anterior lo mandó pero no llegó a guardarlo.
            return Suscriptor::query()->recibenAviso($aviso)->count();
        }

        $this->resend->broadcasts->send($envio->broadcast_id, []);

        return Suscriptor::query()->recibenAviso($aviso)->count();
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
     * El asunto, el HTML y los datos del texto plano salen del correo ya armado (render() corre su build()).
     *
     * @return array<string, string>
     */
    private function contenido(Mailable $correo, string $vistaTexto): array
    {
        $html = $correo->render();

        return [
            'from' => EmailDeliverabilityHeaders::fromName().' <'.config('suscripciones.remitente').'>',
            'reply_to' => EmailDeliverabilityHeaders::replyToAddress(),
            'subject' => (string) $correo->subject,
            'name' => (string) $correo->subject,
            'html' => $html,
            'text' => view($vistaTexto, $correo->buildViewData())->render(),
        ];
    }
}
