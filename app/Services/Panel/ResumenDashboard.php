<?php

namespace App\Services\Panel;

use App\Models\Fecha;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Services\EquiposDeEdicionService;
use App\Services\EstadisticasService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula los datos del nuevo dashboard admin (KPIs, partidos que requieren
 * atención y la próxima fecha), reutilizando el padrón de equipos y la
 * lógica de asistencia/goles ya existentes para no duplicar reglas.
 */
class ResumenDashboard
{
    public function __construct(
        private readonly EquiposDeEdicionService $equiposDeEdicion,
        private readonly EstadisticasService $estadisticas,
    ) {}

    /**
     * @return array{
     *     equiposInscriptos: int,
     *     jugadoresHabilitados: int,
     *     partidosJugados: int,
     *     partidosTotales: int,
     *     partidosPendientes: Collection<int, array<string, mixed>>,
     *     pendientesDeCarga: int,
     *     proximaFecha: array<string, mixed>|null,
     *     jugadoresPorCategoria: array<int, array<string, mixed>>,
     * }
     */
    public function obtenerResumen(int $idEdicion): array
    {
        $equiposInscriptos = $this->equiposDeEdicion->participantes($idEdicion)->count();

        $jugadoresHabilitados = ListaBuenaFe::where('idEdicion', $idEdicion)
            ->activos()
            ->count();

        $partidosJugados = Partido::where('idEdicion', $idEdicion)->where('jugado', 1)->count();
        $partidosTotales = Partido::where('idEdicion', $idEdicion)->count();

        $partidosEnCurso = $this->partidosDeFechasEnCurso($idEdicion);
        $partidosConAsistenciaPendiente = $this->partidosConAsistenciaPendiente($idEdicion, $partidosEnCurso);

        $partidosPendientes = $partidosEnCurso
            ->map(fn (Partido $partido): ?array => $this->normalizarPartidoPendiente($partido, $idEdicion, $partidosConAsistenciaPendiente))
            ->filter()
            ->values();

        $proximaFecha = $this->resolverProximaFecha($idEdicion);

        return [
            'equiposInscriptos' => $equiposInscriptos,
            'jugadoresHabilitados' => $jugadoresHabilitados,
            'partidosJugados' => $partidosJugados,
            'partidosTotales' => $partidosTotales,
            'partidosPendientes' => $partidosPendientes,
            'pendientesDeCarga' => $partidosPendientes->count(),
            'proximaFecha' => $proximaFecha,
            'jugadoresPorCategoria' => $this->estadisticas->obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion),
        ];
    }

    /**
     * Partidos ya disputados de la fecha en curso de cada categoría. Las
     * fechas son por categoría, así que la "última fecha" de la edición no
     * alcanza: cada categoría aporta su fecha más reciente con partidos jugados.
     *
     * @return Collection<int, Partido>
     */
    private function partidosDeFechasEnCurso(int $idEdicion): Collection
    {
        $fechaEnCursoPorCategoria = Partido::query()
            ->where('idEdicion', $idEdicion)
            ->whereNotNull('idFechas')
            ->where('horario_datetime', '<=', now())
            ->groupBy('idCategoria')
            ->selectRaw('idCategoria, MAX(idFechas) as idFecha')
            ->pluck('idFecha')
            ->filter();

        if ($fechaEnCursoPorCategoria->isEmpty()) {
            return collect();
        }

        return Partido::query()
            ->with(['equipoLocal:id,nombre', 'equipoVisitante:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->whereIn('idFechas', $fechaEnCursoPorCategoria)
            ->where('horario_datetime', '<=', now())
            ->orderBy('horario_datetime')
            ->orderBy('id')
            ->get();
    }

    /**
     * Misma regla que EstadisticasService: un equipo tiene la asistencia
     * pendiente si no tiene planilla o no marcó ninguna asistencia.
     *
     * @param  Collection<int, Partido>  $partidos
     * @return Collection<int, int>
     */
    private function partidosConAsistenciaPendiente(int $idEdicion, Collection $partidos): Collection
    {
        if ($partidos->isEmpty()) {
            return collect();
        }

        $asistenciasPorEquipo = PlanillaJugador::query()
            ->select('partido_id', 'idEquipo', DB::raw('SUM(CASE WHEN asistio = 1 THEN 1 ELSE 0 END) as total_asistencias'))
            ->where('partido_type', Partido::class)
            ->where('idEdicion', $idEdicion)
            ->whereIn('partido_id', $partidos->pluck('id'))
            ->groupBy('partido_id', 'idEquipo')
            ->get()
            ->keyBy(fn ($fila): string => $fila->partido_id.'-'.$fila->idEquipo);

        $sinAsistencia = function (Partido $partido, ?int $idEquipo) use ($asistenciasPorEquipo): bool {
            $fila = $asistenciasPorEquipo->get($partido->id.'-'.$idEquipo);

            return $fila === null || (int) $fila->total_asistencias === 0;
        };

        return $partidos
            ->filter(fn (Partido $partido): bool => $sinAsistencia($partido, $partido->idEquipoLocal) || $sinAsistencia($partido, $partido->idEquipoVisitante))
            ->pluck('id')
            ->values();
    }

    /**
     * @param  Collection<int, int>  $partidosConAsistenciaPendiente
     * @return array<string, mixed>|null
     */
    private function normalizarPartidoPendiente(Partido $partido, int $idEdicion, Collection $partidosConAsistenciaPendiente): ?array
    {
        // Un partido que todavía no se jugó no puede tener cargas pendientes.
        $yaSeJugo = $partido->horario_datetime !== null && Carbon::parse($partido->horario_datetime)->lessThanOrEqualTo(now());
        if (! $yaSeJugo) {
            return null;
        }

        $sinResultado = $partido->golesEquipoLocal === null || $partido->golesEquipoVisitante === null;
        $golesSinConfirmar = ! $sinResultado && $partido->goles_confirmados_at === null;
        $sinAsistencia = $partidosConAsistenciaPendiente->contains($partido->id);

        if (! $sinResultado && ! $golesSinConfirmar && ! $sinAsistencia) {
            return null;
        }

        if ($sinResultado) {
            $estado = [
                'tipo' => 'sin_resultado',
                'label' => 'Sin resultado',
                'clase' => 'bg-red-100 text-red-700',
            ];
            $accionUrl = route('cargar-resultado', array_filter([
                'idPartido' => $partido->id,
                'idEdicion' => $idEdicion,
                'idCategoria' => $partido->idCategoria,
                'idGrupo' => $partido->idGrupo,
                'idFecha' => $partido->idFechas,
            ], static fn ($valor) => $valor !== null));
            $accionLabel = 'Cargar resultado';
        } else {
            $estado = $golesSinConfirmar
                ? ['tipo' => 'goles_sin_confirmar', 'label' => 'Goles sin confirmar', 'clase' => 'bg-amber-100 text-amber-700']
                : ['tipo' => 'sin_asistencia', 'label' => 'Sin asistencia', 'clase' => 'bg-amber-100 text-amber-700'];

            $accionUrl = route('planilla.show', [
                'partidoId' => $partido->id,
                'idEdicion' => $idEdicion,
                'tipoPartido' => 'partido',
                'horario' => 0,
            ]);
            $accionLabel = 'Ver planilla';
        }

        return [
            'id' => $partido->id,
            'categoria' => $partido->categoria?->nombreCategoria ?? 'Sin categoría',
            'local' => $partido->equipoLocal?->nombre ?? 'Equipo sin nombre',
            'visitante' => $partido->equipoVisitante?->nombre ?? 'Equipo sin nombre',
            'golesEquipoLocal' => $partido->golesEquipoLocal,
            'golesEquipoVisitante' => $partido->golesEquipoVisitante,
            'horario' => $partido->horario_datetime,
            'estado' => $estado,
            'accionUrl' => $accionUrl,
            'accionLabel' => $accionLabel,
        ];
    }

    /**
     * La próxima fecha es la del primer partido futuro sin resultado, no la
     * última fecha creada (que puede estar ya disputada). La tarjeta resume
     * solo los partidos de esa fecha que todavía faltan jugar.
     *
     * @return array<string, mixed>|null
     */
    private function resolverProximaFecha(int $idEdicion): ?array
    {
        $partidosPorJugar = fn () => Partido::query()
            ->where('idEdicion', $idEdicion)
            ->whereNotNull('idFechas')
            ->where('horario_datetime', '>=', now())
            ->where(fn ($query) => $query->whereNull('golesEquipoLocal')->orWhereNull('golesEquipoVisitante'));

        $proximoPartido = $partidosPorJugar()->orderBy('horario_datetime')->first();
        $fecha = $proximoPartido ? Fecha::find($proximoPartido->idFechas) : null;

        if (! $fecha) {
            return null;
        }

        $partidos = $partidosPorJugar()
            ->where('idFechas', $fecha->id)
            ->get(['id', 'cancha', 'horario_datetime']);

        return $this->construirProximaFecha($fecha, $partidos);
    }

    /**
     * @param  Collection<int, Partido>  $partidos
     * @return array<string, mixed>
     */
    private function construirProximaFecha(Fecha $fecha, Collection $partidos): array
    {
        $primerPartido = $partidos
            ->filter(fn (Partido $partido): bool => $partido->horario_datetime !== null)
            ->sortBy('horario_datetime')
            ->first();

        return [
            'id' => $fecha->id,
            'nombre' => $fecha->nombre,
            'fecha' => $primerPartido?->horario_datetime ? Carbon::parse($primerPartido->horario_datetime)->format('d/m/Y') : null,
            'cantidadPartidos' => $partidos->count(),
            'cantidadCanchas' => $partidos->pluck('cancha')->filter()->unique()->count(),
            'primerHorario' => $primerPartido?->horario_datetime ? Carbon::parse($primerPartido->horario_datetime)->format('H:i') : null,
        ];
    }
}
