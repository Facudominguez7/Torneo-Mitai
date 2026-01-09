<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notificación de recordatorio para verificar email.
 * 
 * Se envía a usuarios que no han verificado su email para recordarles
 * que lo hagan para poder recibir notificaciones del torneo.
 * Incluye información sobre beneficios y opción de darse de baja.
 *
 * @package App\Notifications
 */
class RecordatorioVerificacionEmail extends Notification
{
    use Queueable;

    /**
     * Obtener los canales de entrega de la notificación.
     * 
     * Solo usa canal de email ya que el usuario no tiene email verificado
     * y por lo tanto no puede recibir otras notificaciones.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Obtener la representación en email de la notificación.
     *
     * @param mixed $notifiable Usuario destinatario
     * @return MailMessage
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
        
        // URL para darse de baja de estos recordatorios (aunque no esté verificado)
        $urlBaja = url('/email/baja?id=' . $notifiable->id . '&token=' . sha1($notifiable->email));

        return (new MailMessage)
            ->subject("📧 ¡Verificá tu email para recibir notificaciones! - Torneos Mita'í")
            ->greeting("¡Hola {$notifiable->name}!")
            ->line('Te escribimos porque todavía no verificaste tu email.')
            ->line('')
            ->line('**¿Por qué verificar?**')
            ->line('✉️ Recibir resultados de partidos al instante')
            ->line('📅 Enterarte de nuevos partidos programados')
            ->line('📢 Recibir anuncios importantes del torneo')
            ->line('')
            ->line('Verificar tu email es muy fácil, solo hacé clic en el botón:')
            ->action('Verificar mi email', $urlVerificacion)
            ->line('')
            ->line('---')
            ->line("Si no querés recibir más emails nuestros, podés [darte de baja aquí]({$urlBaja}).")
            ->salutation("Saludos, el equipo de Torneos Mita'í ⚽");
    }

    /**
     * Obtener la representación en array de la notificación.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'tipo' => 'recordatorio_verificacion',
            'mensaje' => 'Recordatorio para verificar email',
        ];
    }
}
