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
 *
 * La clave de idempotencia evita que un doble envío del formulario cree dos
 * deudas: se guarda en el historial y se busca bajo el lock de configuración.
 */
class DeudasManualesCobroService
{
    private const ID_GLOBAL = 1;

    private const IMPORTE_MAXIMO = 2147483647;

    private const ACCION = 'cargar_deuda_manual';

    public function cargarDeuda(
        User $actor,
        int $edicionId,
        int $equipoId,
        mixed $importeArs,
        string $fecha,
        ?string $motivo,
        ?string $claveIdempotencia = null,
    ): CargoPlanilla {
        try {
            return DB::transaction(function () use ($actor, $edicionId, $equipoId, $importeArs, $fecha, $motivo, $claveIdempotencia): CargoPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $this->exigirConfiguracionActiva();

                $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);
                $clave = $claveIdempotencia === null ? null : strtolower(trim($claveIdempotencia));
                if ($clave !== null) {
                    $previo = $this->resolverReplay($clave, $actorVigente, [
                        'idEquipo' => $equipoId, 'idEdicion' => $edicionId, 'importe_ars' => $importeArs, 'fecha' => $fecha, 'motivo' => $motivo,
                    ]);
                    if ($previo !== null) {
                        return $previo;
                    }
                }

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
                    'accion' => self::ACCION, 'usuario_id' => (int) $actorVigente->id, 'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['idEquipo' => $equipoId, 'idEdicion' => $edicionId],
                    'valores_despues' => [
                        'cargo_id' => (int) $cargo->id, 'tipo' => CargoPlanilla::TIPO_DEUDA_MANUAL,
                        'importe_ars' => $importeArs, 'fecha' => $fecha, 'motivo' => $motivo,
                        'clave_idempotencia' => $clave,
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

    /**
     * Un reenvío con la misma clave devuelve la deuda ya cargada; con otros
     * datos u otro usuario, la clave se rechaza sin crear nada.
     *
     * @param  array{idEquipo: int, idEdicion: int, importe_ars: mixed, fecha: string, motivo: ?string}  $datos
     */
    private function resolverReplay(string $clave, User $actorVigente, array $datos): ?CargoPlanilla
    {
        $previo = HistorialCobroPlanilla::query()
            ->where('accion', self::ACCION)
            ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(valores_despues, '$.\"clave_idempotencia\"'))) = ?", [$clave])
            ->lockForUpdate()
            ->first();
        if ($previo === null) {
            return null;
        }

        $huella = $previo->valores_despues;
        $valoresAntes = $previo->valores_antes;
        $coincide = is_array($huella) && is_array($valoresAntes)
            && (int) $previo->usuario_id === (int) $actorVigente->getKey()
            && ($valoresAntes['idEquipo'] ?? null) === $datos['idEquipo']
            && ($valoresAntes['idEdicion'] ?? null) === $datos['idEdicion']
            && ($huella['importe_ars'] ?? null) === $datos['importe_ars']
            && ($huella['fecha'] ?? null) === $datos['fecha']
            && ($huella['motivo'] ?? null) === $datos['motivo'];
        $cargo = $coincide && is_int($huella['cargo_id'] ?? null) ? CargoPlanilla::query()->find($huella['cargo_id']) : null;
        if ($cargo === null) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['Esta carga ya se usó con otros datos. Recargá la página y volvé a cargar la deuda.']]);
        }

        return $cargo;
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
