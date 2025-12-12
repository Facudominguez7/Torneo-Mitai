<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación push personalizada para anuncios del administrador.
 * 
 * Permite enviar notificaciones con título, mensaje y URL personalizados.
 *
 * @package App\Notifications
 */
class NotificacionPersonalizada extends Notification
{
    use Queueable;

    protected string $titulo;
    protected string $mensaje;
    protected string $icono;
    protected string $url;

    /**
     * Crear una nueva instancia de la notificación.
     *
     * @param string $titulo Título de la notificación
     * @param string $mensaje Cuerpo del mensaje
     * @param string $url URL a abrir al hacer clic (opcional)
     * @param string $icono Icono/emoji para el título (opcional)
     */
    public function __construct(string $titulo, string $mensaje, string $url = '/', string $icono = '📢')
    {
        $this->titulo = $titulo;
        $this->mensaje = $mensaje;
        $this->url = $url;
        $this->icono = $icono;
    }

    /**
     * Obtener los canales de entrega de la notificación.
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Obtener la representación Web Push de la notificación.
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("{$this->icono} {$this->titulo}")
            ->icon('/fotos/Logo_Mitai_SinFondo.png')
            ->badge('/fotos/Logo_Mitai_SinFondo.png')
            ->body($this->mensaje)
            ->tag('anuncio-' . time())
            ->data([
                'url' => $this->url
            ])
            ->options([
                'TTL' => 86400,
                'urgency' => 'normal'
            ]);
    }

    /**
     * Obtener la representación en array de la notificación.
     */
    public function toArray($notifiable): array
    {
        return [
            'tipo' => 'notificacion_personalizada',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
        ];
    }
}
