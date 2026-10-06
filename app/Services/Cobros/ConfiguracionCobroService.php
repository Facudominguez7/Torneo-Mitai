<?php

namespace App\Services\Cobros;

use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Servicio del primer corte de cobros: configuración singleton global,
 * actualización de tarifa y activación one-shot con auditoría append-only.
 *
 * El actor será provisto por un controller futuro; hoy no existen rutas ni UI.
 */
class ConfiguracionCobroService
{
    private const ID_GLOBAL = 1;

    private const TARIFA_MINIMA = 1;

    private const TARIFA_MAXIMA = 2147483647;

    private const ERROR_FILA_AUSENTE = 'La configuración global de cobros no está inicializada (falta la fila id 1).';

    /**
     * Obtener la configuración global (solo lectura, sin mutar estado).
     */
    public function obtenerConfiguracion(): ConfiguracionCobroPlanilla
    {
        return $this->obtenerFilaGlobal();
    }

    /**
     * Actualizar la tarifa vigente. Nunca activa los cobros.
     * Acepta únicamente int estricto (no trunca flotantes ni convierte texto).
     */
    public function actualizarTarifa(User $actor, mixed $tarifa): ConfiguracionCobroPlanilla
    {
        return DB::transaction(function () use ($actor, $tarifa): ConfiguracionCobroPlanilla {
            $actorVigente = $this->resolverActorAdmin($actor);
            $this->validarTarifa($tarifa);
            $configuracion = $this->obtenerFilaGlobal(bloqueada: true);

            if ($configuracion->tarifa_ars === $tarifa) {
                return $configuracion; // No-op: precio igual, sin evento.
            }

            $tarifaAnterior = $configuracion->tarifa_ars;
            $configuracion->tarifa_ars = $tarifa;
            if (! $configuracion->save()) {
                throw new RuntimeException('No se pudo guardar la tarifa de planilla.');
            }

            $this->registrarAuditoria($actorVigente, 'actualizar_tarifa', ['tarifa_ars' => $tarifaAnterior], ['tarifa_ars' => $tarifa]);

            return $configuracion;
        });
    }

    /**
     * Activación manual one-shot e idempotente: el instante se toma dentro de
     * la transacción y una repetición no lo reescribe ni duplica auditoría.
     */
    public function activar(User $actor): ConfiguracionCobroPlanilla
    {
        return DB::transaction(function () use ($actor): ConfiguracionCobroPlanilla {
            $actorVigente = $this->resolverActorAdmin($actor);
            $configuracion = $this->obtenerFilaGlobal(bloqueada: true);

            if ($configuracion->activado_at !== null) {
                return $configuracion;
            }

            $instante = now();
            $configuracion->activado_at = $instante;
            if (! $configuracion->save()) {
                throw new RuntimeException('No se pudo activar el cobro de planillas.');
            }

            $this->registrarAuditoria($actorVigente, 'activar_cobros', ['activado_at' => null], ['activado_at' => $instante->format('Y-m-d H:i:s')]);

            return $configuracion;
        });
    }

    /**
     * Fila global del singleton; con lock pesimista para mutaciones.
     * Falla cerrado si no existe: sin recreación silenciosa.
     */
    private function obtenerFilaGlobal(bool $bloqueada = false): ConfiguracionCobroPlanilla
    {
        $consulta = ConfiguracionCobroPlanilla::query()->whereKey(self::ID_GLOBAL);

        if ($bloqueada) {
            $consulta->lockForUpdate();
        }

        $configuracion = $consulta->first();

        if ($configuracion === null) {
            throw new RuntimeException(self::ERROR_FILA_AUSENTE);
        }

        return $configuracion;
    }

    /**
     * Autorización fresca: exige actor persistido y rol vigente en DB.
     * No se confía en el rol ni el id de un objeto local transitorio o stale.
     */
    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede administrar los cobros de planillas.';

        // Rechaza objetos transitorios, aunque fingan el id de un admin persistido.
        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);

        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    /**
     * is_int descarta flotantes, strings numéricos y bools: nada se trunca.
     * Rango congruente con el campo SQL INT firmado.
     */
    private function validarTarifa(mixed $tarifa): void
    {
        if (! is_int($tarifa) || $tarifa < self::TARIFA_MINIMA || $tarifa > self::TARIFA_MAXIMA) {
            throw ValidationException::withMessages([
                'tarifa' => ['La tarifa debe ser un importe entero en ARS entre 1 y 2147483647.'],
            ]);
        }
    }

    /**
     * Snapshot del actor tomado de la fila vigente en DB (no del objeto mutado).
     */
    private function registrarAuditoria(User $actorVigente, string $accion, array $valoresAntes, array $valoresDespues): void
    {
        $evento = HistorialCobroPlanilla::query()->create([
            'accion' => $accion,
            'usuario_id' => $actorVigente->getKey(),
            'actor_nombre' => $actorVigente->name,
            'valores_antes' => $valoresAntes,
            'valores_despues' => $valoresDespues,
        ]);

        if (! $evento->exists) {
            throw new RuntimeException('No se pudo registrar el historial de cobros.');
        }
    }
}
