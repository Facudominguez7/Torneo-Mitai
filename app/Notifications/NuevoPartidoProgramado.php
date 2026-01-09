<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Carbon\Carbon;

/**
 * Notificación de nuevo partido programado.
 * 
 * Se puede enviar para alertar sobre partidos próximos o cambios en fixtures.
 * La notificación se envía vía Web Push y Email a usuarios suscritos.
 *
 * @package App\Notifications
 */
class NuevoPartidoProgramado extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Datos del partido (objeto o array).
     * 
     * @var mixed
     */
    protected $partido;

    /**
     * Mensaje personalizado opcional para la notificación.
     * 
     * @var string|null
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
        // Extraer datos del partido de forma segura (objeto o array)
        $equipoLocal = $this->partido->nombre_local ?? $this->partido['nombre_local'] ?? 'Equipo Local';
        $equipoVisitante = $this->partido->nombre_visitante ?? $this->partido['nombre_visitante'] ?? 'Equipo Visitante';
        $horario = $this->partido->horario_datetime ?? $this->partido['horario_datetime'] ?? null;
        $cancha = $this->partido->cancha ?? $this->partido['cancha'] ?? '';
        $partidoId = $this->partido->id ?? $this->partido['id'] ?? null;

        // Construir cuerpo del mensaje
        $body = $this->mensaje ?? "{$equipoLocal} vs {$equipoVisitante}";
        
        // Agregar fecha y hora si está disponible
        if ($horario) {
            $fecha = Carbon::parse($horario);
            $body .= " | " . $fecha->format('d/m H:i') . " hs";
        }
        
        // Agregar cancha si está disponible
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
     * Obtener la representación en email de la notificación.
     * 
     * Genera un email con los detalles del partido programado usando
     * componentes de Laravel Mail. Incluye enlace de desuscripción.
     *
     * @param mixed $notifiable Usuario destinatario
     * @return MailMessage Mensaje de email configurado
     */
    public function toMail($notifiable): MailMessage
    {
        // Extraer datos del partido de forma segura
        $equipoLocal = $this->partido->nombre_local ?? $this->partido['nombre_local'] ?? 'Equipo Local';
        $equipoVisitante = $this->partido->nombre_visitante ?? $this->partido['nombre_visitante'] ?? 'Equipo Visitante';
        $horario = $this->partido->horario_datetime ?? $this->partido['horario_datetime'] ?? null;
        $cancha = $this->partido->cancha ?? $this->partido['cancha'] ?? '';

        // Construir URL del fixture
        $urlFixture = url('/fixture');
        
        // Generar URL de desuscripción
        $urlDarseDeBaja = url('/email/baja?id=' . $notifiable->id . '&token=' . sha1($notifiable->email));

        // Construir línea del partido
        $partidoTexto = "**{$equipoLocal}** vs **{$equipoVisitante}**";
        
        $mail = (new MailMessage)
            ->subject("📅 Partido Programado: {$equipoLocal} vs {$equipoVisitante} - Torneos Mita'í")
            ->greeting("¡Hola {$notifiable->name}!")
            ->line($this->mensaje ?? 'Se ha programado un nuevo partido:')
            ->line('')
            ->line($partidoTexto);

        // Agregar fecha y hora si está disponible
        if ($horario) {
            $fecha = Carbon::parse($horario);
            $mail->line("📆 Fecha: " . $fecha->format('d/m/Y'));
            $mail->line("🕐 Hora: " . $fecha->format('H:i') . " hs");
        }

        // Agregar cancha si está disponible
        if ($cancha) {
            $mail->line("🏟️ Cancha: {$cancha}");
        }

        return $mail
            ->action('Ver fixture completo', $urlFixture)
            ->line('---')
            ->line("Si preferís no recibir más emails, podés [darte de baja aquí]({$urlDarseDeBaja}).")
            ->salutation("Saludos, el equipo de Torneos Mita'í ⚽");
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
            'tipo' => 'partido_programado',
            'partido_id' => $this->partido->id ?? $this->partido['id'] ?? null,
            'mensaje' => $this->mensaje ?? 'Nuevo partido programado',
        ];
    }
}
