<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

/** Pide confirmar la suscripción con un clic (doble opt-in). */
class ConfirmacionSuscripcionMail extends Mailable
{
    public function __construct(public readonly string $urlConfirmacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('suscripciones.remitente'), EmailDeliverabilityHeaders::fromName()),
            replyTo: [new Address(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())],
            subject: "Confirmá tu suscripción a Torneos Mita'í",
            using: [fn (Email $mensaje) => EmailDeliverabilityHeaders::applyTransactional($mensaje, 'confirmacion_suscripcion')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.suscripcion.confirmacion');
    }
}
