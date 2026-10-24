<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Distribución interna del crédito de UN pago ya registrado hacia deudores de
 * su misma cuenta equipo-edición: seleccionado primero, resto FIFO por fecha
 * vigente. No crea ingresos, no muta pagos ni cargos y audita un solo evento.
 * La autorización, la configuración y el replay delegan en InicioCreditoService.
 */
class CreditosCobroService
{
    /**
     * Aplicar el crédito disponible del pago hasta cubrir las deudas de su cuenta.
     */
    public function aplicarCredito(User $actor, int $pagoId, int $cargoId, string $claveIdempotencia): HistorialCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $pagoId, $cargoId, $claveIdempotencia): HistorialCobroPlanilla {
                // El replay precede a la validación de saldo, deuda y calendario:
                // la misma tripleta devuelve el evento original, otra falla sin mutar.
                $inicio = (new InicioCreditoService)->iniciar($actor, $pagoId, $cargoId, $claveIdempotencia);
                if ($inicio['evento'] !== null) {
                    return $inicio['evento'];
                }

                $actorVigente = $inicio['actor'];
                $clave = $inicio['clave'];

                [$cuenta, $pago] = $this->bloquearCuentaYPago($pagoId);
                [$cargos, $aplicadoPorCargo, $eximidoPorCargo, $ausentes] = $this->bloquearCargosYPadres($cuenta, $cargoId);

                $credito = $this->creditoDisponible($pago);
                if ($credito < 0) {
                    throw new RuntimeException('El pago registra aplicaciones mayores a su importe: saldo corrupto.');
                }

                if ($credito === 0) {
                    throw ValidationException::withMessages(['pago' => ['El pago no conserva crédito disponible.']]);
                }

                $plan = (new DistribucionCreditoService)->planificar($this->filasCargos($cargos, $aplicadoPorCargo, $eximidoPorCargo, $ausentes), $cargoId, $credito);
                if ($plan['aplicaciones'] === []) {
                    throw ValidationException::withMessages(['cargo' => ['Ningún cargo de la cuenta registra deuda pendiente.']]);
                }

                $aplicaciones = [];
                foreach ($plan['aplicaciones'] as $planificada) {
                    $aplicacion = new AplicacionPagoPlanilla([
                        'pago_id' => (int) $pago->id, 'cargo_id' => $planificada['cargo_id'], 'importe_ars' => $planificada['importe_ars'],
                    ]);
                    if (! $aplicacion->save() || ! $aplicacion->exists) {
                        throw new RuntimeException('No se pudo aplicar el crédito al cargo.');
                    }

                    $aplicaciones[] = ['aplicacion_id' => (int) $aplicacion->id, 'cargo_id' => $planificada['cargo_id'], 'importe_ars' => $planificada['importe_ars']];
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'aplicar_credito',
                    'usuario_id' => (int) $actorVigente->id,
                    'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['pago_id' => $pagoId, 'cargo_id' => $cargoId, 'clave_idempotencia' => $clave, 'credito_ars' => $credito],
                    'valores_despues' => ['aplicaciones' => $aplicaciones, 'importe_aplicado_ars' => $credito - $plan['credito_restante_ars'], 'credito_restante_ars' => $plan['credito_restante_ars']],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial del crédito.');
                }

                return $evento;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo aplicar el crédito del pago.', ['pago_id' => $pagoId, 'cargo_id' => $cargoId, 'actor_id' => $actor->exists ? (int) $actor->getKey() : null, 'excepcion' => $excepcion::class]);

            throw $excepcion;
        }
    }

    /** La cuenta se deriva del pago (campo inmutable) y el pago se relee con bloqueo vigente. */
    private function bloquearCuentaYPago(int $pagoId): array
    {
        $previo = PagoPlanilla::query()->find($pagoId);
        if ($previo === null) {
            throw ValidationException::withMessages(['pago' => ['El pago indicado no existe.']]);
        }

        $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->find((int) $previo->cuenta_id);
        $pago = PagoPlanilla::query()->lockForUpdate()->find($pagoId);
        if ($cuenta === null || $pago === null) {
            throw new RuntimeException('No se pudo bloquear el pago o su cuenta de cobros.');
        }

        return [$cuenta, $pago];
    }

    /**
     * Bloquea cargos de la cuenta por id ascendente, verifica la whitelist
     * antes de resolver el morfo y lee aplicaciones y padres vigentes.
     */
    private function bloquearCargosYPadres(CuentaCobroPlanilla $cuenta, int $cargoId): array
    {
        $cargos = CargoPlanilla::query()
            ->where('idEquipo', $cuenta->idEquipo)->where('idEdicion', $cuenta->idEdicion)
            ->orderBy('id')->lockForUpdate()->get();
        if (! $cargos->contains('id', $cargoId)) {
            throw ValidationException::withMessages(['cargo' => ['El cargo indicado no existe o pertenece a otra cuenta.']]);
        }

        $this->exigirWhitelistFinanciera($cargos);
        $aplicadoPorCargo = AplicacionPagoPlanilla::query()
            ->whereIn('cargo_id', $cargos->modelKeys())->orderBy('cargo_id')->lockForUpdate()->get()
            ->groupBy(fn (AplicacionPagoPlanilla $aplicacion): int => (int) $aplicacion->cargo_id)
            ->map(fn ($aplicaciones): int => (int) $aplicaciones->sum('importe_ars'))
            ->all();
        $cargos->load(['parent' => fn (MorphTo $padres) => $padres->constrain([
            Partido::class => fn ($consulta) => $consulta->lockForUpdate(),
            InstanciaFinal::class => fn ($consulta) => $consulta->lockForUpdate(),
        ])]);

        $eximidoPorCargo = ExencionCobroPlanilla::query()
            ->whereIn('cargo_id', $cargos->modelKeys())->orderBy('cargo_id')->lockForUpdate()->get()
            ->mapWithKeys(fn (ExencionCobroPlanilla $exencion): array => [(int) $exencion->cargo_id => $exencion->importe_ars])->all();

        $ausentes = AusenciaCobroPlanilla::query()->vigentes()
            ->whereIn('cargo_id', $cargos->modelKeys())->orderBy('cargo_id')->lockForUpdate()->pluck('cargo_id')
            ->mapWithKeys(fn (int $cargo): array => [$cargo => true])->all();

        return [$cargos, $aplicadoPorCargo, $eximidoPorCargo, $ausentes];
    }

    /** Crédito vigente del pago: importe menos aplicaciones leídas bajo bloqueo. */
    private function creditoDisponible(PagoPlanilla $pago): int
    {
        return (int) $pago->importe_ars - (int) AplicacionPagoPlanilla::query()
            ->where('pago_id', (int) $pago->id)->lockForUpdate()->sum('importe_ars');
    }

    /** Filas del planificador leídas en caliente; los saldados no exigen padre ni fecha. */
    private function filasCargos(Collection $cargos, array $aplicadoPorCargo, array $eximidoPorCargo, array $ausentes): array
    {
        return $cargos->map(fn (CargoPlanilla $cargo): array => [
            'id' => (int) $cargo->id,
            'precio_original' => (int) $cargo->precio_original,
            'aplicado_ars' => $aplicadoPorCargo[(int) $cargo->id] ?? 0,
            'eximido_ars' => $eximidoPorCargo[(int) $cargo->id] ?? 0,
            'ausente' => isset($ausentes[(int) $cargo->id]),
            'padre_valido' => $cargo->parent !== null,
            'fecha_vigente' => $this->fechaVigente($cargo, $aplicadoPorCargo[(int) $cargo->id] ?? 0, $eximidoPorCargo[(int) $cargo->id] ?? 0, isset($ausentes[(int) $cargo->id])),
        ])->all();
    }

    /** Fecha vigente del deudor tomada del padre actual (horario por clase), sin snapshot ni created_at. */
    private function fechaVigente(CargoPlanilla $cargo, int $aplicado, int $eximido, bool $ausente): ?string
    {
        $pendiente = (new DistribucionCreditoService)->calcularDeuda([
            'precio_original' => (int) $cargo->precio_original, 'aplicado_ars' => $aplicado, 'eximido_ars' => $eximido, 'ausente' => $ausente,
        ], (int) $cargo->id);
        if ($cargo->parent === null || $pendiente === 0) {
            return null;
        }

        $campo = $cargo->parent_type === Partido::class ? 'horario_datetime' : 'horario';

        return trim((string) $cargo->parent->getAttribute($campo)) ?: null;
    }

    /** Guarda previa al morfo: falla cerrado sin autocargar una clase padre arbitraria. */
    private function exigirWhitelistFinanciera(Collection $cargos): void
    {
        foreach ($cargos as $cargo) {
            $esperada = CargoPlanilla::TIPOS_FINANCIEROS[(string) $cargo->tipo] ?? null;
            if ($esperada === null || $cargo->parent_type !== $esperada) {
                throw new RuntimeException('La cuenta registra un cargo con cobro padre fuera de la whitelist financiera.');
            }
        }
    }
}
