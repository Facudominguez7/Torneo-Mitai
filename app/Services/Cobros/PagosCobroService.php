<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Ingreso único por UUID: aplica al cargo hasta saldar su deuda y conserva excedentes.
 * Los servicios derivan saldos bajo bloqueo; las guardas validan deuda agregada,
 * pero no reemplazan la coordinación transaccional ni la disponibilidad del pago.
 */
class PagosCobroService
{
    private const ID_GLOBAL = 1;

    private const IMPORTE_MAXIMO = 2147483647;

    public const MEDIOS = ['efectivo', 'mercado_pago'];

    public function registrarPago(User $actor, int $cargoId, mixed $importeArs, string $medio, string $claveIdempotencia): PagoPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $cargoId, $importeArs, $medio, $claveIdempotencia): PagoPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $clave = $this->validarEntrada($importeArs, $medio, $claveIdempotencia);
                $this->exigirConfiguracionActiva();

                // Lectura inmutable para derivar la cuenta; la fila se relee bloqueada después.
                $cargo = CargoPlanilla::query()->find($cargoId);
                if ($cargo === null) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo indicado no existe.']]);
                }

                $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->firstOrCreate([
                    'idEquipo' => (int) $cargo->idEquipo, 'idEdicion' => (int) $cargo->idEdicion,
                ]);
                $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->find((int) $cuenta->id);
                $cargo = CargoPlanilla::query()->lockForUpdate()->find($cargoId);
                if ($cargo === null || $cuenta === null) {
                    throw new RuntimeException('No se pudo bloquear el cargo o la cuenta del equipo.');
                }

                // Idempotencia global: se comprueba tras autorizar y antes de validar saldo,
                // para que el reintento del pago que saldó el cargo devuelva el ingreso original.
                $previo = PagoPlanilla::query()->where('clave_idempotencia', $clave)->lockForUpdate()->first();
                if ($previo !== null) {
                    $coincide = (int) $previo->cargo_origen_id === (int) $cargo->id
                        && (int) $previo->importe_ars === $importeArs
                        && $previo->medio === $medio
                        && (int) $previo->usuario_id === (int) $actorVigente->id;
                    if (! $coincide) {
                        throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia ya fue utilizada con otro pago.']]);
                    }

                    return $previo;
                }

                $aplicado = (int) AplicacionPagoPlanilla::query()->where('cargo_id', (int) $cargo->id)->lockForUpdate()->sum('importe_ars');
                $exencion = ExencionCobroPlanilla::query()->where('cargo_id', (int) $cargo->id)->lockForUpdate()->first();
                $ausente = AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', (int) $cargo->id)->lockForUpdate()->exists();
                $pendiente = (new DistribucionCreditoService)->calcularDeuda([
                    'precio_original' => (int) $cargo->precio_original, 'aplicado_ars' => $aplicado,
                    'eximido_ars' => $exencion === null ? 0 : $exencion->importe_ars, 'ausente' => $ausente,
                ], (int) $cargo->id);
                if ($pendiente === 0) {
                    throw ValidationException::withMessages(['cargo' => ['El cargo no registra deuda pendiente; no se admiten anticipos.']]);
                }

                $importeAplicado = min($importeArs, $pendiente);
                $pago = new PagoPlanilla([
                    'cuenta_id' => (int) $cuenta->id, 'cargo_origen_id' => (int) $cargo->id, 'usuario_id' => (int) $actorVigente->id,
                    'importe_ars' => $importeArs, 'medio' => $medio, 'clave_idempotencia' => $clave,
                ]);
                if (! $pago->save() || ! $pago->exists) {
                    throw new RuntimeException('No se pudo registrar el ingreso del pago.');
                }

                $aplicacion = new AplicacionPagoPlanilla([
                    'pago_id' => (int) $pago->id, 'cargo_id' => (int) $cargo->id, 'importe_ars' => $importeAplicado,
                ]);
                if (! $aplicacion->save() || ! $aplicacion->exists) {
                    throw new RuntimeException('No se pudo aplicar el pago al cargo.');
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'registrar_pago',
                    'usuario_id' => (int) $actorVigente->id,
                    'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['cargo_id' => (int) $cargo->id, 'pendiente_ars' => $pendiente],
                    'valores_despues' => ['pago_id' => (int) $pago->id, 'aplicacion_id' => (int) $aplicacion->id,
                        'importe_aplicado_ars' => $importeAplicado, 'credito_ars' => $importeArs - $importeAplicado, 'ingreso_ars' => $importeArs],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial del pago.');
                }

                return $pago;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo registrar el pago de planilla.', [
                'cargo_id' => $cargoId,
                'actor_id' => $actor->exists ? $actor->getKey() : null,
                'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede registrar pagos de planillas.';

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
     * Singleton global bloqueado: serializa registros entre cuentas y falla
     * cerrado si la fila no existe o los cobros no están activados, sin crearla.
     */
    private function exigirConfiguracionActiva(): void
    {
        $configuracion = ConfiguracionCobroPlanilla::query()->whereKey(self::ID_GLOBAL)->lockForUpdate()->first();
        if ($configuracion === null) {
            throw new RuntimeException('La configuración global de cobros no está inicializada (falta la fila id 1).');
        }

        if ($configuracion->activado_at === null) {
            throw new RuntimeException('Los cobros de planillas no están activados.');
        }
    }

    private function validarEntrada(mixed $importeArs, string $medio, string $claveIdempotencia): string
    {
        if (! is_int($importeArs) || $importeArs < 1 || $importeArs > self::IMPORTE_MAXIMO) {
            throw ValidationException::withMessages(['importe' => ['El importe debe ser un entero en ARS entre 1 y 2147483647.']]);
        }

        if (! in_array($medio, self::MEDIOS, true)) {
            throw ValidationException::withMessages(['medio' => ['El medio debe ser efectivo o mercado_pago.']]);
        }

        if (! Str::isUuid($claveIdempotencia)) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia debe ser un UUID válido.']]);
        }

        return strtolower($claveIdempotencia);
    }
}
