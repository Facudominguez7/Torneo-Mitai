<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación de nuevo partido programado.
 * 
 * Se puede enviar para alertar sobre partidos próximos o cambios en fixtures.
 *
 * @package App\Notifications
 */
class NuevoPartidoProgramado extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Datos del partido
     */
    protected $partido;

    /**
     * Mensaje personalizado opcional
     */
    protected $mensaje;

    /**
     * Crear una nueva instancia de la notificación.
     *
     * @param mixed $partido Objeto o array con datos del partido
     * @param string|null $mensaje Mensaje personalizado opcional
     */
    public function __construct($partido, ?string $mensaje = null)
    {
        $this->partido = $partido;
        $this->mensaje = $mensaje;
    }

    /**
     * Obtener los canales de entrega de la notificación.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Obtener la representación Web Push de la notificación.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return WebPushMessage
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        // Extraer datos del partido
        $equipoLocal = $this->partido->nombre_local ?? $this->partido['nombre_local'] ?? 'Equipo Local';
        $equipoVisitante = $this->partido->nombre_visitante ?? $this->partido['nombre_visitante'] ?? 'Equipo Visitante';
        $horario = $this->partido->horario_datetime ?? $this->partido['horario_datetime'] ?? null;
        $cancha = $this->partido->cancha ?? $this->partido['cancha'] ?? '';
        $partidoId = $this->partido->id ?? $this->partido['id'] ?? null;

        $body = $this->mensaje ?? "{$equipoLocal} vs {$equipoVisitante}";
        
        if ($horario) {
            $fecha = \Carbon\Carbon::parse($horario);
            $body .= " | " . $fecha->format('d/m H:i') . " hs";
        }
        
        if ($cancha) {
            $body .= " | Cancha {$cancha}";
        }

        return (new WebPushMessage)
            ->title('📅 Partido Programado')
            ->icon('/fotos/Logo_Mitai_SinFondo.png')
            ->badge('/fotos/Logo_Mitai_SinFondo.png')
            ->body($body)
            ->tag('partido-' . ($partidoId ?? time()))
            ->data([
                'url' => '/fixture',
                'partido_id' => $partidoId
            ])
            ->options([
                'TTL' => 86400 * 3, // 3 días
                'urgency' => 'normal'
            ]);
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
            'tipo' => 'partido_programado',
            'partido_id' => $this->partido->id ?? $this->partido['id'] ?? null,
            'mensaje' => $this->mensaje ?? 'Nuevo partido programado',
        ];
    }
}
