<?php

namespace App\Console;

use App\Console\Commands\MergeInstitucionesDuplicadas;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Kernel de consola para comandos artisan.
 *
 * Define los comandos disponibles y la programación de tareas automáticas.
 */
class Kernel extends ConsoleKernel
{
    /**
     * Comandos artisan registrados.
     *
     * @var array
     */
    protected $commands = [
        MergeInstitucionesDuplicadas::class,
    ];

    /**
     * Definir la programación de comandos.
     *
     * Los comandos aquí definidos se ejecutarán automáticamente
     * según su programación cuando el scheduler esté activo.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Procesar trabajos en cola cada minuto (para emails diferidos)
        $schedule->command('queue:work', ['--stop-when-empty', '--max-time=50'])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Registrar comandos de consola.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
