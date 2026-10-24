<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
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

/**
 * "No vino": el equipo no pagó la planilla de ese partido y no queda debiendo.
 * A diferencia de una exención no registra importe eximido y se puede deshacer.
 * Bloquea en el mismo orden que las exenciones: actor, configuración, cuenta y cargo.
 */
class AusenciasCobroService
{
    public function marcarNoVino(User $actor, int $equipoId, int $cargoId, ?string $motivo = null): AusenciaCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $equipoId, $cargoId, $motivo): AusenciaCobroPlanilla {
                [$actorVigente, $cargo] = $this->bloquearCargo($actor, $equipoId, $cargoId);

                if (AplicacionPagoPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo ya tiene pagos: si pagó, el equipo vino.']]);
                }
                if (ExencionCobroPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo ya tiene una exención.']]);
                }
                if (AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El equipo ya figura como que no vino en este partido.']]);
                }

                $ausencia = new AusenciaCobroPlanilla([
                    'cargo_id' => $cargoId, 'usuario_id' => (int) $actorVigente->id, 'motivo' => $motivo,
                ]);
                if (! $ausencia->save() || ! $ausencia->exists) {
                    throw new RuntimeException('No se pudo registrar que el equipo no vino.');
                }

                $this->registrarEvento($actorVigente, 'marcar_no_vino', [
                    'idEquipo' => $equipoId, 'idEdicion' => (int) $cargo->idEdicion,
                    'cargo_id' => $cargoId, 'pendiente_ars' => (int) $cargo->precio_original,
                ], ['ausencia_id' => (int) $ausencia->id, 'motivo' => $motivo, 'pendiente_ars' => 0]);

                return $ausencia;
            });
        } catch (Throwable $excepcion) {
            $this->registrarFallo('No se pudo marcar que el equipo no vino.', $actor, $equipoId, $cargoId, $excepcion);

            throw $excepcion;
        }
    }

    /** Devuelve la deuda del cargo: la marca queda en la historia como revertida. */
    public function deshacerNoVino(User $actor, int $equipoId, int $cargoId): AusenciaCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $equipoId, $cargoId): AusenciaCobroPlanilla {
                [$actorVigente, $cargo] = $this->bloquearCargo($actor, $equipoId, $cargoId);

                $ausencia = AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $cargoId)->lockForUpdate()->first();
                if ($ausencia === null) {
                    throw ValidationException::withMessages(['cargo' => ['El equipo no figura como que no vino en este partido.']]);
                }

                $ausencia->forceFill([
                    'revertido_at' => now(), 'revertido_por' => (int) $actorVigente->id, 'revertido_por_nombre' => $actorVigente->name,
                ]);
                if (! $ausencia->save()) {
                    throw new RuntimeException('No se pudo deshacer que el equipo no vino.');
                }

                $this->registrarEvento($actorVigente, 'deshacer_no_vino', [
                    'idEquipo' => $equipoId, 'idEdicion' => (int) $cargo->idEdicion,
                    'cargo_id' => $cargoId, 'ausencia_id' => (int) $ausencia->id, 'pendiente_ars' => 0,
                ], ['pendiente_ars' => (int) $cargo->precio_original]);

                return $ausencia;
            });
        } catch (Throwable $excepcion) {
            $this->registrarFallo('No se pudo deshacer que el equipo no vino.', $actor, $equipoId, $cargoId, $excepcion);

            throw $excepcion;
        }
    }

    /** @return array{0: User, 1: CargoPlanilla} */
    private function bloquearCargo(User $actor, int $equipoId, int $cargoId): array
    {
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
            || (int) $cargo->idEdicion !== (int) $inicial->idEdicion || (int) $cuenta->idEdicion !== (int) $cargo->idEdicion) {
            throw new RuntimeException('No se pudo bloquear la identidad del cargo y la cuenta del equipo.');
        }

        return [$actorVigente, $cargo];
    }

    private function registrarEvento(User $actor, string $accion, array $antes, array $despues): void
    {
        $evento = new HistorialCobroPlanilla([
            'accion' => $accion, 'usuario_id' => (int) $actor->id, 'actor_nombre' => $actor->name,
            'valores_antes' => $antes, 'valores_despues' => $despues,
        ]);
        if (! $evento->save() || ! $evento->exists) {
            throw new RuntimeException('No se pudo registrar el historial de no vino.');
        }
    }

    private function registrarFallo(string $mensaje, User $actor, int $equipoId, int $cargoId, Throwable $excepcion): void
    {
        Log::error($mensaje, [
            'equipo_id' => $equipoId, 'cargo_id' => $cargoId,
            'actor_id' => $actor->exists ? $actor->getKey() : null, 'excepcion' => $excepcion::class,
        ]);
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede marcar que un equipo no vino.';
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
