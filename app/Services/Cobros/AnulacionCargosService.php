<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anula la deuda de planilla de un partido que se elimina. Los cargos son
 * históricos y no se borran: su saldo pendiente se exime con el motivo
 * «Partido eliminado» para que no quede deuda huérfana. Si algún equipo ya
 * pagó, el partido no se puede eliminar.
 */
class AnulacionCargosService
{
    public const MOTIVO = 'Partido eliminado';

    public const MENSAJE_CON_PAGOS = 'No se puede eliminar el partido: ya tiene pagos de planilla registrados.';

    /**
     * Toma el actor y la configuración global antes de bloquear el partido.
     * Pagos, crédito y «no vino» bloquean el partido al final; respetar ese
     * orden evita interbloqueos con un pago simultáneo sobre el mismo partido.
     */
    public function bloquearAntesDelPartido(User $actor): User
    {
        $this->exigirTransaccion();
        $actorVigente = $this->resolverActorAdmin($actor);
        ConfiguracionCobroPlanilla::query()->whereKey(1)->lockForUpdate()->first();

        return $actorVigente;
    }

    /**
     * Debe ejecutarse dentro de la transacción que elimina el partido, después
     * de bloquearAntesDelPartido y antes de borrarlo, para que un rechazo
     * revierta también la eliminación.
     *
     * @throws DomainException cuando algún cargo del partido ya registra pagos
     */
    public function anularPorEliminacion(User $actorVigente, Partido|InstanciaFinal $padre): void
    {
        $this->exigirTransaccion();

        $ids = CargoPlanilla::query()->where('parent_type', $padre::class)
            ->where('parent_id', $padre->getKey())->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $cargos = CargoPlanilla::query()->whereKey($ids)->orderBy('id')->get();
        foreach ($cargos->map(fn (CargoPlanilla $cargo): array => [(int) $cargo->idEquipo, (int) $cargo->idEdicion])->unique() as [$idEquipo, $idEdicion]) {
            CuentaCobroPlanilla::query()->where('idEquipo', $idEquipo)->where('idEdicion', $idEdicion)->lockForUpdate()->first();
        }
        $cargos = CargoPlanilla::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if (AplicacionPagoPlanilla::query()->whereIn('cargo_id', $ids)->lockForUpdate()->exists()) {
            throw new DomainException(self::MENSAJE_CON_PAGOS);
        }

        // Sin pagos, el saldo a anular es el precio completo. Un cargo con «no vino»
        // vigente también se exime: si después se deshace la ausencia, la deuda
        // volvería sobre un partido que ya no existe.
        $exentos = ExencionCobroPlanilla::query()->whereIn('cargo_id', $ids)->lockForUpdate()->pluck('cargo_id')->all();
        AusenciaCobroPlanilla::query()->whereIn('cargo_id', $ids)->lockForUpdate()->get();
        $anuladas = [];

        foreach ($cargos as $cargo) {
            if (in_array($cargo->id, $exentos, true)) {
                continue;
            }

            $pendiente = (int) $cargo->precio_original;
            $exencion = new ExencionCobroPlanilla([
                'cargo_id' => (int) $cargo->id, 'usuario_id' => (int) $actorVigente->id,
                'importe_ars' => $pendiente, 'motivo' => self::MOTIVO,
            ]);
            if (! $exencion->save() || ! $exencion->exists) {
                throw new RuntimeException('No se pudo anular el cargo del partido eliminado.');
            }
            $anuladas[(int) $cargo->id] = $pendiente;
        }

        $evento = new HistorialCobroPlanilla([
            'accion' => 'anular_cargos_partido_eliminado', 'usuario_id' => (int) $actorVigente->id,
            'actor_nombre' => $actorVigente->name,
            'valores_antes' => ['parent' => ['tipo' => $padre::class, 'id' => (int) $padre->getKey()]],
            'valores_despues' => [
                'cargos' => array_map('intval', $ids),
                'anulados' => array_keys($anuladas),
                'anulado_ars' => array_sum($anuladas),
            ],
        ]);
        if (! $evento->save() || ! $evento->exists) {
            throw new RuntimeException('No se pudo registrar el historial de la anulación.');
        }
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('La anulación de cargos requiere la transacción que elimina el partido.');
        }
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede eliminar un partido con cargos de planilla.';
        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $vigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($vigente === null || ! $vigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $vigente;
    }
}
