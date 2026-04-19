<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\PlanillaJugador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CopiarPlanillaPartidoAnteriorService
{
    public function __construct(
        private readonly PlanillaConfirmacionService $planillaConfirmacionService,
        private readonly PlanillaPartidoAnteriorResolver $planillaPartidoAnteriorResolver,
        private readonly GolesArbitrosService $golesArbitrosService,
    ) {}

    /**
     * @return array{status: string, message: string, data: array<string, mixed>, huboCambiosReales: bool, confirmacionInvalidada: bool}
     */
    public function copiarPlanillaPartidoAnterior(
        string $tipoPartido,
        int $partidoId,
        int $idEdicion,
        string $scope,
        ?string $updatedAt = null,
    ): array {
        try {
            $partidoActual = $this->planillaConfirmacionService->resolverPartido($tipoPartido, $partidoId, $idEdicion);
            $equipoId = $this->resolverEquipoPorScope($partidoActual, $scope);
            $idCategoria = (int) $partidoActual->idCategoria;

            $resolucion = $this->planillaPartidoAnteriorResolver->resolverMismoDiaConAsistencia(
                $tipoPartido,
                $partidoId,
                $idEdicion,
                $idCategoria,
                $equipoId,
            );

            if ($resolucion['status'] !== 'ok' || ! isset($resolucion['partido'])) {
                return [
                    'status' => $resolucion['status'],
                    'message' => $resolucion['message'],
                    'data' => [
                        'scope' => $scope,
                        'partido_anterior_id' => null,
                        'resumen' => [
                            'copiados' => 0,
                            'omitidos' => 0,
                            'conflictos' => 0,
                        ],
                        'filas_actualizadas' => [],
                        'omitidos' => [],
                        'conflictos' => [],
                        'confirmacion_invalidada' => false,
                    ],
                    'huboCambiosReales' => false,
                    'confirmacionInvalidada' => false,
                ];
            }

            $partidoAnterior = $resolucion['partido'];

            $resultado = DB::transaction(function () use ($partidoActual, $partidoAnterior, $equipoId, $idEdicion, $tipoPartido, $scope): array {
                $partidoType = PartidoConstants::getModelClass($tipoPartido);

                $planillasDestino = PlanillaJugador::query()
                    ->where('partido_id', $partidoActual->id)
                    ->where('partido_type', $partidoType)
                    ->where('idEdicion', $idEdicion)
                    ->where('idEquipo', $equipoId)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('dni_jugador');

                $planillasOrigen = PlanillaJugador::query()
                    ->where('partido_id', $partidoAnterior->id)
                    ->where('partido_type', $partidoType)
                    ->where('idEdicion', $idEdicion)
                    ->where('idEquipo', $equipoId)
                    ->get();

                $numerosOcupados = $planillasDestino
                    ->pluck('numero_camiseta')
                    ->filter(static fn ($numero): bool => $numero !== null && (int) $numero > 0)
                    ->map(static fn ($numero): int => (int) $numero)
                    ->values()
                    ->all();

                $resumen = [
                    'copiados' => 0,
                    'omitidos' => 0,
                    'conflictos' => 0,
                ];

                $filasActualizadas = [];
                $omitidos = [];
                $conflictos = [];

                foreach ($planillasOrigen as $planillaOrigen) {
                    $planillaDestino = $planillasDestino->get($planillaOrigen->dni_jugador);

                    if ($planillaDestino === null) {
                        $resumen['omitidos']++;
                        $omitidos[] = [
                            'dni' => (string) $planillaOrigen->dni_jugador,
                            'motivo' => 'dni_no_presente',
                        ];

                        continue;
                    }

                    $numeroCamisetaOrigen = (int) $planillaOrigen->numero_camiseta;
                    if ($numeroCamisetaOrigen <= 0) {
                        $resumen['conflictos']++;
                        $conflictos[] = [
                            'dni' => (string) $planillaOrigen->dni_jugador,
                            'motivo' => 'camiseta_invalida',
                        ];

                        continue;
                    }

                    $numeroActual = (int) $planillaDestino->numero_camiseta;
                    $ocupaOtroJugador = in_array($numeroCamisetaOrigen, $numerosOcupados, true) && $numeroCamisetaOrigen !== $numeroActual;

                    if ($ocupaOtroJugador) {
                        $resumen['conflictos']++;
                        $conflictos[] = [
                            'dni' => (string) $planillaOrigen->dni_jugador,
                            'motivo' => 'camiseta_duplicada',
                        ];

                        continue;
                    }

                    $asistenciaNueva = (bool) $planillaOrigen->asistio;
                    $hayCambiosReales = (bool) ($numeroActual !== $numeroCamisetaOrigen || (bool) $planillaDestino->asistio !== $asistenciaNueva);

                    $planillaDestino->forceFill([
                        'asistio' => $asistenciaNueva,
                        'numero_camiseta' => $numeroCamisetaOrigen,
                    ]);

                    if ($hayCambiosReales) {
                        $planillaDestino->save();
                    } else {
                        $planillaDestino->touch();
                    }

                    $planillaDestino->refresh();

                    $numerosOcupados = array_values(array_filter(
                        $numerosOcupados,
                        static fn (int $numero): bool => $numero !== $numeroActual,
                    ));
                    $numerosOcupados[] = $numeroCamisetaOrigen;

                    $resumen['copiados']++;
                    $filasActualizadas[] = [
                        'dni' => (string) $planillaOrigen->dni_jugador,
                        'asistencia' => (bool) $planillaDestino->asistio,
                        'numero_camiseta' => (int) $planillaDestino->numero_camiseta,
                        'updated_at' => $planillaDestino->updated_at?->toIso8601String(),
                    ];
                }

                $confirmacionInvalidada = $resumen['copiados'] > 0
                    ? $this->planillaConfirmacionService->invalidarConfirmacionPorEdicion($tipoPartido, $partidoActual->id, $idEdicion)
                    : false;

                if ($resumen['copiados'] > 0) {
                    $this->golesArbitrosService->sincronizarVinculaciones($tipoPartido, $partidoActual->id, $idEdicion);
                }

                return [
                    'status' => 'ok',
                    'message' => 'Se copiaron datos del partido anterior.',
                    'data' => [
                        'scope' => $scope,
                        'partido_anterior_id' => (int) $partidoAnterior->id,
                        'resumen' => $resumen,
                        'filas_actualizadas' => $filasActualizadas,
                        'omitidos' => $omitidos,
                        'conflictos' => $conflictos,
                        'confirmacion_invalidada' => $confirmacionInvalidada,
                    ],
                    'huboCambiosReales' => $resumen['copiados'] > 0,
                    'confirmacionInvalidada' => $confirmacionInvalidada,
                ];
            });

            return $resultado;
        } catch (Throwable $throwable) {
            Log::error('Error copiando planilla del partido anterior.', [
                'partido_id' => $partidoId,
                'idEdicion' => $idEdicion,
                'scope' => $scope,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'No se pudo copiar la planilla.',
                'data' => [
                    'scope' => $scope,
                    'partido_anterior_id' => null,
                    'resumen' => [
                        'copiados' => 0,
                        'omitidos' => 0,
                        'conflictos' => 0,
                    ],
                    'filas_actualizadas' => [],
                    'omitidos' => [],
                    'conflictos' => [],
                    'confirmacion_invalidada' => false,
                ],
                'huboCambiosReales' => false,
                'confirmacionInvalidada' => false,
            ];
        }
    }

    /**
     * @return array{local: bool, visitante: bool}
     */
    public function obtenerDisponibilidadCopiadoPorEquipo(string $tipoPartido, int $partidoId, int $idEdicion): array
    {
        $partidoActual = $this->planillaConfirmacionService->resolverPartido($tipoPartido, $partidoId, $idEdicion);
        $idCategoria = (int) $partidoActual->idCategoria;

        $local = $this->planillaPartidoAnteriorResolver->resolverMismoDiaConAsistencia(
            $tipoPartido,
            $partidoId,
            $idEdicion,
            $idCategoria,
            (int) $partidoActual->idEquipoLocal,
        );

        $visitante = $this->planillaPartidoAnteriorResolver->resolverMismoDiaConAsistencia(
            $tipoPartido,
            $partidoId,
            $idEdicion,
            $idCategoria,
            (int) $partidoActual->idEquipoVisitante,
        );

        return [
            'local' => $local['status'] === 'ok',
            'visitante' => $visitante['status'] === 'ok',
        ];
    }

    private function resolverEquipoPorScope(object $partido, string $scope): int
    {
        return $scope === 'visitante'
            ? (int) $partido->idEquipoVisitante
            : (int) $partido->idEquipoLocal;
    }
}
