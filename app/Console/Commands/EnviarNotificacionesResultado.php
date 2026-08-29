<?php

namespace App\Console\Commands;

use App\Models\Partido;
use App\Models\User;
use App\Notifications\ResultadoCargado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnviarNotificacionesResultado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mitai:notificar-resultado {partido_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía notificaciones de resultado cargado en segundo plano';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $partidoId = $this->argument('partido_id');

        Log::info("Iniciando comando de notificación background para partido #{$partidoId}");

        try {
            $partido = Partido::with(['equipoLocal', 'equipoVisitante', 'categoria'])
                ->find($partidoId);

            if (! $partido) {
                Log::error("Partido #{$partidoId} no encontrado.");

                return 1;
            }

            // Preparar datos
            $datosPartido = (object) [
                'id' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'nombre_local' => $partido->equipoLocal->nombre ?? 'Equipo Local',
                'nombre_visitante' => $partido->equipoVisitante->nombre ?? 'Equipo Visitante',
                'golesEquipoLocal' => $partido->golesEquipoLocal,
                'golesEquipoVisitante' => $partido->golesEquipoVisitante,
                'nombre_categoria' => $partido->categoria->nombreCategoria ?? '',
            ];

            $notificacion = new ResultadoCargado($datosPartido);

            // Las cuentas nuevas con política reforzada solo reciben emails
            // si ya verificaron su dirección.
            User::suscritosAEmail()->chunk(50, function ($users) use ($notificacion) {
                foreach ($users as $user) {
                    try {
                        $user->notify($notificacion);
                        $user->forceFill(['last_notification_at' => now()])->saveQuietly();
                    } catch (\Exception $e) {
                        Log::error("Fallo notificando usuario {$user->id}: ".$e->getMessage());
                    }
                }
            });

            Log::info("Comando de notificaciones finalizado exitosamente para partido #{$partidoId}");

            return 0;

        } catch (\Exception $e) {
            Log::error('Error fatal en comando notificaciones: '.$e->getMessage());

            return 1;
        }
    }
}
