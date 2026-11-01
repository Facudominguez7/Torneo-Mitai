<?php

namespace App\Console\Commands;

use App\Models\EnvioSuscriptores;
use App\Models\Suscriptor;
use App\Services\EnvioSuscriptoresService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manda una sola vez a todos los suscriptores activos el aviso de que la lista cambió. Sale por la
 * misma auditoría, cola y canal que el resumen de la fecha; si falló, se puede volver a correr.
 */
class AvisarMigracionSuscriptores extends Command
{
    /**
     * @var string
     */
    protected $signature = 'suscripciones:avisar-migracion';

    /**
     * @var string
     */
    protected $description = 'Avisar una vez a los suscriptores que los avisos llegan desde la lista nueva';

    public function handle(EnvioSuscriptoresService $envios): int
    {
        // El broadcast llega a los contactos de Resend: un migrado sin sincronizar no lo recibiría.
        if (config('suscripciones.resend.sincronizar')) {
            $sinSincronizar = Suscriptor::query()->whereNotNull('usuario_id')->whereNull('resend_sincronizado_at')->count();

            if ($sinSincronizar > 0) {
                $this->error("Todavía hay {$sinSincronizar} suscriptor(es) migrado(s) sin sincronizar con Resend. Esperá a que la cola los procese (o corré suscripciones:sincronizar) y volvé a intentar.");

                return self::FAILURE;
            }
        }

        if ($envios->destinatarios(null) === 0) {
            $this->error('No hay suscriptores activos para avisar.');

            return self::FAILURE;
        }

        try {
            $envio = $envios->registrarAvisoMigracion();
        } catch (Throwable) {
            $this->error('No se pudo dejar el aviso en cola. Probá de nuevo en unos minutos.');

            return self::FAILURE;
        }

        if ($envio === null) {
            $previo = $envios->envio(EnvioSuscriptores::CLAVE_AVISO_MIGRACION);
            $this->info($previo?->estado === EnvioSuscriptores::ESTADO_ENVIADO && $previo->enviado_at !== null
                ? 'El aviso de la migración ya se envió el '.$previo->enviado_at->format('d/m/Y H:i').'.'
                : 'El aviso de la migración ya está en cola.');

            return self::SUCCESS;
        }

        $this->info("El aviso quedó en cola para {$envio->destinatarios} destinatario(s): sale en el próximo minuto.");

        return self::SUCCESS;
    }
}
