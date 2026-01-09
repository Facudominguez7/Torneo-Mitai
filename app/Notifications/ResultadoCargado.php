<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación de resultado de partido cargado.
 * 
 * Se envía cuando un administrador carga el resultado de un partido.
 * La notificación se envía vía Web Push y Email a usuarios suscritos.
 *
 * @package App\Notifications
 */
class ResultadoCargado extends Notification
{
    use Queueable;

    /**
     * Datos del partido (objeto o array).
     * 
     * @var mixed
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
        $golesLocal = $this->partido->golesEquipoLocal ?? $this->partido['golesEquipoLocal'] ?? 0;
        $golesVisitante = $this->partido->golesEquipoVisitante ?? $this->partido['golesEquipoVisitante'] ?? 0;
        $categoria = $this->partido->nombre_categoria ?? $this->partido['nombre_categoria'] ?? '';
        $partidoId = $this->partido->id ?? $this->partido['id'] ?? null;
        $idEdicion = $this->partido->idEdicion ?? $this->partido['idEdicion'] ?? null;

        // Construir cuerpo del mensaje
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
     * Obtener la representación en email de la notificación.
     * 
     * Genera un email con el resultado del partido usando componentes
     * de Laravel Mail. Incluye enlace de desuscripción.
     *
     * @param mixed $notifiable Usuario destinatario
     * @return MailMessage Mensaje de email configurado
     */
    public function toMail($notifiable): MailMessage
    {
        // Extraer datos del partido de forma segura
        $equipoLocal = $this->partido->nombre_local ?? $this->partido['nombre_local'] ?? 'Equipo Local';
        $equipoVisitante = $this->partido->nombre_visitante ?? $this->partido['nombre_visitante'] ?? 'Equipo Visitante';
        $golesLocal = $this->partido->golesEquipoLocal ?? $this->partido['golesEquipoLocal'] ?? 0;
        $golesVisitante = $this->partido->golesEquipoVisitante ?? $this->partido['golesEquipoVisitante'] ?? 0;
        $categoria = $this->partido->nombre_categoria ?? $this->partido['nombre_categoria'] ?? '';
        $idEdicion = $this->partido->idEdicion ?? $this->partido['idEdicion'] ?? null;

        // Construir URL del fixture
        $urlFixture = url('/fixture' . ($idEdicion ? "?idEdicion={$idEdicion}" : ''));
        
        // Generar URL de desuscripción
        $urlDarseDeBaja = url('/email/baja/' . $notifiable->id . '/' . sha1($notifiable->email));

        // Construir línea de resultado con formato atractivo
        $resultado = "**{$equipoLocal}** {$golesLocal} - {$golesVisitante} **{$equipoVisitante}**";
        
        $mail = (new MailMessage)
            ->subject("⚽ Resultado: {$equipoLocal} vs {$equipoVisitante} - Torneos Mita'í")
            ->greeting("¡Hola {$notifiable->name}!")
            ->line('Se ha cargado un nuevo resultado:')
            ->line('')
            ->line($resultado);

        // Agregar categoría si existe
        if ($categoria) {
            $mail->line("📋 Categoría: {$categoria}");
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
            'tipo' => 'resultado_cargado',
            'partido_id' => $this->partido->id ?? $this->partido['id'] ?? null,
            'mensaje' => 'Nuevo resultado cargado',
        ];
    }
}
