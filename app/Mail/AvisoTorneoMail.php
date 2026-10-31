<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso libre del torneo (suspensiones, cambios, novedades) para quienes eligieron los avisos. */
class AvisoTorneoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $titulo, public string $mensaje, public string $urlPreferencias) {}

    public function build(): self
    {
        return $this
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->subject($this->titulo." - Torneos Mita'i")
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->view('emails.suscripcion.aviso-torneo')
            ->text('emails.suscripcion.aviso-torneo-text')
            ->withSymfonyMessage(function ($message): void {
                EmailDeliverabilityHeaders::applyBulk($message, 'avisos_torneo', $this->urlPreferencias);
            });
    }
}
