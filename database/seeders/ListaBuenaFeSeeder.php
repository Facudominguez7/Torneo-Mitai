<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlanillaJugador;
use App\Models\ListaBuenaFe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListaBuenaFeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los jugadores únicos de las planillas
        // Agrupados por Jugador, Equipo y Edición
        $jugadoresPlanilla = PlanillaJugador::select(
                'dni_jugador',
                'idEquipo',
                'idEdicion',
                'idCategoria',
                'fecha_nacimiento',
                DB::raw('MIN(created_at) as fecha_alta')
            )
            ->groupBy('dni_jugador', 'idEquipo', 'idEdicion', 'idCategoria', 'fecha_nacimiento')
            ->get();

        $count = 0;

        foreach ($jugadoresPlanilla as $jugador) {
            // Verificar si ya existe en la lista de buena fe
            $exists = ListaBuenaFe::where('dni_jugador', $jugador->dni_jugador)
                ->where('idEquipo', $jugador->idEquipo)
                ->where('idEdicion', $jugador->idEdicion)
                ->exists();

            if (!$exists) {
                ListaBuenaFe::create([
                    'dni_jugador' => $jugador->dni_jugador,
                    'idEquipo' => $jugador->idEquipo,
                    'idEdicion' => $jugador->idEdicion,
                    'idCategoria' => $jugador->idCategoria,
                    'fecha_nacimiento' => $jugador->fecha_nacimiento,
                    'fecha_alta' => $jugador->fecha_alta ?? now(),
                    'activo' => true,
                ]);
                $count++;
            }
        }

        $this->command->info("Seeder completado: {$count} jugadores agregados a Lista de Buena Fe.");
    }
}
