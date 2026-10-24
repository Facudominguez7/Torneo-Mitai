<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PoliticaExencionCobroPlanilla;
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
                $ausente = AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $cargoId)->lockForUpdate()->exists();
                $pendiente = (new DistribucionCreditoService)->calcularDeuda([
                    'precio_original' => (int) $cargo->precio_original, 'aplicado_ars' => $aplicado,
                    'eximido_ars' => $previa === null ? 0 : $previa->importe_ars, 'ausente' => $ausente,
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

    /** Registra autorización para cargos futuros, sin perdonar deuda existente. */
    public function registrarPolitica(User $actor, int $equipoId, int $edicionId, ?string $motivo = null): PoliticaExencionCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $equipoId, $edicionId, $motivo): PoliticaExencionCobroPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $this->exigirConfiguracionActiva();
                if ($equipoId < 1 || $edicionId < 1 || $equipoId > 2147483647 || $edicionId > 2147483647
                    || ! Equipo::query()->whereKey($equipoId)->exists()
                    || ! Edicion::query()->whereKey($edicionId)->exists()
                    || ! EquipoEdicion::query()->where('idEquipo', $equipoId)->where('idEdicion', $edicionId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['politica' => ['La política requiere un equipo inscrito en una edición existente.']]);
                }

                if (PoliticaExencionCobroPlanilla::query()->where('idEquipo', $equipoId)->where('idEdicion', $edicionId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['politica' => ['El equipo ya tiene una política de exención en esta edición.']]);
                }

                $politica = new PoliticaExencionCobroPlanilla([
                    'idEquipo' => $equipoId, 'idEdicion' => $edicionId,
                    'usuario_id' => (int) $actorVigente->id, 'motivo' => $motivo,
                ]);
                if (! $politica->save() || ! $politica->exists) {
                    throw new RuntimeException('No se pudo registrar la política de exención.');
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'registrar_politica_exencion', 'usuario_id' => (int) $actorVigente->id,
                    'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['idEquipo' => $equipoId, 'idEdicion' => $edicionId],
                    'valores_despues' => ['politica_id' => (int) $politica->id, 'motivo' => $motivo],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial de la política.');
                }

                return $politica;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo registrar la política de exención de planillas.', [
                'equipo_id' => $equipoId, 'edicion_id' => $edicionId,
                'actor_id' => $actor->exists ? $actor->getKey() : null, 'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    /**
     * Deja al equipo exento en toda la edición: registra la política para cargos futuros
     * (si no existía) y exime el saldo pendiente de sus cargos actuales. Los pagos se conservan.
     *
     * @return array{politica_id: int, politica_nueva: bool, cargos_eximidos: int, eximido_ars: int}
     */
    public function eximirEdicion(User $actor, int $equipoId, int $edicionId, ?string $motivo = null): array
    {
        return DB::transaction(function () use ($actor, $equipoId, $edicionId, $motivo): array {
            $this->resolverActorAdmin($actor);
            // La configuración global es el mutex común de cobros: se bloquea antes que la política
            // para que dos exenciones simultáneas se serialicen en lugar de interbloquearse.
            $this->exigirConfiguracionActiva();
            $politica = PoliticaExencionCobroPlanilla::query()->where('idEquipo', $equipoId)->where('idEdicion', $edicionId)->lockForUpdate()->first();
            $politicaNueva = $politica === null;
            $politica ??= $this->registrarPolitica($actor, $equipoId, $edicionId, $motivo);

            $pendientes = app(ConsultaCobrosService::class)->consultar([
                'idEdicion' => $edicionId, 'idEquipo' => $equipoId, 'estado' => 'deudores', 'periodo' => 'todos',
            ])['cargos'];
            if (! $politicaNueva && $pendientes->isEmpty()) {
                throw ValidationException::withMessages(['politica' => ['El equipo ya está exento en esta edición y no tiene deuda pendiente.']]);
            }

            $eximido = 0;
            foreach ($pendientes as $cargo) {
                $eximido += $this->eximirCargo($actor, $equipoId, (int) $cargo['id'], $motivo)->importe_ars;
            }

            return [
                'politica_id' => (int) $politica->id, 'politica_nueva' => $politicaNueva,
                'cargos_eximidos' => $pendientes->count(), 'eximido_ars' => $eximido,
            ];
        });
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
