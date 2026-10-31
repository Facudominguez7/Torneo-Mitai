<?php

namespace App\Services;

use App\Jobs\EnviarResumenFechaSuscriptores;
use App\Mail\ResumenResultadosFechaMail;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Resend\Client;
use Resend\Exceptions\ErrorException;
use Throwable;

/**
 * Envía los avisos masivos a los suscriptores. El pedido del panel solo registra el envío y lo
 * encola; el job arma el broadcast de Resend (un único envío al segmento, acotado al tópico del
 * aviso, que no consume el cupo diario de correos individuales). Con la sincronización apagada
 * (MailHog en local) se manda un correo por suscriptor con el mailer.
 */
class EnvioSuscriptoresService
{
    /** El broadcast lleva el mismo HTML para todos: el enlace de cada uno sale de su contacto. */
    public const URL_PREFERENCIAS_CONTACTO = '{{{contact.url_preferencias}}}';

    public function __construct(
        private readonly Client $resend,
        private readonly SuscripcionService $suscripciones,
        private readonly ResumenFechaService $resumenes,
    ) {}

    /**
     * Registra el envío del resumen y lo encola. Un resumen de la misma fecha y categoría que ya
     * salió o está en cola no se vuelve a enviar; uno que falló sí se puede reintentar.
     *
     * @param  array<string, mixed>  $resumen  armado por ResumenFechaService
     * @return EnvioSuscriptores|null null si ese resumen ya se envió o está en cola
     */
    public function registrarResumenFecha(array $resumen, ?User $administrador): ?EnvioSuscriptores
    {
        $clave = EnvioSuscriptores::claveResumenFecha((int) $resumen['id_fecha'], $resumen['id_categoria']);
        $datos = [
            'usuario_id' => $administrador?->id,
            'estado' => EnvioSuscriptores::ESTADO_PENDIENTE,
            'destinatarios' => $this->destinatariosResumenFecha(),
        ];

        try {
            $envio = EnvioSuscriptores::query()->create([
                ...$datos,
                'tipo' => EnvioSuscriptores::TIPO_RESUMEN_FECHA,
                'clave' => $clave,
                'idEdicion' => (int) $resumen['id_edicion'],
                'idFecha' => (int) $resumen['id_fecha'],
                'idCategoria' => $resumen['id_categoria'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Solo un pedido puede reactivar el que falló: el update condicionado es atómico.
            $reactivados = EnvioSuscriptores::query()
                ->where('clave', $clave)
                ->where('estado', EnvioSuscriptores::ESTADO_FALLIDO)
                ->update($datos);

            if ($reactivados === 0) {
                return null;
            }

            $envio = EnvioSuscriptores::query()->where('clave', $clave)->firstOrFail();
        }

        try {
            EnviarResumenFechaSuscriptores::dispatch($envio->id);
        } catch (Throwable $error) {
            // Sin job en la cola nadie lo mandaría: queda fallido para poder pedirlo de nuevo.
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            throw $error;
        }

        return $envio;
    }

    /** El envío registrado del resumen de esa fecha y categoría, si existe. */
    public function envioResumenFecha(int $idFecha, ?int $idCategoria): ?EnvioSuscriptores
    {
        return EnvioSuscriptores::query()->where('clave', EnvioSuscriptores::claveResumenFecha($idFecha, $idCategoria))->first();
    }

    /** Suscriptores que reciben el resumen de cada fecha. */
    public function destinatariosResumenFecha(): int
    {
        return Suscriptor::query()->activos()->where('recibe_resumen_fecha', true)->count();
    }

    /**
     * Lo corre el job. Es seguro reintentarlo: el broadcast se crea como borrador, se guarda su id
     * y recién después se manda; un reintento reutiliza ese borrador y no lo manda dos veces.
     */
    public function procesarResumenFecha(EnvioSuscriptores $envio): void
    {
        if ($envio->estado !== EnvioSuscriptores::ESTADO_PENDIENTE) {
            return;
        }

        $resumen = $this->resumenes->armar($envio->idEdicion, (int) $envio->idFecha, $envio->idCategoria);

        if ($resumen === null) {
            $envio->forceFill(['estado' => EnvioSuscriptores::ESTADO_FALLIDO])->save();

            return;
        }

        $destinatarios = config('suscripciones.resend.sincronizar')
            ? $this->enviarBroadcast($envio, $resumen)
            : $this->enviarPorMailer($resumen);

        $envio->forceFill([
            'estado' => EnvioSuscriptores::ESTADO_ENVIADO,
            'destinatarios' => $destinatarios,
            'enviado_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function enviarBroadcast(EnvioSuscriptores $envio, array $resumen): int
    {
        $contenido = $this->contenidoBroadcast($resumen);
        $estadoEnResend = $envio->broadcast_id === null ? null : $this->estadoBroadcast($envio->broadcast_id);

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
            return $this->destinatariosResumenFecha();
        }

        $this->resend->broadcasts->send($envio->broadcast_id, []);

        return $this->destinatariosResumenFecha();
    }

    /** null si el borrador ya no existe en Resend (lo borraron): se crea otro. */
    private function estadoBroadcast(string $idBroadcast): ?string
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
    private function contenidoBroadcast(array $resumen): array
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

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function enviarPorMailer(array $resumen): int
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

        return $enviados;
    }
}
