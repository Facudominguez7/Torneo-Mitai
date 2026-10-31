<?php

namespace App\Services\EnvioSuscriptores;

use App\Mail\ResumenResultadosFechaMail;
use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Mail;

/**
 * Local: el mismo aviso como un correo por suscriptor al mailer configurado (MailHog), cada uno con
 * su enlace firmado de preferencias, que en producción viaja como propiedad del contacto de Resend.
 */
class CanalMailerLocal implements CanalEnvioSuscriptores
{
    public function __construct(private readonly SuscripcionService $suscripciones) {}

    public function enviarResumenFecha(EnvioSuscriptores $envio, array $resumen): int
    {
        $enviados = 0;

        Suscriptor::query()->recibenResumenFecha()
            ->chunkById(100, function ($suscriptores) use ($resumen, &$enviados): void {
                foreach ($suscriptores as $suscriptor) {
                    Mail::mailer(config('suscripciones.mailer'))->to($suscriptor->email)->send(
                        new ResumenResultadosFechaMail($resumen, $this->suscripciones->urlPreferencias($suscriptor)),
                    );
                    $enviados++;
                }
            });

        return $enviados;
    }
}
