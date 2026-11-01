<?php

namespace App\Services\EnvioSuscriptores;

use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Closure;
use Illuminate\Support\Facades\Mail;

/**
 * Local: el mismo aviso como un correo por suscriptor al mailer configurado (MailHog), cada uno con
 * su enlace firmado de preferencias, que en producción viaja como propiedad del contacto de Resend.
 */
class CanalMailerLocal implements CanalEnvioSuscriptores
{
    public function __construct(private readonly SuscripcionService $suscripciones) {}

    public function enviar(EnvioSuscriptores $envio, ?string $aviso, Closure $correo, string $vistaTexto): int
    {
        $enviados = 0;

        Suscriptor::query()->recibenAviso($aviso)
            ->chunkById(100, function ($suscriptores) use ($correo, &$enviados): void {
                foreach ($suscriptores as $suscriptor) {
                    Mail::mailer(config('suscripciones.mailer'))->to($suscriptor->email)->send(
                        $correo($this->suscripciones->urlPreferencias($suscriptor)),
                    );
                    $enviados++;
                }
            });

        return $enviados;
    }
}
