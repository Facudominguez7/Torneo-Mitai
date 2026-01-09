<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Listener para registrar cada notificación enviada.
 * 
 * Se ejecuta automáticamente después de que cualquier notificación
 * sea enviada exitosamente (email, push, etc).
 */
class LogNotificacionEnviada
{
    /**
     * Manejar el evento de notificación enviada.
     *
     * @param NotificationSent $event
     * @return void
     */
    public function handle(NotificationSent $event): void
    {
        $notifiable = $event->notifiable;
        $notification = $event->notification;
        $channel = $event->channel;

        // Solo logear emails
        if ($channel === 'mail') {
            Log::channel('single')->info('Email enviado', [
                'tipo' => class_basename($notification),
                'destinatario_id' => $notifiable->id ?? null,
                'destinatario_email' => $notifiable->email ?? 'N/A',
                'destinatario_nombre' => $notifiable->name ?? 'N/A',
                'canal' => $channel,
                'fecha' => now()->toDateTimeString(),
            ]);
        }
    }
}
