<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PlanillaJugadorAutosaveService
{
    public function __construct(
        private readonly PlanillaConfirmacionService $planillaConfirmacionService,
        private readonly EstadisticasJugadorService $estadisticasJugadorService,
        private readonly ValidadorNumeroCamiseta $validadorNumeroCamiseta,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $jugadores
     * @return array{status: string, message: string, data: array<string, mixed>, huboCambiosReales: bool, confirmacionInvalidada: bool, resultados: array<string, array<string, mixed>>}
     */
    public function actualizarJugadores(string $tipoPartido, int $partidoId, int $idEdicion, array $jugadores): array
    {
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        $resultados = [];
        $huboCambiosReales = false;
        $confirmacionInvalidada = false;
        $estadoAgregado = 'ok';

        foreach ($jugadores as $dniJugador => $datos) {
            $resultado = $this->actualizarFila(
                $tipoPartido,
                $partidoType,
                $partidoId,
                $idEdicion,
                (string) $dniJugador,
                $datos,
            );

            $resultados[(string) $dniJugador] = $resultado;
            $huboCambiosReales = $huboCambiosReales || (bool) ($resultado['huboCambiosReales'] ?? false);
            $confirmacionInvalidada = $confirmacionInvalidada || (bool) ($resultado['confirmacionInvalidada'] ?? false);

            if (($resultado['status'] ?? 'ok') === 'error') {
                $estadoAgregado = 'error';
            } elseif (($resultado['status'] ?? 'ok') === 'validation' && $estadoAgregado !== 'error') {
                $estadoAgregado = 'validation';
            } elseif (($resultado['status'] ?? 'ok') === 'conflict' && $estadoAgregado === 'ok') {
                $estadoAgregado = 'conflict';
            }
        }

        $resultadoPrincipal = $resultados !== [] ? array_values($resultados)[0] : [
            'status' => 'ok',
            'message' => 'Jugador actualizado correctamente.',
            'data' => [],
            'huboCambiosReales' => false,
            'confirmacionInvalidada' => false,
        ];

        if ($estadoAgregado === 'error') {
            $resultadoPrincipal['status'] = 'error';
        } elseif ($estadoAgregado === 'validation') {
            $resultadoPrincipal['status'] = 'validation';
        } elseif ($estadoAgregado === 'conflict') {
            $resultadoPrincipal['status'] = 'conflict';
        }

        $resultadoPrincipal['huboCambiosReales'] = $huboCambiosReales;
        $resultadoPrincipal['confirmacionInvalidada'] = $confirmacionInvalidada;
        $resultadoPrincipal['resultados'] = $resultados;

        return $resultadoPrincipal;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{status: string, message: string, data: array<string, mixed>, huboCambiosReales: bool, confirmacionInvalidada: bool}
     */
    private function actualizarFila(
        string $tipoPartido,
        string $partidoType,
        int $partidoId,
        int $idEdicion,
        string $dniJugador,
        array $datos,
    ): array {
        try {
            return DB::transaction(function () use ($tipoPartido, $partidoType, $partidoId, $idEdicion, $dniJugador, $datos): array {
                $planilla = PlanillaJugador::query()
                    ->where('partido_id', $partidoId)
                    ->where('partido_type', $partidoType)
                    ->where('dni_jugador', $dniJugador)
                    ->lockForUpdate()
                    ->first();

                $jugador = Jugador::query()
                    ->where('dni', $dniJugador)
                    ->lockForUpdate()
                    ->first();

                if ($planilla === null || $jugador === null) {
                    return [
                        'status' => 'error',
                        'message' => 'No se encontró la fila del jugador en la planilla.',
                        'data' => ['dni' => $dniJugador],
                        'huboCambiosReales' => false,
                        'confirmacionInvalidada' => false,
                    ];
                }

                $updatedAtEnviado = $datos['updated_at'] ?? null;
                if ($updatedAtEnviado !== null && $updatedAtEnviado !== '' && $this->hayConflictoPorVersion($planilla, (string) $updatedAtEnviado)) {
                    return [
                        'status' => 'conflict',
                        'message' => 'La fila fue actualizada por otro operador.',
                        'data' => [
                            'dni' => $dniJugador,
                            'current' => $this->formatearPlanillaActual($planilla),
                        ],
                        'huboCambiosReales' => false,
                        'confirmacionInvalidada' => false,
                    ];
                }

                $golesNuevos = max(0, (int) ($datos['goles'] ?? $planilla->goles));
                $asistenciaNueva = $this->normalizarCheckbox($datos['asistencia'] ?? null);
                $numeroCamisetaNuevo = array_key_exists('numero_camiseta', $datos)
                    ? (int) $datos['numero_camiseta']
                    : (int) $planilla->numero_camiseta;
                $tarjetaRojaNueva = $this->normalizarCheckbox($datos['tarjeta_roja'] ?? null);

                // El número solo se controla entre jugadores con asistencia
                // marcada. Se valida al cambiar el número o al marcar la
                // asistencia; editar goles o tarjeta de una fila que ya venía
                // duplicada no se bloquea.
                $numeroCamisetaCambio = $numeroCamisetaNuevo !== (int) $planilla->numero_camiseta;
                $asistenciaCambio = $asistenciaNueva !== (bool) $planilla->asistio;

                if ($asistenciaNueva
                    && ($numeroCamisetaCambio || $asistenciaCambio)
                    && $this->validadorNumeroCamiseta->numeroOcupado(
                        $partidoType,
                        $partidoId,
                        (int) $planilla->idEquipo,
                        $numeroCamisetaNuevo,
                        (int) $planilla->id,
                    )
                ) {
                    return [
                        'status' => 'validation',
                        'message' => 'El número de camiseta '.$numeroCamisetaNuevo.' ya está en uso por otro jugador con asistencia marcada en este equipo para este partido.',
                        'data' => [
                            'dni' => $dniJugador,
                            'current' => $this->formatearPlanillaActual($planilla),
                        ],
                        'huboCambiosReales' => false,
                        'confirmacionInvalidada' => false,
                    ];
                }

                if (! $this->planillaTieneCambiosReales($planilla, [
                    'goles' => $golesNuevos,
                    'asistencia' => $asistenciaNueva,
                    'numero_camiseta' => $numeroCamisetaNuevo,
                    'tarjeta_roja' => $tarjetaRojaNueva,
                ])) {
                    return [
                        'status' => 'ok',
                        'message' => 'No hubo cambios en la fila.',
                        'data' => [
                            'dni' => $dniJugador,
                            'updated_at' => $planilla->updated_at?->toIso8601String(),
                        ],
                        'huboCambiosReales' => false,
                        'confirmacionInvalidada' => false,
                    ];
                }

                $huboCambiosReales = true;

                $golesAnteriores = (int) $planilla->goles;
                $asistioAnteriormente = (bool) $planilla->asistio;
                $teniaRojaAntes = (bool) $planilla->tarjeta_roja;

                $planilla->goles = $golesNuevos;
                $planilla->asistio = $asistenciaNueva;
                $planilla->numero_camiseta = $numeroCamisetaNuevo;
                $planilla->tarjeta_roja = $tarjetaRojaNueva;
                $planilla->save();

                if (! $teniaRojaAntes && $planilla->tarjeta_roja) {
                    $jugador->tarjetas_rojas_totales = ($jugador->tarjetas_rojas_totales ?? 0) + 1;
                }

                $deltaGoles = $planilla->goles - $golesAnteriores;

                if ($planilla->asistio && ! $asistioAnteriormente) {
                    $jugador->partidos_totales = ($jugador->partidos_totales ?? 0) + 1;
                } elseif (! $planilla->asistio && $asistioAnteriormente) {
                    $jugador->partidos_totales = max(0, ($jugador->partidos_totales ?? 0) - 1);
                }

                $jugador->save();

                $this->estadisticasJugadorService->actualizarDeltaGoles(
                    $dniJugador,
                    $idEdicion,
                    (int) $planilla->idCategoria,
                    (int) $planilla->idEquipo,
                    $deltaGoles,
                );

                $confirmacionInvalidada = $this->planillaConfirmacionService->invalidarConfirmacionPorEdicion(
                    $tipoPartido,
                    $partidoId,
                    $idEdicion,
                );

                return [
                    'status' => 'ok',
                    'message' => 'Jugador actualizado correctamente.',
                    'data' => [
                        'dni' => $dniJugador,
                        'numero_camiseta' => $planilla->numero_camiseta,
                        'goles' => $planilla->goles,
                        'asistencia' => (bool) $planilla->asistio,
                        'tarjeta_roja' => (bool) $planilla->tarjeta_roja,
                        'updated_at' => $planilla->fresh()?->updated_at?->toIso8601String(),
                        'confirmacion_invalidada' => $confirmacionInvalidada,
                    ],
                    'huboCambiosReales' => true,
                    'confirmacionInvalidada' => $confirmacionInvalidada,
                ];
            });
        } catch (Throwable $throwable) {
            Log::error('Error actualizando planilla por autosave.', [
                'dni_jugador' => $dniJugador,
                'partido_id' => $partidoId,
                'idEdicion' => $idEdicion,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'No se pudo guardar la fila.',
                'data' => ['dni' => $dniJugador],
                'huboCambiosReales' => false,
                'confirmacionInvalidada' => false,
            ];
        }
    }

    private function planillaTieneCambiosReales(PlanillaJugador $planilla, array $datosActualizados): bool
    {
        return (int) $planilla->goles !== (int) ($datosActualizados['goles'] ?? $planilla->goles)
            || (bool) $planilla->asistio !== (bool) ($datosActualizados['asistencia'] ?? $planilla->asistio)
            || (int) $planilla->numero_camiseta !== (int) ($datosActualizados['numero_camiseta'] ?? $planilla->numero_camiseta)
            || (bool) $planilla->tarjeta_roja !== (bool) ($datosActualizados['tarjeta_roja'] ?? $planilla->tarjeta_roja);
    }

    private function hayConflictoPorVersion(PlanillaJugador $planilla, string $updatedAtEnviado): bool
    {
        $updatedAtCliente = Carbon::parse($updatedAtEnviado);

        return $planilla->updated_at === null || $planilla->updated_at->getTimestamp() !== $updatedAtCliente->getTimestamp();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearPlanillaActual(PlanillaJugador $planilla): array
    {
        return [
            'numero_camiseta' => (int) $planilla->numero_camiseta,
            'goles' => (int) $planilla->goles,
            'asistencia' => (bool) $planilla->asistio,
            'tarjeta_roja' => (bool) $planilla->tarjeta_roja,
            'updated_at' => $planilla->updated_at?->toIso8601String(),
        ];
    }

    private function normalizarCheckbox(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_int($valor)) {
            return $valor === 1;
        }

        if (is_string($valor)) {
            $valorNormalizado = strtolower(trim($valor));

            return in_array($valorNormalizado, ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
