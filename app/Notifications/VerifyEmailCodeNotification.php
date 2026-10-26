<?php

namespace App\Notifications;

use App\Services\EmailDeliverabilityHeaders;
use App\Services\EmailVerificationCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->subject('Tu codigo de verificacion - Torneos Mita\'i')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Usa este codigo de 6 digitos para verificar tu email y activar las notificaciones por correo:')
            ->line('Codigo de verificacion: '.$this->formattedCode())
            ->line('El codigo vence en '.EmailVerificationCodeService::EXPIRES_IN_MINUTES.' minutos.')
            ->action('Ingresar codigo', route('verification.notice'))
            ->line('Si no creaste esta cuenta, podes ignorar este mensaje.')
            ->salutation('Saludos, Torneos Mita\'i')
            ->withSymfonyMessage(function ($message) {
                EmailDeliverabilityHeaders::applyTransactional($message, 'verificacion_email_codigo');
            });
    }

    private function formattedCode(): string
    {
        return $this->code;
    }
}
