<?php

namespace App\Console\Commands;

use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Illuminate\Console\Command;

/**
 * Reenvía el enlace de confirmación (doble opt-in) a quienes pidieron suscribirse y nunca confirmaron,
 * por ejemplo porque Resend rechazó el correo original. No toca a activos ni a quienes se dieron de baja.
 * El tope por corrida cuida el cupo diario de Resend; --desde-id permite seguir otro día sin repetir.
 */
class ReenviarConfirmacionesSuscripcion extends Command
{
    /**
     * @var string
     */
    protected $signature = 'suscripciones:reenviar-confirmaciones
        {--limite=80 : Máximo de correos por corrida (el plan gratuito de Resend permite 100 por día)}
        {--desde-id=0 : Seguir con los suscriptores de ID mayor a este}
        {--simular : Mostrar cuántos se enviarían sin mandar nada}';

    /**
     * @var string
     */
    protected $description = 'Reenviar el correo de confirmación a los suscriptores que nunca confirmaron';

    public function handle(SuscripcionService $suscripciones): int
    {
        $limite = max(1, (int) $this->option('limite'));

        $pendientes = Suscriptor::query()
            ->whereNull('confirmado_at')
            ->whereNull('baja_at')
            ->where('id', '>', (int) $this->option('desde-id'))
            ->orderBy('id');

        $total = (clone $pendientes)->count();

        if ($total === 0) {
            $this->info('No hay suscriptores pendientes de confirmar.');

            return self::SUCCESS;
        }

        $lote = $pendientes->limit($limite)->get();

        $this->line("Pendientes de confirmar: {$total}");

        if ($this->option('simular')) {
            $this->line("Se reenviarían: {$lote->count()}");
            $this->info('Modo simulación: no se envió ningún correo.');

            return self::SUCCESS;
        }

        if (! $this->confirm("¿Reenviar el correo de confirmación a {$lote->count()} suscriptor(es)?")) {
            $this->info('No se envió ningún correo.');

            return self::SUCCESS;
        }

        // Un correo por segundo: la API de Resend limita los pedidos por segundo.
        $inicio = now();
        foreach ($lote->values() as $indice => $suscriptor) {
            $suscripciones->reenviarConfirmacion($suscriptor, $inicio->copy()->addSeconds($indice + 1));
        }

        $this->info("Quedaron en cola: {$lote->count()}");

        $restantes = $total - $lote->count();
        if ($restantes > 0) {
            $this->warn("Quedan {$restantes} pendiente(s). Para seguir: php artisan suscripciones:reenviar-confirmaciones --desde-id={$lote->last()->id}");
        }

        return self::SUCCESS;
    }
}
