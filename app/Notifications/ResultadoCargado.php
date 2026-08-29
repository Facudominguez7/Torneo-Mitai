<?php

namespace App\Notifications;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de resultado de partido cargado.
 *
 * Se envía cuando un administrador carga el resultado de un partido.
 * La notificación se envía por email a usuarios suscritos.
 * Implementa ShouldQueue para procesarse en segundo plano.
 */
class ResultadoCargado extends Notification
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
     * Datos del partido (objeto o array).
     *
     * @var mixed
     */
    protected $partido;

    /**
     * Crear una nueva instancia de la notificación.
     *
     * @param  mixed  $partido  Objeto o array con datos del partido
     */
    public function __construct($partido)
    {
        $this->partido = $partido;
    }

    /**
     * Obtener los canales de entrega de la notificación.
     *
     * Determina si el usuario tiene habilitadas las notificaciones por email.
     *
     * @param  mixed  $notifiable  Usuario que recibirá la notificación
     * @return array Canales de notificación a usar
     */
    public function via($notifiable): array
    {
        return $notifiable->quiereRecibirEmails() ? ['mail'] : [];
    }

    /**
     * Obtener la representación en email de la notificación.
     *
     * Genera un email con el resultado del partido usando componentes
     * de Laravel Mail. Incluye enlace de desuscripción.
     *
     * @param  mixed  $notifiable  Usuario destinatario
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
        $urlFixture = url('/fixture'.($idEdicion ? "?idEdicion={$idEdicion}" : ''));
        // URL para darse de baja de estos recordatorios
        $urlDarseDeBaja = url('/email/baja?id='.$notifiable->id.'&token='.sha1($notifiable->email));

        // Construir línea de resultado con formato atractivo
        $resultado = "**{$equipoLocal}** {$golesLocal} - {$golesVisitante} **{$equipoVisitante}**";

        $mail = (new MailMessage)
            ->subject("Resultado: {$equipoLocal} vs {$equipoVisitante} - Torneos Mita'i")
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->greeting("Hola {$notifiable->name},")
            ->line('Se ha cargado un nuevo resultado:')
            ->line('')
            ->line($resultado);

        // Agregar categoría si existe
        if ($categoria) {
            $mail->line("Categoría: {$categoria}");
        }

        return $mail
            ->action('Ver fixture completo', $urlFixture)
            ->line('---')
            ->line("Si preferís no recibir más emails, podés [darte de baja aquí]({$urlDarseDeBaja}).")
            ->salutation("Saludos, Torneos Mita'i")
            ->withSymfonyMessage(function ($message) use ($urlDarseDeBaja) {
                EmailDeliverabilityHeaders::applyBulk($message, 'resultado_cargado', $urlDarseDeBaja);
            });
    }

    /**
     * Obtener la representación en array de la notificación.
     *
     * Usado para almacenar la notificación en base de datos si se requiere.
     *
     * @param  mixed  $notifiable  Usuario destinatario
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
