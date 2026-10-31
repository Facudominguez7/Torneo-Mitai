<?php

namespace App\Services;

use App\Mail\ResumenResultadosFechaMail;
use App\Models\Suscriptor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Resend\Client;
use Throwable;

/**
 * Envía los avisos masivos a los suscriptores. Con Resend sale un único broadcast al segmento,
 * acotado al tópico del aviso: no consume el cupo diario de correos individuales. Con la
 * sincronización apagada (MailHog en local) se envía un correo por suscriptor con el mailer.
 */
class EnvioSuscriptoresService
{
    /** El broadcast lleva el mismo HTML para todos: el enlace de cada uno sale de su contacto. */
    public const URL_PREFERENCIAS_CONTACTO = '{{{contact.url_preferencias}}}';

    public const MINUTOS_ENTRE_ENVIOS = 10;

    public function __construct(
        private readonly Client $resend,
        private readonly SuscripcionService $suscripciones,
    ) {}

    /**
     * @param  array<string, mixed>  $resumen  resumen armado por ControladorNotificacionesEmail
     * @return array{canal: string, broadcast_id: ?string, enviados: int}|null null si ese resumen ya se envió hace poco
     */
    public function enviarResumenFecha(array $resumen): ?array
    {
        $candado = Cache::lock(
            "suscriptores:resumen-fecha:{$resumen['id_fecha']}:".($resumen['id_categoria'] ?? 'todas'),
            self::MINUTOS_ENTRE_ENVIOS * 60,
        );

        // El candado no se libera al terminar: evita un segundo envío por doble clic o recarga.
        if (! $candado->get()) {
            return null;
        }

        try {
            return config('suscripciones.resend.sincronizar')
                ? $this->enviarBroadcast($resumen)
                : $this->enviarPorMailer($resumen);
        } catch (Throwable $error) {
            $candado->release();

            throw $error;
        }
    }

    /** Suscriptores que reciben el resumen de cada fecha. */
    public function destinatariosResumenFecha(): int
    {
        return Suscriptor::query()->activos()->where('recibe_resumen_fecha', true)->count();
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array{canal: string, broadcast_id: string, enviados: int}
     */
    private function enviarBroadcast(array $resumen): array
    {
        $correo = new ResumenResultadosFechaMail($resumen, self::URL_PREFERENCIAS_CONTACTO);

        $broadcast = $this->resend->broadcasts->create([
            'segment_id' => (string) config('suscripciones.resend.segmento'),
            'topic_id' => (string) config('suscripciones.resend.topicos.resumen_fecha'),
            'from' => EmailDeliverabilityHeaders::fromName().' <'.config('suscripciones.remitente').'>',
            'reply_to' => EmailDeliverabilityHeaders::replyToAddress(),
            'subject' => $correo->asunto(),
            'name' => $correo->asunto(),
            'html' => $correo->render(),
            'text' => view('emails.resumen-resultados-fecha-text', [
                'resumen' => $resumen,
                'urlBajaGeneral' => self::URL_PREFERENCIAS_CONTACTO,
            ])->render(),
            'send' => true,
        ]);

        return ['canal' => 'resend', 'broadcast_id' => $broadcast->id, 'enviados' => $this->destinatariosResumenFecha()];
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array{canal: string, broadcast_id: null, enviados: int}
     */
    private function enviarPorMailer(array $resumen): array
    {
        $enviados = 0;

        Suscriptor::query()->activos()->where('recibe_resumen_fecha', true)
            ->chunkById(100, function ($suscriptores) use ($resumen, &$enviados): void {
                foreach ($suscriptores as $suscriptor) {
                    Mail::mailer(config('suscripciones.mailer'))->to($suscriptor->email)->send(
                        new ResumenResultadosFechaMail($resumen, $this->suscripciones->urlPreferencias($suscriptor)),
                    );
                    $enviados++;
                }
            });

        return ['canal' => 'mailer', 'broadcast_id' => null, 'enviados' => $enviados];
    }
}
