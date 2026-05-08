<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\PartidoConstants;
use App\DTO\AplicacionGolesArbitralesResultado;
use App\Models\PlanillaJugador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AplicarGolesArbitralesService
{
    public function __construct(
        private readonly GolesArbitrosService $golesArbitrosService,
        private readonly EstadisticasJugadorService $estadisticasJugadorService,
        private readonly PlanillaConfirmacionService $planillaConfirmacionService,
    ) {}

    public function aplicarPorEquipo(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo): AplicacionGolesArbitralesResultado
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        if ($this->golesArbitrosService->equipoTieneGolesOficiales($partidoId, $partidoType, $idEdicion, $idEquipo)) {
            return new AplicacionGolesArbitralesResultado(
                'bloqueado',
                'No se aplicaron goles arbitrales: el equipo ya tiene goles oficiales cargados.',
                false,
                false,
                [],
                null,
            );
        }

        return DB::transaction(function () use ($tipoPartido, $partidoType, $partidoId, $idEdicion, $idEquipo): AplicacionGolesArbitralesResultado {
            if ($this->golesArbitrosService->equipoTieneGolesOficiales($partidoId, $partidoType, $idEdicion, $idEquipo, true)) {
                return new AplicacionGolesArbitralesResultado(
                    'bloqueado',
                    'No se aplicaron goles arbitrales: el equipo ya tiene goles oficiales cargados.',
                    false,
                    false,
                    [],
                    null,
                );
            }

            $candidatos = $this->golesArbitrosService->obtenerCandidatosAplicables($tipoPartido, $partidoId, $idEdicion, $idEquipo);
            /** @var Collection<int, array<string, mixed>> $aplicables */
            $aplicables = collect($candidatos['aplicables']);

            if ($aplicables->isEmpty()) {
                return new AplicacionGolesArbitralesResultado(
                    'sin_cambios',
                    'No hay goles arbitrales vinculados para aplicar en este equipo.',
                    false,
                    false,
                    [],
                    null,
                );
            }

            $resultados = [];

            foreach ($aplicables as $aplicable) {
                $planilla = PlanillaJugador::query()
                    ->whereKey((int) $aplicable['idPlanillaJugador'])
                    ->lockForUpdate()
                    ->first();

                if ($planilla === null) {
                    continue;
                }

                $golesAnteriores = (int) $planilla->goles;
                $golesReportados = (int) $aplicable['golesReportados'];
                $delta = $golesReportados - $golesAnteriores;

                if ($delta === 0) {
                    continue;
                }

                $planilla->goles = $golesReportados;
                $planilla->save();

                $this->estadisticasJugadorService->actualizarDeltaGoles(
                    (string) $planilla->dni_jugador,
                    $idEdicion,
                    (int) $planilla->idCategoria,
                    (int) $planilla->idEquipo,
                    $delta,
                );

                $resultados[] = [
                    'dni_jugador' => (string) $planilla->dni_jugador,
                    'goles_anteriores' => $golesAnteriores,
                    'goles_nuevos' => $golesReportados,
                ];
            }

            if ($resultados === []) {
                return new AplicacionGolesArbitralesResultado(
                    'sin_cambios',
                    'No hubo cambios para aplicar en la planilla oficial.',
                    false,
                    false,
                    [],
                    null,
                );
            }

            $confirmacionInvalidada = $this->planillaConfirmacionService->invalidarConfirmacionPorEdicion(
                $tipoPartido,
                $partidoId,
                $idEdicion,
            );

            $this->golesArbitrosService->sincronizarVinculaciones($tipoPartido, $partidoId, $idEdicion);

            return new AplicacionGolesArbitralesResultado(
                'ok',
                'Goles arbitrales aplicados sobre la planilla oficial del equipo.',
                true,
                $confirmacionInvalidada,
                $resultados,
                $this->golesArbitrosService->obtenerAdvertenciaDiferenciaConResultadoOficial($tipoPartido, $partidoId, $idEdicion, $idEquipo),
            );
        }, 3);
    }
}
