<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación de resultado de partido cargado.
 * 
 * Se envía cuando un administrador carga el resultado de un partido.
 * La notificación se envía vía Web Push a todos los usuarios suscritos.
 *
 * @package App\Notifications
 */
class ResultadoCargado extends Notification
{
    use Queueable;

    /**
     * Datos del partido
     */
    protected $partido;

    /**
     * Crear una nueva instancia de la notificación.
     *
     * @param mixed $partido Objeto o array con datos del partido
     */
    public function __construct($partido)
    {
        $this->partido = $partido;
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
        $golesLocal = $this->partido->golesEquipoLocal ?? $this->partido['golesEquipoLocal'] ?? 0;
        $golesVisitante = $this->partido->golesEquipoVisitante ?? $this->partido['golesEquipoVisitante'] ?? 0;
        $categoria = $this->partido->nombre_categoria ?? $this->partido['nombre_categoria'] ?? '';
        $partidoId = $this->partido->id ?? $this->partido['id'] ?? null;
        $idEdicion = $this->partido->idEdicion ?? $this->partido['idEdicion'] ?? null;

        $body = "{$equipoLocal} {$golesLocal} - {$golesVisitante} {$equipoVisitante}";
        if ($categoria) {
            $body .= " | {$categoria}";
        }

        // Construir URL con idEdicion (sanitizado como entero por seguridad)
        $url = '/';
        if ($idEdicion) {
            $url = '/fixture?idEdicion=' . (int) $idEdicion;
        }

        return (new WebPushMessage)
            ->title('⚽ Nuevo Resultado')
            ->icon('/fotos/Logo_Mitai_SinFondo.png')
            ->badge('/fotos/Logo_Mitai_SinFondo.png')
            ->body($body)
            ->tag('resultado-' . ($partidoId ?? time()))
            ->data([
                'url' => $url,
                'partido_id' => $partidoId
            ])
            ->options([
                'TTL' => 86400, // 24 horas
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
            'tipo' => 'resultado_cargado',
            'partido_id' => $this->partido->id ?? $this->partido['id'] ?? null,
            'mensaje' => 'Nuevo resultado cargado',
        ];
    }
}
