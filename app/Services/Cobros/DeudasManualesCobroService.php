<?php

namespace App\Services\Cobros;

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\EquipoEdicion;
use App\Models\HistorialCobroPlanilla;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Deudas de planilla cargadas a mano, sin partido: el admin elige el equipo de
 * la edición, el importe, la fecha (por defecto, el día de carga) y un motivo
 * opcional. Se paga o se exime como cualquier cargo; "no vino" no aplica.
 */
class DeudasManualesCobroService
{
    private const ID_GLOBAL = 1;

    private const IMPORTE_MAXIMO = 2147483647;

    public function cargarDeuda(User $actor, int $edicionId, int $equipoId, mixed $importeArs, string $fecha, ?string $motivo): CargoPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $edicionId, $equipoId, $importeArs, $fecha, $motivo): CargoPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $this->exigirConfiguracionActiva();

                if (! is_int($importeArs) || $importeArs < 1 || $importeArs > self::IMPORTE_MAXIMO) {
                    throw ValidationException::withMessages(['importe_ars' => ['El importe debe ser un entero en ARS mayor a 0.']]);
                }
                $fecha = $this->fechaValida($fecha);

                $inscripcion = EquipoEdicion::query()->where('idEdicion', $edicionId)->where('idEquipo', $equipoId)
                    ->with(['equipo:id,nombre', 'categoria:id,nombreCategoria', 'edicion:id,nombre'])->first();
                if ($inscripcion === null || $inscripcion->equipo === null) {
                    throw ValidationException::withMessages(['idEquipo' => ['El equipo no está inscripto en esta edición.']]);
                }
                if ($inscripcion->categoria === null) {
                    throw ValidationException::withMessages(['idEquipo' => ['El equipo no tiene categoría en esta edición.']]);
                }

                if (PoliticaExencionCobroPlanilla::query()->where('idEquipo', $equipoId)->where('idEdicion', $edicionId)->exists()) {
                    throw ValidationException::withMessages(['idEquipo' => ['El equipo está exento de planillas en esta edición.']]);
                }

                $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);
                $cargo = CargoPlanilla::query()->create([
                    'tipo' => CargoPlanilla::TIPO_DEUDA_MANUAL,
                    'parent_type' => null,
                    'parent_id' => null,
                    'idEquipo' => $equipoId,
                    'idEdicion' => $edicionId,
                    'idCategoria' => (int) $inscripcion->idCategoria,
                    'precio_original' => $importeArs,
                    'snapshot' => [
                        'origen' => 'manual',
                        'motivo' => $motivo,
                        'fecha_deuda' => $fecha,
                        'equipo' => ['id' => $equipoId, 'nombre' => $inscripcion->equipo->nombre],
                        'edicion' => ['id' => $edicionId, 'nombre' => $inscripcion->edicion?->nombre],
                        'categoria' => ['id' => (int) $inscripcion->idCategoria, 'nombre' => $inscripcion->categoria->nombreCategoria],
                    ],
                ]);
                if (! $cargo->exists) {
                    throw new RuntimeException('No se pudo registrar la deuda manual.');
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'cargar_deuda_manual', 'usuario_id' => (int) $actorVigente->id, 'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['idEquipo' => $equipoId, 'idEdicion' => $edicionId],
                    'valores_despues' => [
                        'cargo_id' => (int) $cargo->id, 'tipo' => CargoPlanilla::TIPO_DEUDA_MANUAL,
                        'importe_ars' => $importeArs, 'fecha' => $fecha, 'motivo' => $motivo,
                    ],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial de la deuda manual.');
                }

                return $cargo;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo cargar la deuda manual de planilla.', [
                'edicion_id' => $edicionId, 'equipo_id' => $equipoId,
                'actor_id' => $actor->exists ? $actor->getKey() : null, 'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    /** La fecha llega como Y-m-d y no puede ser posterior a hoy. */
    private function fechaValida(string $fecha): string
    {
        $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) ? Carbon::createFromFormat('!Y-m-d', $fecha) : false;
        if ($dia === false || $dia->format('Y-m-d') !== $fecha) {
            throw ValidationException::withMessages(['fecha' => ['La fecha no es válida.']]);
        }
        if ($dia->greaterThan(today())) {
            throw ValidationException::withMessages(['fecha' => ['La fecha no puede ser posterior a hoy.']]);
        }

        return $fecha;
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede cargar deudas de planillas.';

        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    private function exigirConfiguracionActiva(): void
    {
        $configuracion = ConfiguracionCobroPlanilla::query()->whereKey(self::ID_GLOBAL)->lockForUpdate()->first();
        if ($configuracion === null) {
            throw new RuntimeException('La configuración global de cobros no está inicializada (falta la fila id 1).');
        }

        if ($configuracion->activado_at === null) {
            throw ValidationException::withMessages(['cobros' => ['Los cobros de planillas no están activados.']]);
        }
    }
}
