<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso único a quienes aceptaban correos con su cuenta y pasaron a la lista de suscriptores:
 * les cuenta el cambio y les deja elegir los avisos o darse de baja.
 */
class AvisoMigracionSuscriptoresMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const ASUNTO = "Los avisos de Torneos Mita'í ahora llegan desde una lista nueva";

    public function __construct(public string $urlPreferencias) {}

    public function build(): self
    {
        return $this
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->subject(self::ASUNTO)
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->view('emails.suscripcion.aviso-migracion')
            ->text('emails.suscripcion.aviso-migracion-text')
            ->withSymfonyMessage(function ($message): void {
                EmailDeliverabilityHeaders::applyBulk($message, 'aviso_migracion_suscriptores', $this->urlPreferencias);
            });
    }
}
