<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de recordatorio para verificar email.
 *
 * Envía de forma síncrona para evitar duplicados.
 */
class RecordatorioVerificacionEmail extends Notification
{
    use Queueable;

    /**
     * Número de intentos antes de fallar.
     */
    public $tries = 3;

    /**
     * Tiempo máximo de procesamiento (segundos).
     */
    public $timeout = 60;

    /**
     * Obtener los canales de entrega de la notificación.
     *
     * Solo usa canal de email ya que el usuario no tiene email verificado
     * y por lo tanto no puede recibir otras notificaciones.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Obtener la representación en email de la notificación.
     *
     * @param  mixed  $notifiable  Usuario destinatario
     */
    public function toMail($notifiable): MailMessage
    {
        // URL de verificación usando la acción firmada de Laravel con query params
        $urlVerificacion = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // URL para darse de baja
        $urlBaja = url('/email/baja?id='.$notifiable->id.'&token='.sha1($notifiable->email));

        return (new MailMessage)
            ->subject("Verifica tu email - Torneos Mita'i")
            ->replyTo('info@torneosmitai.com')
            ->greeting("Hola {$notifiable->name},")
            ->line('Tu cuenta en Torneos Mita\'i todavia no tiene el email verificado.')
            ->line('')
            ->line('Al verificar tu email podras:')
            ->line('- Recibir resultados de partidos')
            ->line('- Enterarte de partidos programados')
            ->line('- Recibir noticias del torneo')
            ->line('')
            ->line('Hace clic en el boton para verificar:')
            ->action('Verificar Email', $urlVerificacion)
            ->line('')
            ->line('Si no creaste esta cuenta, podes ignorar este mensaje.')
            ->salutation("Saludos, Torneos Mita'i")
            // Headers para deliverability y one-click unsubscribe (RFC 8058)
            ->withSymfonyMessage(function ($message) use ($urlBaja) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', "<{$urlBaja}>");
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }

    /**
     * Obtener la representación en array de la notificación.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'tipo' => 'recordatorio_verificacion',
            'mensaje' => 'Recordatorio para verificar email',
        ];
    }
}
