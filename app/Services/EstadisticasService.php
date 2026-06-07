<?php

namespace App\Services;

use App\Models\Fecha;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EstadisticasService
{
    private const TIPO_PARTIDO = 'App\\Models\\Partido';

    private const TIPO_INSTANCIA_FINAL = InstanciaFinal::class;

    /**
     * Obtiene el total de jugadores únicos de la última fecha de una edición
     *
     * @param  int  $idEdicion
     * @return array
     */
    public function obtenerJugadoresDosUltimasFechas($idEdicion)
    {
        // Obtener la última fecha de la edición
        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)
            ->orderBy('id', 'desc')
            ->first();

        if (! $ultimaFecha) {
            return [
                'total_jugadores' => 0,
                'fecha' => null,
                'nombre_fecha' => null,
            ];
        }

        // Obtener los IDs de partidos de esa fecha
        $partidosIds = Partido::where('idEdicion', $idEdicion)
            ->where('idFechas', $ultimaFecha->id)
            ->pluck('id');

        // Contar jugadores únicos (por DNI) en la planilla de esos partidos
        // Aunque un equipo juegue varios partidos, contamos cada jugador solo una vez
        $totalJugadores = PlanillaJugador::where('partido_type', self::TIPO_PARTIDO)
            ->whereIn('partido_id', $partidosIds)
            ->where('idEdicion', $idEdicion)
            ->distinct('dni_jugador')
            ->count('dni_jugador');

        return [
            'total_jugadores' => $totalJugadores,
            'fecha' => $ultimaFecha->id,
            'nombre_fecha' => $ultimaFecha->nombre,
        ];
    }

    /**
     * Obtiene estadísticas detalladas de jugadores por categoría de la última fecha
     *
     * @param  int  $idEdicion
     * @return array
     */
    public function obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion)
    {
        // Obtener la última fecha de la edición
        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)
            ->orderBy('id', 'desc')
            ->first();

        if (! $ultimaFecha) {
            return [];
        }

        // Obtener los IDs de partidos de esa fecha
        $partidosIds = Partido::where('idEdicion', $idEdicion)
            ->where('idFechas', $ultimaFecha->id)
            ->pluck('id');

        // Contar jugadores únicos por categoría (todos los jugadores en planilla)
        $jugadoresPorCategoria = PlanillaJugador::select('idCategoria', DB::raw('COUNT(DISTINCT dni_jugador) as total_jugadores'))
            ->where('partido_type', self::TIPO_PARTIDO)
            ->whereIn('partido_id', $partidosIds)
            ->where('idEdicion', $idEdicion)
            ->groupBy('idCategoria')
            ->with('categoria')
            ->get();

        return $jugadoresPorCategoria->map(function ($item) {
            return [
                'categoria' => $item->categoria->nombreCategoria ?? 'Sin categoría',
                'total_jugadores' => $item->total_jugadores,
            ];
        })->toArray();
    }

    /**
     * Obtiene los equipos con asistencia pendiente para la ultima fecha visible
     * de una edicion, respetando el filtro horario del panel admin.
     */
    public function obtenerEquiposSinAsistenciaUltimaFecha(?int $idEdicion, ?string $horario = null): array
    {
        if (empty($idEdicion)) {
            return $this->respuestaPendientesVacia();
        }

        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)
            ->orderByDesc('id')
            ->first();

        if (! $ultimaFecha) {
            return $this->respuestaPendientesVacia();
        }

        $partidos = $this->obtenerPartidosVisiblesUltimaFecha($idEdicion, $ultimaFecha->id, $horario);

        if ($partidos->isEmpty()) {
            return $this->respuestaPendientesVacia($ultimaFecha->nombre, $horario);
        }

        $resumenPlanillas = PlanillaJugador::query()
            ->select(
                'partido_id',
                'idEquipo',
                DB::raw('COUNT(*) as total_planilla'),
                DB::raw('SUM(CASE WHEN asistio = 1 THEN 1 ELSE 0 END) as total_asistencias')
            )
            ->where('partido_type', self::TIPO_PARTIDO)
            ->where('idEdicion', $idEdicion)
            ->whereIn('partido_id', $partidos->pluck('id'))
            ->groupBy('partido_id', 'idEquipo')
            ->get()
            ->keyBy(fn ($item) => $item->partido_id.'-'.$item->idEquipo);

        $equiposPendientes = [];

        foreach ($partidos as $partido) {
            $equipoLocal = $this->normalizarPendienteEquipo($partido, $resumenPlanillas, true);
            $equipoVisitante = $this->normalizarPendienteEquipo($partido, $resumenPlanillas, false);

            if ($equipoLocal !== null) {
                $equiposPendientes[] = $equipoLocal;
            }

            if ($equipoVisitante !== null) {
                $equiposPendientes[] = $equipoVisitante;
            }
        }

        return [
            'nombre_fecha' => $ultimaFecha->nombre,
            'horario_filtrado' => $horario,
            'total_pendientes' => count($equiposPendientes),
            'equipos' => $equiposPendientes,
        ];
    }

    /**
     * Obtiene los equipos con asistencia pendiente para instancias finales ya filtradas.
     */
    public function obtenerEquiposSinAsistenciaInstanciasFinales(Collection $partidos, ?int $idEdicion = null): array
    {
        if ($partidos->isEmpty()) {
            return $this->respuestaPendientesVacia();
        }

        $resumenPlanillas = PlanillaJugador::query()
            ->select(
                'partido_id',
                'idEquipo',
                DB::raw('COUNT(*) as total_planilla'),
                DB::raw('SUM(CASE WHEN asistio = 1 THEN 1 ELSE 0 END) as total_asistencias')
            )
            ->where('partido_type', self::TIPO_INSTANCIA_FINAL)
            ->when($idEdicion, fn ($query) => $query->where('idEdicion', $idEdicion))
            ->whereIn('partido_id', $partidos->pluck('id'))
            ->groupBy('partido_id', 'idEquipo')
            ->get()
            ->keyBy(fn ($item) => $item->partido_id.'-'.$item->idEquipo);

        $equiposPendientes = [];

        foreach ($partidos as $partido) {
            $equipoLocal = $this->normalizarPendienteEquipoInstanciaFinal($partido, $resumenPlanillas, true);
            $equipoVisitante = $this->normalizarPendienteEquipoInstanciaFinal($partido, $resumenPlanillas, false);

            if ($equipoLocal !== null) {
                $equiposPendientes[] = $equipoLocal;
            }

            if ($equipoVisitante !== null) {
                $equiposPendientes[] = $equipoVisitante;
            }
        }

        return [
            'nombre_fecha' => null,
            'horario_filtrado' => null,
            'total_pendientes' => count($equiposPendientes),
            'equipos' => $equiposPendientes,
        ];
    }

    private function obtenerPartidosVisiblesUltimaFecha(int $idEdicion, int $fechaId, ?string $horario = null): Collection
    {
        return Partido::query()
            ->with(['equipoLocal', 'equipoVisitante', 'categoria', 'fecha'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $fechaId)
            ->when($horario, function ($query) use ($horario) {
                $query->whereRaw('TIME(horario_datetime) = ?', [$horario.':00']);
            })
            ->orderBy('horario_datetime')
            ->orderBy('id')
            ->get();
    }

    private function normalizarPendienteEquipo(Partido $partido, $resumenPlanillas, bool $esLocal): ?array
    {
        $equipo = $esLocal ? $partido->equipoLocal : $partido->equipoVisitante;
        $rival = $esLocal ? $partido->equipoVisitante : $partido->equipoLocal;
        $equipoId = $esLocal ? $partido->idEquipoLocal : $partido->idEquipoVisitante;
        $resumen = $resumenPlanillas->get($partido->id.'-'.$equipoId);

        if ($resumen === null) {
            $estado = 'sin_planilla';
        } elseif ((int) $resumen->total_asistencias === 0) {
            $estado = 'sin_asistencias_marcadas';
        } else {
            return null;
        }

        return [
            'partido_id' => $partido->id,
            'partido_type' => self::TIPO_PARTIDO,
            'partido_key' => self::TIPO_PARTIDO.'-'.$partido->id,
            'equipo_id' => $equipoId,
            'equipo' => $equipo?->nombre ?? 'Equipo sin nombre',
            'rival' => $rival?->nombre ?? 'Rival sin nombre',
            'categoria' => $partido->categoria?->nombreCategoria ?? 'Sin categoria',
            'fecha' => $partido->fecha?->nombre ?? null,
            'horario' => $partido->horario_datetime ? Carbon::parse($partido->horario_datetime)->format('H:i') : null,
            'cancha' => $partido->cancha,
            'condicion' => $esLocal ? 'Local' : 'Visitante',
            'estado' => $estado,
            'estado_label' => $estado === 'sin_planilla' ? 'Sin planilla' : 'Sin asistencias marcadas',
        ];
    }

    private function normalizarPendienteEquipoInstanciaFinal(InstanciaFinal $partido, $resumenPlanillas, bool $esLocal): ?array
    {
        $equipo = $esLocal ? $partido->equipoLocal : $partido->equipoVisitante;
        $rival = $esLocal ? $partido->equipoVisitante : $partido->equipoLocal;
        $equipoId = $esLocal ? $partido->idEquipoLocal : $partido->idEquipoVisitante;
        $resumen = $resumenPlanillas->get($partido->id.'-'.$equipoId);

        if ($resumen === null) {
            $estado = 'sin_planilla';
        } elseif ((int) $resumen->total_asistencias === 0) {
            $estado = 'sin_asistencias_marcadas';
        } else {
            return null;
        }

        return [
            'partido_id' => $partido->id,
            'partido_type' => self::TIPO_INSTANCIA_FINAL,
            'partido_key' => self::TIPO_INSTANCIA_FINAL.'-'.$partido->id,
            'equipo_id' => $equipoId,
            'equipo' => $equipo?->nombre ?? 'Equipo sin nombre',
            'rival' => $rival?->nombre ?? 'Rival sin nombre',
            'categoria' => $partido->categoria?->nombreCategoria ?? 'Sin categoria',
            'fecha' => null,
            'horario' => $partido->horario ? Carbon::parse($partido->horario)->format('H:i') : null,
            'cancha' => $partido->cancha,
            'condicion' => $esLocal ? 'Local' : 'Visitante',
            'estado' => $estado,
            'estado_label' => $estado === 'sin_planilla' ? 'Sin planilla' : 'Sin asistencias marcadas',
        ];
    }

    private function respuestaPendientesVacia(?string $nombreFecha = null, ?string $horario = null): array
    {
        return [
            'nombre_fecha' => $nombreFecha,
            'horario_filtrado' => $horario,
            'total_pendientes' => 0,
            'equipos' => [],
        ];
    }
}
