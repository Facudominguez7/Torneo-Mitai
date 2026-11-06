<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

/**
 * Invitación única a quien tiene cuenta y nunca pidió los avisos: solo queda suscripto si confirma (doble opt-in).
 */
class InvitacionSuscripcionMail extends Mailable implements ShouldQueue
{
    public function __construct(public readonly string $urlConfirmacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('suscripciones.remitente'), EmailDeliverabilityHeaders::fromName()),
            replyTo: [new Address(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())],
            subject: "¿Querés recibir los resultados de Torneos Mita'í por correo?",
            using: [fn (Email $mensaje) => EmailDeliverabilityHeaders::applyTransactional($mensaje, 'invitacion_suscripcion')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.suscripcion.invitacion');
    }
}
