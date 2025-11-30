<?php

namespace App\Services;

use App\Models\Fecha;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Illuminate\Support\Facades\DB;

class EstadisticasService
{
    /**
     * Obtiene el total de jugadores únicos de la última fecha de una edición
     *
     * @param int $idEdicion
     * @return array
     */
    public function obtenerJugadoresDosUltimasFechas($idEdicion)
    {
        // Obtener la última fecha de la edición
        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)
            ->orderBy('id', 'desc')
            ->first();

        if (!$ultimaFecha) {
            return [
                'total_jugadores' => 0,
                'fecha' => null,
                'nombre_fecha' => null
            ];
        }

        // Obtener los IDs de partidos de esa fecha
        $partidosIds = Partido::where('idEdicion', $idEdicion)
            ->where('idFechas', $ultimaFecha->id)
            ->pluck('id');

        // Contar jugadores únicos (por DNI) en la planilla de esos partidos
        // Aunque un equipo juegue varios partidos, contamos cada jugador solo una vez
        $totalJugadores = PlanillaJugador::where('partido_type', 'App\Models\Partido')
            ->whereIn('partido_id', $partidosIds)
            ->where('idEdicion', $idEdicion)
            ->distinct('dni_jugador')
            ->count('dni_jugador');

        return [
            'total_jugadores' => $totalJugadores,
            'fecha' => $ultimaFecha->id,
            'nombre_fecha' => $ultimaFecha->nombre
        ];
    }

    /**
     * Obtiene estadísticas detalladas de jugadores por categoría de la última fecha
     *
     * @param int $idEdicion
     * @return array
     */
    public function obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion)
    {
        // Obtener la última fecha de la edición
        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)
            ->orderBy('id', 'desc')
            ->first();

        if (!$ultimaFecha) {
            return [];
        }

        // Obtener los IDs de partidos de esa fecha
        $partidosIds = Partido::where('idEdicion', $idEdicion)
            ->where('idFechas', $ultimaFecha->id)
            ->pluck('id');

        // Contar jugadores únicos por categoría (todos los jugadores en planilla)
        $jugadoresPorCategoria = PlanillaJugador::select('idCategoria', DB::raw('COUNT(DISTINCT dni_jugador) as total_jugadores'))
            ->where('partido_type', 'App\Models\Partido')
            ->whereIn('partido_id', $partidosIds)
            ->where('idEdicion', $idEdicion)
            ->groupBy('idCategoria')
            ->with('categoria')
            ->get();

        return $jugadoresPorCategoria->map(function ($item) {
            return [
                'categoria' => $item->categoria->nombreCategoria ?? 'Sin categoría',
                'total_jugadores' => $item->total_jugadores
            ];
        })->toArray();
    }
}
