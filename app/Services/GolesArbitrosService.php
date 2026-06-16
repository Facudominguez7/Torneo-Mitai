<?php

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\GolArbitroReportado;
use App\Models\PlanillaJugador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GolesArbitrosService
{
    /**
     * @return array{aplicado: bool, bloqueado: bool, goles_aplicados: int, mensaje: string}
     */
    public function aplicarReportesAPlanillaOficial(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo): array
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        if ($this->equipoTieneGolesOficiales($partidoId, $partidoType, $idEdicion, $idEquipo)) {
            return [
                'aplicado' => false,
                'bloqueado' => true,
                'goles_aplicados' => 0,
                'mensaje' => 'No se aplicaron goles arbitrales: el equipo ya tiene goles oficiales cargados.',
            ];
        }

        return DB::transaction(function () use ($partidoId, $partidoType, $idEdicion, $idEquipo): array {
            if ($this->equipoTieneGolesOficiales($partidoId, $partidoType, $idEdicion, $idEquipo, true)) {
                return [
                    'aplicado' => false,
                    'bloqueado' => true,
                    'goles_aplicados' => 0,
                    'mensaje' => 'No se aplicaron goles arbitrales: el equipo ya tiene goles oficiales cargados.',
                ];
            }

            $golesPorPlanilla = GolArbitroReportado::query()
                ->where('partido_id', $partidoId)
                ->where('partido_type', $partidoType)
                ->where('idEdicion', $idEdicion)
                ->where('idEquipo', $idEquipo)
                ->where('es_inconsistencia', false)
                ->whereNotNull('idPlanillaJugador')
                ->selectRaw('idPlanillaJugador, SUM(goles_reportados) as goles_reportados')
                ->groupBy('idPlanillaJugador')
                ->get();

            if ($golesPorPlanilla->isEmpty()) {
                return [
                    'aplicado' => false,
                    'bloqueado' => false,
                    'goles_aplicados' => 0,
                    'mensaje' => 'No hay goles arbitrales vinculados para aplicar en este equipo.',
                ];
            }

            $planillasIds = $golesPorPlanilla->pluck('idPlanillaJugador')->all();

            $planillas = PlanillaJugador::query()
                ->where('partido_id', $partidoId)
                ->where('partido_type', $partidoType)
                ->where('idEdicion', $idEdicion)
                ->where('idEquipo', $idEquipo)
                ->whereIn('id', $planillasIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalAplicado = 0;

            foreach ($golesPorPlanilla as $fila) {
                $planilla = $planillas->get((int) $fila->idPlanillaJugador);

                if ($planilla === null) {
                    continue;
                }

                $golesReportados = (int) $fila->goles_reportados;
                $planilla->goles = $golesReportados;
                $planilla->save();
                $totalAplicado += $golesReportados;
            }

            return [
                'aplicado' => true,
                'bloqueado' => false,
                'goles_aplicados' => $totalAplicado,
                'mensaje' => 'Goles arbitrales aplicados sobre la planilla oficial del equipo.',
            ];
        }, 3);
    }

    /**
     * @return array{aplicables: array<int, array<string, mixed>>, ignorados: array<int, array<string, mixed>>, ambiguos: array<int, array<string, mixed>>}
     */
    public function obtenerCandidatosAplicables(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo): array
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        $reportes = GolArbitroReportado::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->orderBy('id')
            ->get();

        $duplicadosPorCamiseta = PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->where('asistio', true)
            ->selectRaw('numero_camiseta, COUNT(*) as total')
            ->groupBy('numero_camiseta')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'numero_camiseta');

        $aplicables = [];
        $ignorados = [];
        $ambiguos = [];

        foreach ($reportes as $reporte) {
            $filaBase = [
                'idReporte' => (int) $reporte->id,
                'idPlanillaJugador' => $reporte->idPlanillaJugador !== null ? (int) $reporte->idPlanillaJugador : null,
                'numeroCamiseta' => $reporte->numero_camiseta !== null ? (int) $reporte->numero_camiseta : null,
                'golesReportados' => (int) $reporte->goles_reportados,
            ];

            if ((bool) $reporte->es_inconsistencia || $reporte->idPlanillaJugador === null || (int) $reporte->goles_reportados <= 0) {
                $ignorados[] = $filaBase;

                continue;
            }

            $numeroCamiseta = $reporte->numero_camiseta !== null ? (int) $reporte->numero_camiseta : null;
            if ($numeroCamiseta !== null && $duplicadosPorCamiseta->has($numeroCamiseta)) {
                $ambiguos[] = $filaBase;

                continue;
            }

            $aplicables[] = $filaBase;
        }

        return [
            'aplicables' => $aplicables,
            'ignorados' => $ignorados,
            'ambiguos' => $ambiguos,
        ];
    }

    public function obtenerAdvertenciaDiferenciaConResultadoOficial(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo): ?string
    {
        $partido = app(PlanillaConfirmacionService::class)->resolverPartido($tipoPartido, $partidoId, $idEdicion);

        $sumaIndividual = (int) PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', PartidoConstants::getModelClass($tipoPartido))
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->sum('goles');

        $golesOficiales = (int) ($partido->idEquipoLocal === $idEquipo ? $partido->golesEquipoLocal : $partido->golesEquipoVisitante);

        if ($sumaIndividual === $golesOficiales) {
            return null;
        }

        return 'Advertencia: la suma de goles individuales del equipo ('.$sumaIndividual.') difiere del resultado oficial ('.$golesOficiales.').';
    }

    public function guardarReportes(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo, array $reportes): void
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);
        $reportesNormalizados = $this->normalizarReportes($reportes);

        DB::transaction(function () use ($partidoId, $partidoType, $idEdicion, $idEquipo, $reportesNormalizados): void {
            GolArbitroReportado::query()
                ->where('partido_id', $partidoId)
                ->where('partido_type', $partidoType)
                ->where('idEdicion', $idEdicion)
                ->where('idEquipo', $idEquipo)
                ->delete();

            foreach ($reportesNormalizados as $reporte) {
                $resolucion = $this->resolverVinculacion(
                    $partidoId,
                    $partidoType,
                    $idEdicion,
                    $idEquipo,
                    $reporte['numero_camiseta']
                );

                GolArbitroReportado::create([
                    'partido_id' => $partidoId,
                    'partido_type' => $partidoType,
                    'idEdicion' => $idEdicion,
                    'idEquipo' => $idEquipo,
                    'idPlanillaJugador' => $resolucion['idPlanillaJugador'],
                    'numero_camiseta' => $reporte['numero_camiseta'],
                    'goles_reportados' => $reporte['goles_reportados'],
                    'es_inconsistencia' => $resolucion['es_inconsistencia'],
                    'motivo_inconsistencia' => $resolucion['motivo_inconsistencia'],
                ]);
            }
        });
    }

    public function sincronizarVinculaciones(string $tipoPartido, int $partidoId, int $idEdicion): void
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        GolArbitroReportado::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->get()
            ->each(function (GolArbitroReportado $reporte) use ($partidoId, $partidoType, $idEdicion): void {
                $resolucion = $this->resolverVinculacion(
                    $partidoId,
                    $partidoType,
                    $idEdicion,
                    (int) $reporte->idEquipo,
                    $reporte->numero_camiseta !== null ? (int) $reporte->numero_camiseta : null
                );

                $reporte->forceFill($resolucion)->save();
            });
    }

    public function obtenerResumenPorEquipos(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipoLocal, int $idEquipoVisitante): array
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        $reportes = GolArbitroReportado::query()
            ->with('planillaJugador.jugador')
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->whereIn('idEquipo', [$idEquipoLocal, $idEquipoVisitante])
            ->orderBy('idEquipo')
            ->orderByRaw('CASE WHEN numero_camiseta IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('numero_camiseta')
            ->get();

        return [
            'local' => $this->formatearResumenEquipo($reportes->where('idEquipo', $idEquipoLocal)->values()),
            'visitante' => $this->formatearResumenEquipo($reportes->where('idEquipo', $idEquipoVisitante)->values()),
        ];
    }

    private function normalizarReportes(array $reportes): array
    {
        return collect($reportes)
            ->map(function ($reporte): array {
                $numeroCamiseta = $this->normalizarEntero(data_get($reporte, 'numero_camiseta'));
                $golesReportados = max(0, (int) data_get($reporte, 'goles_reportados', 0));

                return [
                    'numero_camiseta' => $numeroCamiseta,
                    'goles_reportados' => $golesReportados,
                ];
            })
            ->filter(fn (array $reporte): bool => $reporte['goles_reportados'] > 0)
            ->values()
            ->all();
    }

    private function resolverVinculacion(int $partidoId, string $partidoType, int $idEdicion, int $idEquipo, ?int $numeroCamiseta): array
    {
        if ($numeroCamiseta === null) {
            return [
                'idPlanillaJugador' => null,
                'es_inconsistencia' => true,
                'motivo_inconsistencia' => 'Camiseta no informada por arbitros.',
            ];
        }

        $planillaJugador = PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->where('numero_camiseta', $numeroCamiseta)
            ->where('asistio', true)
            ->first();

        if ($planillaJugador === null) {
            // Se distinguen las dos causas reales para no dar a entender que el
            // jugador faltó al partido cuando en realidad es un dato sin vincular.
            $camisetaCargada = PlanillaJugador::query()
                ->where('partido_id', $partidoId)
                ->where('partido_type', $partidoType)
                ->where('idEdicion', $idEdicion)
                ->where('idEquipo', $idEquipo)
                ->where('numero_camiseta', $numeroCamiseta)
                ->exists();

            $motivo = $camisetaCargada
                ? "El jugador con la camiseta N° {$numeroCamiseta} figura en la planilla pero no está marcado como presente en este partido, por eso el gol no se vinculó."
                : "El número de camiseta N° {$numeroCamiseta} reportado por los árbitros no figura en la planilla oficial de este equipo. El gol no se vinculó a ningún jugador.";

            return [
                'idPlanillaJugador' => null,
                'es_inconsistencia' => true,
                'motivo_inconsistencia' => $motivo,
            ];
        }

        return [
            'idPlanillaJugador' => $planillaJugador->id,
            'es_inconsistencia' => false,
            'motivo_inconsistencia' => null,
        ];
    }

    private function formatearResumenEquipo(Collection $reportes): array
    {
        return [
            'total_reportado' => (int) $reportes->sum('goles_reportados'),
            'total_inconsistencias' => $reportes->where('es_inconsistencia', true)->count(),
            'reportes' => $reportes->map(function (GolArbitroReportado $reporte): array {
                $jugador = $reporte->planillaJugador?->jugador;

                return [
                    'id' => (int) $reporte->id,
                    'numero_camiseta' => $reporte->numero_camiseta !== null ? (int) $reporte->numero_camiseta : null,
                    'goles_reportados' => (int) $reporte->goles_reportados,
                    'jugador_oficial' => $jugador ? trim($jugador->apellido.', '.$jugador->nombre, ' ,') : null,
                    'es_inconsistencia' => (bool) $reporte->es_inconsistencia,
                    'motivo_inconsistencia' => $reporte->motivo_inconsistencia,
                ];
            })->values()->all(),
        ];
    }

    private function normalizarEntero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    public function equipoTieneGolesOficiales(int $partidoId, string $partidoType, int $idEdicion, int $idEquipo, bool $forUpdate = false): bool
    {
        $consulta = PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->where('goles', '>', 0);

        if ($forUpdate) {
            $consulta->lockForUpdate();
        }

        return $consulta->exists();
    }
}
