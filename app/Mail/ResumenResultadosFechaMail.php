<?php

namespace App\Mail;

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
     * @param array<string, mixed> $resumen
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
            ->subject($subject)
            ->replyTo('info@torneosmitai.com')
            ->view('emails.resumen-resultados-fecha')
            ->with([
                'resumen' => $this->resumen,
                'urlBajaGeneral' => $this->urlBajaGeneral,
            ])
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', "<{$this->urlBajaGeneral}>");
                $headers->addTextHeader('Precedence', 'bulk');
                $headers->addTextHeader('X-Mailer', 'Torneos-Mitai-Laravel');
            });
    }
}
