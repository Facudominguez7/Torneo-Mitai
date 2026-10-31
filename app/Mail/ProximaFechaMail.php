<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Programación de una fecha (equipos, día, hora y cancha) para los suscriptores que la eligieron. */
class ProximaFechaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $proximaFecha  armada por ResumenFechaService::armarProximaFecha
     */
    public function __construct(public array $proximaFecha, public string $urlPreferencias) {}

    public function build(): self
    {
        $asunto = 'Próxima fecha: '.($this->proximaFecha['fecha_nombre'] ?? 'Fecha');
        if (! empty($this->proximaFecha['categoria_nombre'])) {
            $asunto .= ' ('.$this->proximaFecha['categoria_nombre'].')';
        }

        return $this
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->subject($asunto." - Torneos Mita'i")
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->view('emails.suscripcion.proxima-fecha')
            ->text('emails.suscripcion.proxima-fecha-text')
            ->withSymfonyMessage(function ($message): void {
                EmailDeliverabilityHeaders::applyBulk($message, 'proxima_fecha', $this->urlPreferencias);
            });
    }
}
