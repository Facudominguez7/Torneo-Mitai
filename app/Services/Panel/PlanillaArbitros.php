<?php

namespace App\Services\Panel;

use App\Models\InstanciaFinal;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reúne en una sola planilla de árbitros los partidos regulares y las instancias
 * finales pendientes de una única jornada: la próxima desde hoy o, si no hay,
 * la última con partidos sin resultado. Así no se imprimen partidos viejos que
 * quedaron sin cargar ni la jornada queda tapada por un partido reprogramado.
 */
class PlanillaArbitros
{
    /**
     * @return Collection<int, array{
     *     tipo: string,
     *     horario: ?Carbon,
     *     cancha: mixed,
     *     categoria: string,
     *     detalle: array<int, string>,
     *     local: string,
     *     visitante: string,
     * }>
     */
    public function partidosPendientes(int $idEdicion, ?string $horario = null): Collection
    {
        $jornada = $this->jornadaPendiente($idEdicion);

        return $this->partidosRegulares($idEdicion, $horario, $jornada)
            ->concat($this->instanciasFinales($idEdicion, $horario, $jornada))
            ->sortBy([
                fn (array $a, array $b): int => ($a['horario']?->timestamp ?? PHP_INT_MAX) <=> ($b['horario']?->timestamp ?? PHP_INT_MAX),
                fn (array $a, array $b): int => (string) $a['cancha'] <=> (string) $b['cancha'],
                fn (array $a, array $b): int => $b['categoria'] <=> $a['categoria'],
            ])
            ->values();
    }

    /**
     * Día (Y-m-d) a imprimir: el primero pendiente desde hoy o, si ya pasaron
     * todos, el último con partidos sin resultado.
     */
    private function jornadaPendiente(int $idEdicion): ?string
    {
        $diasRegulares = $this->partidosSinResultado($idEdicion)
            ->whereNotNull('horario_datetime')
            ->toBase()
            ->selectRaw('DATE(horario_datetime) as dia')
            ->distinct()
            ->pluck('dia');

        $diasFinales = InstanciaFinal::query()
            ->where('idEdicion', $idEdicion)
            ->whereNull('golesEquipoLocal')
            ->whereNull('golesEquipoVisitante')
            ->whereNotNull('horario')
            ->toBase()
            ->selectRaw('DATE(horario) as dia')
            ->distinct()
            ->pluck('dia');

        $dias = $diasRegulares->concat($diasFinales)->map(fn ($dia): string => (string) $dia)->unique()->sort()->values();
        $hoy = now()->toDateString();

        return $dias->first(fn (string $dia): bool => $dia >= $hoy) ?? $dias->last();
    }

    private function partidosSinResultado(int $idEdicion): Builder
    {
        return Partido::query()
            ->where('idEdicion', $idEdicion)
            ->whereNull('golesEquipoLocal')
            ->whereNull('golesEquipoVisitante');
    }

    /**
     * Partidos del día y los sin horario de sus mismas fechas. Si ninguno tiene
     * día, los de la última fecha pendiente.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function partidosRegulares(int $idEdicion, ?string $horario, ?string $jornada): Collection
    {
        $idsFechas = $jornada
            ? $this->partidosSinResultado($idEdicion)->whereRaw('DATE(horario_datetime) = ?', [$jornada])->distinct()->pluck('idFechas')
            : collect([$this->partidosSinResultado($idEdicion)->max('idFechas')])->filter();

        if (! $jornada && $idsFechas->isEmpty()) {
            return collect();
        }

        return Partido::query()
            ->select(
                'partidos.*',
                'el.nombre as nombre_local',
                'ev.nombre as nombre_visitante',
                'f.nombre as nombre_fecha',
                'cat.nombreCategoria as nombre_categoria',
                DB::raw("COALESCE(g.nombre, '') as nombre_grupo")
            )
            ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
            ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
            ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
            ->leftJoin('grupos as g', 'partidos.idGrupo', '=', 'g.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->whereNull('partidos.golesEquipoLocal')
            ->whereNull('partidos.golesEquipoVisitante')
            ->where(fn ($query) => $query
                ->when($jornada, fn ($delDia) => $delDia->whereRaw('DATE(partidos.horario_datetime) = ?', [$jornada]))
                ->orWhere(fn ($sinHorario) => $sinHorario
                    ->whereNull('partidos.horario_datetime')
                    ->whereIn('partidos.idFechas', $idsFechas)))
            ->when($horario, fn ($query) => $query->whereRaw('TIME(partidos.horario_datetime) = ?', [$horario.':00']))
            ->get()
            ->map(fn (Partido $partido): array => [
                'tipo' => 'partido',
                'horario' => $partido->horario_datetime ? Carbon::parse($partido->horario_datetime) : null,
                'cancha' => $partido->cancha,
                'categoria' => (string) ($partido->nombre_categoria ?? 'Sin categoría'),
                'detalle' => $this->detallePartido($partido),
                'local' => (string) $partido->nombre_local,
                'visitante' => (string) $partido->nombre_visitante,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function instanciasFinales(int $idEdicion, ?string $horario, ?string $jornada): Collection
    {
        if (! $jornada) {
            return collect();
        }

        return InstanciaFinal::query()
            ->select(
                'instancias_finales.*',
                'fases.nombre as nombre_fase',
                'el.nombre as nombre_local',
                'ev.nombre as nombre_visitante',
                'categorias.nombreCategoria as nombre_categoria',
                DB::raw("COALESCE(copas.nombre, '') as nombre_copa")
            )
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftJoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->whereNull('instancias_finales.golesEquipoLocal')
            ->whereNull('instancias_finales.golesEquipoVisitante')
            ->whereRaw('DATE(instancias_finales.horario) = ?', [$jornada])
            ->when($horario, fn ($query) => $query->whereRaw('TIME(instancias_finales.horario) = ?', [$horario.':00']))
            ->orderBy('copas.secuencia')
            ->orderBy('fases.secuencia')
            ->get()
            ->map(fn (InstanciaFinal $final): array => [
                'tipo' => 'final',
                'horario' => $final->horario ? Carbon::parse($final->horario) : null,
                'cancha' => $final->cancha,
                'categoria' => (string) $final->nombre_categoria,
                'detalle' => array_values(array_filter([$final->nombre_copa, $final->nombre_fase])),
                'local' => (string) $final->nombre_local,
                'visitante' => (string) $final->nombre_visitante,
            ]);
    }

    /**
     * Fecha, grupo (si la fecha no lo nombra ya) e interzonal.
     *
     * @return array<int, string>
     */
    private function detallePartido(Partido $partido): array
    {
        $nombreFecha = $partido->nombre_fecha ?? 'Fecha sin nombre';
        $nombreGrupo = trim((string) ($partido->nombre_grupo ?? ''));
        $fechaNormalizada = mb_strtolower($nombreFecha);
        $grupoNormalizado = mb_strtolower($nombreGrupo);
        $mostrarGrupo = $nombreGrupo !== ''
            && ! $partido->es_interzonal
            && ! str_contains($fechaNormalizada, 'grupo '.$grupoNormalizado)
            && ! str_contains($fechaNormalizada, $grupoNormalizado.' grupo');

        $detalle = [$nombreFecha];
        if ($mostrarGrupo) {
            $detalle[] = str_starts_with($grupoNormalizado, 'grupo ') ? $nombreGrupo : 'Grupo '.$nombreGrupo;
        }
        if ($partido->es_interzonal) {
            $detalle[] = 'Interzonal';
        }

        return $detalle;
    }
}
