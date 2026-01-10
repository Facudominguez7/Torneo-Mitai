<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación push y email personalizada para anuncios del administrador.
 * 
 * Permite enviar notificaciones con título, mensaje y URL personalizados.
 * Se envía tanto por WebPush como por email a usuarios suscritos.
 * Implementa ShouldQueue para procesarse en segundo plano.
 *
 * @package App\Notifications
 */
class NotificacionPersonalizada extends Notification
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
     * Título de la notificación.
     */
    protected string $titulo;

    /**
     * Mensaje/cuerpo de la notificación.
     */
    protected string $mensaje;

    /**
     * Icono/emoji para mostrar junto al título.
     */
    protected string $icono;

    /**
     * URL a donde redirigir al usuario al hacer clic.
     */
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
     * 
     * Determina dinámicamente qué canales usar según las preferencias del usuario:
     * - WebPush: Si tiene suscripciones push activas
     * - Mail: Si tiene email verificado y está suscrito a emails
     *
     * @param mixed $notifiable Usuario que recibirá la notificación
     * @return array Canales de notificación a usar
     */
    public function via($notifiable): array
    {
        $canales = [];

        // Canal WebPush si el usuario tiene suscripciones activas
        if ($notifiable->pushSubscriptions()->exists()) {
            $canales[] = WebPushChannel::class;
        }

        // Canal Email si el usuario quiere recibir emails
        if ($notifiable->quiereRecibirEmails()) {
            $canales[] = 'mail';
        }

        return $canales;
    }

    /**
     * Obtener la representación Web Push de la notificación.
     *
     * @param mixed $notifiable Usuario destinatario
     * @param mixed $notification Instancia de la notificación
     * @return WebPushMessage Mensaje push configurado
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
     * Obtener la representación en email de la notificación.
     * 
     * Genera un email con diseño atractivo usando los componentes
     * de Laravel Mail. Incluye enlace de desuscripción.
     *
     * @param mixed $notifiable Usuario destinatario
     * @return MailMessage Mensaje de email configurado
     */
    public function toMail($notifiable): MailMessage
    {
        // Construir URL completa para el enlace de acción
        $urlCompleta = url($this->url);
        
        // Generar URL de desuscripción usando parámetros de consulta (query string)
        $urlDarseDeBaja = url('/email/baja?id=' . $notifiable->id . '&token=' . sha1($notifiable->email));

        return (new MailMessage)
            ->subject("{$this->titulo} - Torneos Mita'i")
            ->replyTo('info@torneosmitai.com')
            ->greeting("Hola {$notifiable->name},")
            ->line($this->mensaje)
            ->action('Ver más información', $urlCompleta)
            ->line('---')
            ->line("Si preferís no recibir más emails, podés [darte de baja aquí]({$urlDarseDeBaja}).")
            ->salutation("Saludos, Torneos Mita'i")
            // Headers anti-spam
            ->withSymfonyMessage(function ($message) use ($urlDarseDeBaja) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', "<{$urlDarseDeBaja}>");
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                $headers->addTextHeader('Precedence', 'bulk');
                // Reply-To manejado via ->replyTo() method
                $headers->addTextHeader('X-Mailer', 'Torneos-Mitai-Laravel');
            });
    }

    /**
     * Obtener la representación en array de la notificación.
     * 
     * Usado para almacenar la notificación en base de datos si se requiere.
     *
     * @param mixed $notifiable Usuario destinatario
     * @return array Datos de la notificación
     */
    public function toArray($notifiable): array
    {
        return [
            'tipo' => 'notificacion_personalizada',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }
}
