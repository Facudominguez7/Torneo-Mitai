<?php

namespace App\Notifications;

use App\Services\EmailDeliverabilityHeaders;
use App\Services\EnlaceBajaEmail;
use App\Services\NotificationDispatchMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResumenResultadosFecha extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $resumen
     */
    public function __construct(private readonly array $resumen) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->quiereRecibirEmails()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urlBajaPersonalizada = EnlaceBajaEmail::generar($notifiable);
        $resumen = $this->resumen;
        $resumen['url_fixture'] = $resumen['url_fixture'] ?? '/fixture';

        return (new MailMessage)
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->subject($this->subject())
            ->view('emails.resumen-resultados-fecha', [
                'resumen' => $resumen,
                'urlBajaGeneral' => $urlBajaPersonalizada,
            ])
            ->text('emails.resumen-resultados-fecha-text', [
                'resumen' => $resumen,
                'urlBajaGeneral' => $urlBajaPersonalizada,
            ])
            ->withSymfonyMessage(function ($message) use ($urlBajaPersonalizada) {
                EmailDeliverabilityHeaders::applyBulk($message, 'resumen_resultados_fecha', $urlBajaPersonalizada);
                NotificationDispatchMetadata::attach($message, $this->id);
            });
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'resumen_resultados_fecha',
            'titulo' => $this->resumen['titulo_personalizado'] ?? 'Resumen de resultados',
            'fecha_nombre' => $this->resumen['fecha_nombre'] ?? null,
            'categoria_nombre' => $this->resumen['categoria_nombre'] ?? null,
            'url_fixture' => $this->resumen['url_fixture'] ?? '/fixture',
        ];
    }

    private function subject(): string
    {
        $fechaNombre = trim((string) ($this->resumen['fecha_nombre'] ?? 'Fecha'));
        $categoriaNombre = trim((string) ($this->resumen['categoria_nombre'] ?? ''));
        $tituloPersonalizado = trim((string) ($this->resumen['titulo_personalizado'] ?? ''));

        if ($tituloPersonalizado !== '') {
            return $tituloPersonalizado." - Torneos Mita'i";
        }

        $subject = "Resultados {$fechaNombre} - Torneos Mita'i";

        if ($categoriaNombre !== '') {
            $subject .= " ({$categoriaNombre})";
        }

        return $subject;
    }
}
