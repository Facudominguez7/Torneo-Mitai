<?php

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\GolArbitroReportado;
use App\Models\PlanillaJugador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GolesArbitrosService
{
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
            ->first();

        if ($planillaJugador === null) {
            return [
                'idPlanillaJugador' => null,
                'es_inconsistencia' => true,
                'motivo_inconsistencia' => 'Camiseta no encontrada o no vinculada en la planilla oficial.',
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
}
