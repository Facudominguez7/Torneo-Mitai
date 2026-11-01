<?php

namespace App\Console\Commands;

use App\Models\Suscriptor;
use App\Services\SincronizacionResendService;
use Illuminate\Console\Command;

/**
 * Reintenta llevar a Resend los suscriptores confirmados con cambios pendientes (altas, preferencias o bajas).
 */
class SincronizarSuscriptoresResend extends Command
{
    /**
     * @var string
     */
    protected $signature = 'suscripciones:sincronizar';

    /**
     * @var string
     */
    protected $description = 'Sincronizar con Resend los suscriptores con cambios pendientes';

    public function handle(SincronizacionResendService $sincronizacion): int
    {
        if (! config('suscripciones.resend.sincronizar')) {
            $this->warn('La sincronización con Resend está apagada (el mailer de suscriptores no es resend).');

            return self::SUCCESS;
        }

        $sincronizados = 0;
        $conError = 0;
        $pausa = (int) config('suscripciones.resend.pausa_ms') * 1000;

        Suscriptor::query()
            ->whereNotNull('confirmado_at')
            ->whereNull('resend_sincronizado_at')
            ->chunkById(100, function ($suscriptores) use ($sincronizacion, $pausa, &$sincronizados, &$conError): void {
                foreach ($suscriptores as $suscriptor) {
                    $sincronizacion->sincronizar($suscriptor) ? $sincronizados++ : $conError++;
                    // Resend admite 10 pedidos por segundo y cada suscriptor puede usar varios.
                    usleep($pausa);
                }
            });

        $this->info("Sincronizados: {$sincronizados}. Con error: {$conError}.");

        return $conError === 0 ? self::SUCCESS : self::FAILURE;
    }
}
