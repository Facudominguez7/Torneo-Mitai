<?php

namespace App\Notifications;

use App\Services\EmailDeliverabilityHeaders;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de nuevo partido programado.
 *
 * Se puede enviar para alertar sobre partidos próximos o cambios en fixtures.
 * La notificación se envía por email a usuarios suscritos.
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
     * @param  mixed  $partido  Objeto o array con datos del partido
     * @param  string|null  $mensaje  Mensaje personalizado opcional
     */
    public function __construct($partido, ?string $mensaje = null)
    {
        $this->partido = $partido;
        $this->mensaje = $mensaje;
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
     * Genera un email con los detalles del partido programado usando
     * componentes de Laravel Mail. Incluye enlace de desuscripción.
     *
     * @param  mixed  $notifiable  Usuario destinatario
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
        $urlDarseDeBaja = url('/email/baja?id='.$notifiable->id.'&token='.sha1($notifiable->email));

        // Construir línea del partido
        $partidoTexto = "**{$equipoLocal}** vs **{$equipoVisitante}**";

        $mail = (new MailMessage)
            ->subject("Partido Programado: {$equipoLocal} vs {$equipoVisitante} - Torneos Mita'i")
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->greeting("Hola {$notifiable->name},")
            ->line($this->mensaje ?? 'Se ha programado un nuevo partido:')
            ->line('')
            ->line($partidoTexto);

        // Agregar fecha y hora si está disponible
        if ($horario) {
            $fecha = Carbon::parse($horario);
            $mail->line('Fecha: '.$fecha->format('d/m/Y'));
            $mail->line('Hora: '.$fecha->format('H:i').' hs');
        }

        // Agregar cancha si está disponible
        if ($cancha) {
            $mail->line("Cancha: {$cancha}");
        }

        return $mail
            ->action('Ver fixture completo', $urlFixture)
            ->line('---')
            ->line("Si preferís no recibir más emails, podés [darte de baja aquí]({$urlDarseDeBaja}).")
            ->salutation("Saludos, Torneos Mita'i")
            ->withSymfonyMessage(function ($message) use ($urlDarseDeBaja) {
                EmailDeliverabilityHeaders::applyBulk($message, 'partido_programado', $urlDarseDeBaja);
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
            'tipo' => 'partido_programado',
            'partido_id' => $this->partido->id ?? $this->partido['id'] ?? null,
            'mensaje' => $this->mensaje ?? 'Nuevo partido programado',
        ];
    }
}
