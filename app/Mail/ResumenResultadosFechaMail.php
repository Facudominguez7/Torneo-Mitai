<?php

namespace App\Mail;

use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResumenResultadosFechaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Datos consolidados del resumen de fecha.
     *
     * @var array<string, mixed>
     */
    public array $resumen;

    /**
     * URL de baja general para envíos en lote BCC.
     */
    public string $urlBajaGeneral;

    /**
     * Crea el correo resumen con todos los resultados de la fecha.
     *
     * Se usa una sola plantilla para múltiples destinatarios en BCC,
     * optimizando cuota SMTP y evitando exposición de correos.
     *
     * @param  array<string, mixed>  $resumen
     */
    public function __construct(array $resumen, string $urlBajaGeneral)
    {
        $this->resumen = $resumen;
        $this->urlBajaGeneral = $urlBajaGeneral;
    }

    /**
     * Construye el correo HTML con el resumen de resultados.
     */
    public function build(): self
    {
        $fechaNombre = (string) ($this->resumen['fecha_nombre'] ?? 'Fecha');
        $categoriaNombre = $this->resumen['categoria_nombre'] ?? null;
        $tituloPersonalizado = trim((string) ($this->resumen['titulo_personalizado'] ?? ''));

        if ($tituloPersonalizado !== '') {
            $subject = "{$tituloPersonalizado} - Torneos Mita'i";
        } else {
            $subject = "Resultados {$fechaNombre} - Torneos Mita'i";
            if (! empty($categoriaNombre)) {
                $subject .= " ({$categoriaNombre})";
            }
        }

        return $this
            ->from(EmailDeliverabilityHeaders::fromAddress(), EmailDeliverabilityHeaders::fromName())
            ->subject($subject)
            ->replyTo(EmailDeliverabilityHeaders::replyToAddress(), EmailDeliverabilityHeaders::replyToName())
            ->view('emails.resumen-resultados-fecha')
            ->text('emails.resumen-resultados-fecha-text')
            ->with([
                'resumen' => $this->resumen,
                'urlBajaGeneral' => $this->urlBajaGeneral,
            ])
            ->withSymfonyMessage(function ($message) {
                EmailDeliverabilityHeaders::applyBulk($message, 'resumen_resultados_fecha', $this->urlBajaGeneral);
            });
    }
}
