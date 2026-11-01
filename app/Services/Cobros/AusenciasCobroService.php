<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\PartidoService;
use DomainException;
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
    /** Goles con los que gana el equipo que se presentó cuando el rival no vino. */
    public const GOLES_NO_VINO = 3;

    public function __construct(private readonly PartidoService $partidoService) {}

    public function marcarNoVino(User $actor, int $equipoId, int $cargoId, ?string $motivo = null): AusenciaCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $equipoId, $cargoId, $motivo): AusenciaCobroPlanilla {
                [$actorVigente, $cargo] = $this->bloquearCargo($actor, $equipoId, $cargoId);

                if ($cargo->esDeudaSinPartido()) {
                    throw ValidationException::withMessages(['cargo' => ['Una deuda cargada a mano no tiene partido: no se puede marcar que el equipo no vino.']]);
                }
                if (AplicacionPagoPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo ya tiene pagos: si pagó, el equipo vino.']]);
                }
                if (ExencionCobroPlanilla::query()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo ya tiene una exención.']]);
                }
                if (AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $cargoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cargo' => ['El equipo ya figura como que no vino en este partido.']]);
                }
                $partido = $this->partidoRegular($cargo);
                if ($partido !== null && $this->partidoService->resultadoAplicado($partido)
                    && $this->resultadoAutomatico($partido) === null) {
                    throw ValidationException::withMessages(['cargo' => ['El partido ya tiene un resultado cargado: corregilo antes de marcar que el equipo no vino.']]);
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
                $this->sincronizarResultado($partido);

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
                $this->sincronizarResultado($this->partidoRegular($cargo));

                return $ausencia;
            });
        } catch (Throwable $excepcion) {
            $this->registrarFallo('No se pudo deshacer que el equipo no vino.', $actor, $equipoId, $cargoId, $excepcion);

            throw $excepcion;
        }
    }

    /** Solo los partidos regulares e interzonales reciben resultado automático; las finales siguen con su carga manual. */
    private function partidoRegular(CargoPlanilla $cargo): ?Partido
    {
        if ($cargo->parent_type !== Partido::class) {
            return null;
        }

        return Partido::query()->whereKey($cargo->parent_id)->lockForUpdate()->first();
    }

    /**
     * Equipo ganador si el resultado vigente es el 3 a 0 automático por "no vino"; si no, null.
     */
    private function resultadoAutomatico(Partido $partido): ?int
    {
        return $this->partidoService->resultadoAplicado($partido) ? $partido->ganador_por_no_vino : null;
    }

    /**
     * Deriva el resultado de las ausencias vigentes: con un solo ausente, el rival gana 3 a 0;
     * en otro caso se quita el resultado automático. Nunca pisa un resultado corregido a mano.
     */
    private function sincronizarResultado(?Partido $partido): void
    {
        if ($partido === null) {
            return;
        }

        try {
            $this->aplicarResultadoPorAusencias($partido);
        } catch (DomainException $excepcion) {
            throw ValidationException::withMessages(['cargo' => [$excepcion->getMessage()]]);
        }
    }

    private function aplicarResultadoPorAusencias(Partido $partido): void
    {

        $equiposPorCargo = CargoPlanilla::query()->where('parent_type', Partido::class)->where('parent_id', $partido->id)
            ->pluck('idEquipo', 'id');
        // Lectura con bloqueo: ve las ausencias confirmadas por otra marca concurrente del mismo partido.
        $ausentes = AusenciaCobroPlanilla::query()->vigentes()->whereIn('cargo_id', $equiposPorCargo->keys())
            ->lockForUpdate()->pluck('cargo_id')
            ->map(fn ($cargoId): int => (int) $equiposPorCargo[$cargoId])->unique()->values();
        $aplicado = $this->partidoService->resultadoAplicado($partido);
        if ($aplicado && $this->resultadoAutomatico($partido) === null) {
            return;
        }

        $ganador = match (true) {
            $ausentes->count() !== 1 => null,
            $ausentes->first() === (int) $partido->idEquipoLocal => (int) $partido->idEquipoVisitante,
            default => (int) $partido->idEquipoLocal,
        };

        if ($ganador === null) {
            if ($aplicado) {
                $this->partidoService->quitarResultado($partido);
                $this->recalcularGolesContra($partido);
            }

            return;
        }

        if ($aplicado && $this->resultadoAutomatico($partido) === $ganador) {
            return;
        }

        $ganaLocal = $ganador === (int) $partido->idEquipoLocal;
        $this->partidoService->actualizarResultado($partido,
            $ganaLocal ? self::GOLES_NO_VINO : 0, $ganaLocal ? 0 : self::GOLES_NO_VINO, $ganador);
        $this->recalcularGolesContra($partido);
    }

    private function recalcularGolesContra(Partido $partido): void
    {
        foreach ([$partido->idEquipoLocal, $partido->idEquipoVisitante] as $equipoId) {
            $this->partidoService->actualizarGolesContraEquipoEdicion($equipoId, $partido->idEdicion, $partido->idGrupo);
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
