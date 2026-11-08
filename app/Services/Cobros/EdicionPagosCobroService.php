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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Corrige importe y medio de un recibo ya registrado sin reemplazarlo: conserva su
 * identidad, recepción y clave, recalcula la aplicación del cargo de origen bajo los
 * bloqueos del registro y distribuye el excedente como crédito con la misma cadena.
 *
 * Resultado público: el evento append-only `editar_pago` de ESTA operación. Su
 * snapshot no cambia aunque el pago se edite después, así que no debe presentarse
 * como estado vigente: quien lo muestre tiene que releer `PagoPlanilla` (o
 * `ConsultaCobrosService::resultadoPago`) y usar el evento solo como comprobante de
 * la corrección enviada. Un replay con la misma clave devuelve el evento original.
 *
 * Modelos: `PagoPlanilla` y `AplicacionPagoPlanilla` siguen rechazando cualquier
 * `save`/`update`/`delete` Eloquent, también para este servicio. La corrección usa
 * dos `whereKey(...)->update()` acotados por las claves inmutables del recibo dentro
 * de esta transacción bloqueada, exige una fila afectada y relee ambas filas para
 * confirmar importe, medio, porción aplicada y que el aplicado no supere el importe.
 *
 * Un motivo vacío o solo con espacios se guarda como `null` y se normaliza antes del
 * replay. Una edición que no cambia importe ni medio se rechaza con `importe_ars`
 * («No hay cambios para guardar.») antes de cualquier escritura, después del replay.
 */
class EdicionPagosCobroService
{
    private const ID_GLOBAL = 1;

    private const ACCION = 'editar_pago';

    private const ACCION_CREDITO = 'aplicar_credito';

    private const IMPORTE_MAXIMO = 2147483647;

    /**
     * Clave de crédito derivada de la edición: UUID v5 estable y distinto de la
     * clave de la operación, para que el replay del crédito no dependa de la clave
     * de edición ni reutilice un `aplicar_credito` ajeno.
     */
    public static function claveDeCredito(string $claveIdempotencia, int $pagoId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'mitai.cobros.editar_pago:'.strtolower($claveIdempotencia).':'.$pagoId)->toString();
    }

    public function editarPago(User $actor, int $pagoId, mixed $importeArs, string $medio, ?string $motivo, string $claveIdempotencia): HistorialCobroPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $pagoId, $importeArs, $medio, $motivo, $claveIdempotencia): HistorialCobroPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $clave = $this->validarEntrada($importeArs, $medio, $claveIdempotencia);
                // Normalizar antes del replay: un motivo en blanco coincide con el guardado sin motivo.
                $motivo = $this->normalizarMotivo($motivo);
                $this->exigirConfiguracionActiva();
                $importe = (int) $importeArs;

                // Lectura inmutable para derivar la cuenta; la fila se relee bloqueada después.
                $previo = PagoPlanilla::query()->find($pagoId);
                if ($previo === null) {
                    throw ValidationException::withMessages(['pago' => ['El pago indicado no existe.']]);
                }

                $cuenta = CuentaCobroPlanilla::query()->lockForUpdate()->find((int) $previo->cuenta_id);
                $pago = PagoPlanilla::query()->lockForUpdate()->find($pagoId);
                if ($cuenta === null || $pago === null) {
                    throw new RuntimeException('No se pudo bloquear el pago o su cuenta de cobros.');
                }

                $cargos = CargoPlanilla::query()
                    ->where('idEquipo', $cuenta->idEquipo)->where('idEdicion', $cuenta->idEdicion)
                    ->orderBy('id')->lockForUpdate()->get();
                $importeAnterior = (int) $pago->importe_ars;
                $medioAnterior = (string) $pago->medio;

                // El replay precede a la deuda y al bloqueo por crédito: repetir la misma
                // operación devuelve el evento original sin volver a aplicar nada.
                $replay = $this->resolverReplay($clave, $actorVigente, $pagoId, $importe, $medio, $motivo);
                if ($replay !== null) {
                    return $replay;
                }

                // Una edición que no cambia importe ni medio no escribe ni audita nada.
                if ($importe === $importeAnterior && $medio === $medioAnterior) {
                    throw ValidationException::withMessages(['importe_ars' => ['No hay cambios para guardar.']]);
                }

                $previas = AplicacionPagoPlanilla::query()->where('pago_id', $pagoId)->orderBy('id')->lockForUpdate()->get();
                // Snapshot escalar antes de tocar las filas: los modelos se mutan en memoria.
                $aplicacionesPrevias = $previas->map(fn (AplicacionPagoPlanilla $previa): array => $this->listarAplicacion(
                    (int) $previa->id, (int) $previa->cargo_id, (int) $previa->importe_ars,
                ))->all();
                if ($previas->contains(fn (AplicacionPagoPlanilla $aplicacion): bool => (int) $aplicacion->cargo_id !== (int) $pago->cargo_origen_id)) {
                    throw ValidationException::withMessages(['pago' => ['El pago ya distribuyó su excedente como crédito a otras planillas: la edición está bloqueada.']]);
                }
                if ($previas->count() !== 1) {
                    throw new RuntimeException('El pago no conserva una única aplicación a su cargo de origen: estado inconsistente.');
                }

                $aplicacion = $previas->first();
                $deudas = $this->deudasPorCargo($cargos, [(int) $pago->cargo_origen_id => (int) $aplicacion->importe_ars]);
                $deudaOrigen = $deudas[(int) $pago->cargo_origen_id];
                $aplicadaOrigen = min($importe, $deudaOrigen);
                $precioOrigen = (int) ($cargos->firstWhere('id', (int) $pago->cargo_origen_id)?->precio_original ?? 0);
                if ($aplicadaOrigen < 1) {
                    throw ValidationException::withMessages(['importe' => ['El cargo de origen ya no registra deuda fuera de este pago: no se puede recalcular su aplicación.']]);
                }
                if ($aplicadaOrigen > $precioOrigen) {
                    throw new RuntimeException('La porción de origen excede el precio original del cargo.');
                }

                // Ninguna escritura deja el aplicado por encima del importe: al bajar el monto
                // se corrige primero la porción y al subirlo primero el recibo.
                if ($importe < (int) $aplicacion->importe_ars) {
                    $this->corregirAplicacion($aplicacion, $pago, $aplicadaOrigen);
                    $this->corregirPago($pago, $cuenta, $importe, $medio);
                } else {
                    $this->corregirPago($pago, $cuenta, $importe, $medio);
                    $this->corregirAplicacion($aplicacion, $pago, $aplicadaOrigen);
                }
                $this->verificarEscritura($pagoId, (int) $aplicacion->id, (int) $pago->cargo_origen_id, $importe, $medio, $aplicadaOrigen);

                $aplicaciones = [$this->listarAplicacion((int) $aplicacion->id, (int) $pago->cargo_origen_id, $aplicadaOrigen)];
                $afectados = [(int) $pago->cargo_origen_id];
                $claveCredito = null;
                if ($importe - $aplicadaOrigen > 0 && $this->hayDeuda($cargos)) {
                    $claveCredito = $this->claveDeCreditoLibre($clave, $pagoId);
                    $credito = (new CreditosCobroService)->aplicarCredito($actorVigente, $pagoId, (int) $pago->cargo_origen_id, $claveCredito);
                    foreach ($credito->valores_despues['aplicaciones'] as $planificada) {
                        $aplicaciones[] = $this->listarAplicacion((int) $planificada['aplicacion_id'], (int) $planificada['cargo_id'], (int) $planificada['importe_ars']);
                        $afectados[] = (int) $planificada['cargo_id'];
                    }
                }

                $despues = ['pago_id' => $pagoId, 'importe_ars' => $importe, 'medio' => $medio,
                    'aplicaciones' => $aplicaciones, 'motivo' => $motivo];
                if ($claveCredito !== null) {
                    $despues['clave_credito'] = $claveCredito;
                }
                // Relectura final: el crédito distribuido tampoco excede el importe del recibo.
                $this->verificarEscritura($pagoId, (int) $aplicacion->id, (int) $pago->cargo_origen_id, $importe, $medio, $aplicadaOrigen);

                $evento = new HistorialCobroPlanilla([
                    'accion' => self::ACCION,
                    'usuario_id' => (int) $actorVigente->id,
                    'actor_nombre' => $actorVigente->name,
                    'valores_antes' => [
                        'pago_id' => $pagoId, 'clave_idempotencia' => $clave,
                        'importe_ars' => $importeAnterior, 'medio' => $medioAnterior,
                        'aplicaciones' => $aplicacionesPrevias,
                    ],
                    'valores_despues' => $despues,
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial de la edición del pago.');
                }

                foreach (array_unique($afectados) as $afectado) {
                    $cargoAfectado = $cargos->firstWhere('id', $afectado);
                    if ($cargoAfectado !== null) {
                        app(AusenciasCobroService::class)->sincronizarResultadoDeCargo($cargoAfectado);
                    }
                }

                return $evento;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo editar el pago de planilla.', [
                'pago_id' => $pagoId,
                'actor_id' => $actor->exists ? $actor->getKey() : null,
                'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    /**
     * Corrección acotada del recibo: solo importe y medio, solo sobre la fila
     * bloqueada y sus claves inmutables. Exige una fila afectada o revierte.
     */
    private function corregirPago(PagoPlanilla $pago, CuentaCobroPlanilla $cuenta, int $importeArs, string $medio): void
    {
        if ((int) $pago->importe_ars === $importeArs && $pago->medio === $medio) {
            return;
        }

        $filas = PagoPlanilla::query()
            ->whereKey((int) $pago->id)
            ->where('cuenta_id', (int) $cuenta->id)
            ->where('cargo_origen_id', (int) $pago->cargo_origen_id)
            ->update(['importe_ars' => $importeArs, 'medio' => $medio]);
        if ($filas !== 1) {
            throw new RuntimeException('No se pudo corregir el importe y el medio del pago registrado.');
        }
    }

    /**
     * Corrección acotada de la porción de origen: solo el importe de la fila
     * bloqueada del pago y su cargo de origen. Exige una fila afectada o revierte.
     */
    private function corregirAplicacion(AplicacionPagoPlanilla $aplicacion, PagoPlanilla $pago, int $aplicada): void
    {
        if ((int) $aplicacion->importe_ars === $aplicada) {
            return;
        }

        $filas = AplicacionPagoPlanilla::query()
            ->whereKey((int) $aplicacion->id)
            ->where('pago_id', (int) $pago->id)
            ->where('cargo_id', (int) $pago->cargo_origen_id)
            ->update(['importe_ars' => $aplicada]);
        if ($filas !== 1) {
            throw new RuntimeException('No se pudo corregir la aplicación del pago a su cargo de origen.');
        }
    }

    /**
     * Relectura de las filas corregidas: la edición solo queda firme si la base guardó
     * el importe, el medio y la porción calculados, sin que el aplicado del recibo
     * supere su importe ni quede en cero.
     */
    private function verificarEscritura(int $pagoId, int $aplicacionId, int $cargoOrigenId, int $importeArs, string $medio, int $aplicada): void
    {
        $pago = PagoPlanilla::query()->find($pagoId);
        $aplicacion = AplicacionPagoPlanilla::query()->find($aplicacionId);
        $aplicado = (int) AplicacionPagoPlanilla::query()->where('pago_id', $pagoId)->sum('importe_ars');

        if ($pago === null || $aplicacion === null
            || (int) $pago->importe_ars !== $importeArs || $pago->medio !== $medio
            || (int) $pago->cargo_origen_id !== $cargoOrigenId
            || (int) $aplicacion->importe_ars !== $aplicada || (int) $aplicacion->cargo_id !== $cargoOrigenId
            || $aplicado < 1 || $aplicado > $importeArs) {
            throw new RuntimeException('La corrección del pago no se persistió como se calculó.');
        }
    }

    /**
     * Clave de crédito derivada libre: nunca se asume equivalente a un evento
     * `aplicar_credito` existente, porque su replay omitiría esta distribución.
     */
    private function claveDeCreditoLibre(string $claveEdicion, int $pagoId): string
    {
        $clave = self::claveDeCredito($claveEdicion, $pagoId);
        $ocupada = HistorialCobroPlanilla::query()
            ->where('accion', self::ACCION_CREDITO)
            ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(valores_antes, '$.\"clave_idempotencia\"'))) = ?", [$clave])
            ->lockForUpdate()
            ->exists();
        if ($ocupada) {
            throw new RuntimeException('La clave de crédito derivada de la edición ya fue utilizada por otra operación.');
        }

        return $clave;
    }

    /**
     * Deuda efectiva de cada cargo de la cuenta, descontando las aplicaciones
     * indicadas: la del cargo de origen excluye lo que aporta el pago editado.
     *
     * @param  Collection<int, CargoPlanilla>  $cargos
     * @param  array<int, int>  $descontar
     * @return array<int, int>
     */
    private function deudasPorCargo(Collection $cargos, array $descontar = []): array
    {
        $aplicado = AplicacionPagoPlanilla::query()->whereIn('cargo_id', $cargos->modelKeys())
            ->orderBy('cargo_id')->lockForUpdate()->get()
            ->groupBy(fn (AplicacionPagoPlanilla $aplicacion): int => (int) $aplicacion->cargo_id)
            ->map(fn ($aplicaciones): int => (int) $aplicaciones->sum('importe_ars'))->all();
        $eximido = ExencionCobroPlanilla::query()->whereIn('cargo_id', $cargos->modelKeys())
            ->orderBy('cargo_id')->lockForUpdate()->get()
            ->mapWithKeys(fn (ExencionCobroPlanilla $exencion): array => [(int) $exencion->cargo_id => (int) $exencion->importe_ars])->all();
        $ausentes = AusenciaCobroPlanilla::query()->vigentes()->whereIn('cargo_id', $cargos->modelKeys())
            ->orderBy('cargo_id')->lockForUpdate()->pluck('cargo_id')->flip();

        $deudas = [];
        foreach ($cargos as $cargo) {
            $deudas[(int) $cargo->id] = (new DistribucionCreditoService)->calcularDeuda([
                'precio_original' => (int) $cargo->precio_original,
                'aplicado_ars' => ($aplicado[(int) $cargo->id] ?? 0) - ($descontar[(int) $cargo->id] ?? 0),
                'eximido_ars' => $eximido[(int) $cargo->id] ?? 0,
                'ausente' => $ausentes->has((int) $cargo->id),
            ], (int) $cargo->id);
        }

        return $deudas;
    }

    /** @param Collection<int, CargoPlanilla> $cargos */
    private function hayDeuda(Collection $cargos): bool
    {
        foreach ($this->deudasPorCargo($cargos) as $deuda) {
            if ($deuda > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array{aplicacion_id: int, cargo_id: int, importe_ars: int} */
    private function listarAplicacion(int $aplicacionId, int $cargoId, int $importeArs): array
    {
        return ['aplicacion_id' => $aplicacionId, 'cargo_id' => $cargoId, 'importe_ars' => $importeArs];
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede editar pagos de planillas.';

        if (! $actor->exists || $actor->isDirty($actor->getKeyName()) || (int) $actor->getKey() < 1) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    /** Singleton global bloqueado: serializa ediciones entre cuentas y falla cerrado si está inactivo. */
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

        if (! in_array($medio, PagosCobroService::MEDIOS, true)) {
            throw ValidationException::withMessages(['medio' => ['El medio debe ser efectivo o mercado_pago.']]);
        }

        if (! Str::isUuid($claveIdempotencia)) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia debe ser un UUID válido.']]);
        }

        return strtolower($claveIdempotencia);
    }

    /** Un motivo vacío o solo con espacios equivale a no indicarlo; el texto se conserva literal. */
    private function normalizarMotivo(?string $motivo): ?string
    {
        return $motivo === null || trim($motivo) === '' ? null : $motivo;
    }

    /**
     * Huella de la edición en el JSON del historial, comparada en minúsculas bajo el
     * lock de configuración: misma clave, actor, pago, importe, medio y motivo, o se
     * rechaza sin efectos. Otra acción no reclama la clave.
     */
    private function resolverReplay(string $clave, User $actorVigente, int $pagoId, int $importeArs, string $medio, ?string $motivo): ?HistorialCobroPlanilla
    {
        $previo = HistorialCobroPlanilla::query()
            ->where('accion', self::ACCION)
            ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(valores_antes, '$.\"clave_idempotencia\"'))) = ?", [$clave])
            ->lockForUpdate()
            ->first();
        if ($previo === null) {
            return null;
        }

        $antes = $previo->valores_antes;
        $despues = $previo->valores_despues;
        $coincide = is_array($antes) && is_array($despues)
            && (int) $previo->usuario_id === (int) $actorVigente->getKey()
            && ($antes['pago_id'] ?? null) === $pagoId
            && ($despues['importe_ars'] ?? null) === $importeArs
            && ($despues['medio'] ?? null) === $medio
            && array_key_exists('motivo', $despues)
            && $despues['motivo'] === $motivo;
        if (! $coincide) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia ya fue utilizada con otra edición del pago.']]);
        }

        return $previo;
    }
}
