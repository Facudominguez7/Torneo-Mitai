<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Exime deuda pendiente de un único equipo sin modificar ingresos ni precios históricos. */
class ExencionesCobroService
{
    public function eximirCargo(User $actor, int $equipoId, int $cargoId, ?string $motivo = null): ExencionCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $equipoId, $cargoId, $motivo): ExencionCobroPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $this->exigirConfiguracionActiva();
                if ($equipoId < 1 || $cargoId < 1) {
                    throw ValidationException::withMessages(['cargo' => ['El equipo y el cargo requieren identificadores positivos.']]);
                }

                $inicial = CargoPlanilla::query()->find($cargoId);
                if ($inicial === null || (int) $inicial->idEquipo !== $equipoId) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo no existe o no pertenece al equipo indicado.']]);
                }

                $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->firstOrCreate([
                    'idEquipo' => $equipoId, 'idEdicion' => (int) $inicial->idEdicion,
                ]);
                $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->find((int) $cuenta->id);
                $cargo = CargoPlanilla::query()->lockForUpdate()->find($cargoId);
                if ($cuenta === null || $cargo === null || (int) $cargo->idEquipo !== $equipoId
                    || (int) $cargo->idEdicion !== (int) $inicial->idEdicion
                    || (int) $cuenta->idEquipo !== $equipoId || (int) $cuenta->idEdicion !== (int) $cargo->idEdicion) {
                    throw new RuntimeException('No se pudo bloquear la identidad del cargo y la cuenta del equipo.');
                }

                $aplicado = (int) AplicacionPagoPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->sum('importe_ars');
                $previa = ExencionCobroPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->first();
                $pendiente = (new DistribucionCreditoService)->calcularDeuda([
                    'precio_original' => (int) $cargo->precio_original, 'aplicado_ars' => $aplicado,
                    'eximido_ars' => $previa === null ? 0 : $previa->importe_ars,
                ], $cargoId);
                if ($previa !== null || $pendiente === 0) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo ya tiene una exención o no registra deuda pendiente.']]);
                }

                $exencion = new ExencionCobroPlanilla([
                    'cargo_id' => $cargoId, 'usuario_id' => (int) $actorVigente->id,
                    'importe_ars' => $pendiente, 'motivo' => $motivo,
                ]);
                if (! $exencion->save() || ! $exencion->exists) {
                    throw new RuntimeException('No se pudo registrar la exención del cargo.');
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'eximir_cargo', 'usuario_id' => (int) $actorVigente->id, 'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['idEquipo' => $equipoId, 'idEdicion' => (int) $cargo->idEdicion,
                        'cargo_id' => $cargoId, 'pendiente_ars' => $pendiente],
                    'valores_despues' => ['exencion_id' => (int) $exencion->id, 'importe_ars' => $pendiente, 'pendiente_ars' => 0],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial de la exención.');
                }

                return $exencion;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo eximir el cargo de planilla.', [
                'equipo_id' => $equipoId, 'cargo_id' => $cargoId,
                'actor_id' => $actor->exists ? $actor->getKey() : null, 'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede eximir cargos de planillas.';
        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $vigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($vigente === null || ! $vigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $vigente;
    }

    private function exigirConfiguracionActiva(): void
    {
        $configuracion = ConfiguracionCobroPlanilla::query()->whereKey(1)->lockForUpdate()->first();
        if ($configuracion === null || $configuracion->activado_at === null) {
            throw new RuntimeException('La configuración global de cobros debe existir y estar activa.');
        }
    }
}
