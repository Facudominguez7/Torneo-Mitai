<?php

namespace App\Services\Cobros;

use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

/**
 * Preparación de una operación de crédito dentro de la transacción externa del
 * llamador: autoriza al actor, valida la huella y resuelve el replay sin leer
 * entidades financieras ni escribir. Los locks valen hasta el commit del llamador.
 */
class InicioCreditoService
{
    private const ID_GLOBAL = 1;

    private const ACCION = 'aplicar_credito';

    /**
     * Resuelve el inicio de aplicar_credito: devuelve el actor vigente bloqueado,
     * la clave normalizada y el evento original si la clave ya fue usada.
     *
     * @return array{actor: User, clave: string, evento: HistorialCobroPlanilla|null}
     */
    public function iniciar(User $actor, int $pagoId, int $cargoId, string $claveIdempotencia): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Iniciar una operación de crédito exige la transacción externa del servicio de cobros.');
        }

        $actorVigente = $this->resolverActorAdmin($actor);
        $clave = $this->validarEntrada($pagoId, $cargoId, $claveIdempotencia);
        $this->exigirConfiguracionActiva();

        // El replay precede a la lectura de pago, cargo, deuda y calendario: el
        // llamador ejecuta la operación real sólo en la primera invocación.
        $evento = $this->resolverReplay($clave, $actorVigente, $pagoId, $cargoId);

        return ['actor' => $actorVigente, 'clave' => $clave, 'evento' => $evento];
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede iniciar operaciones de crédito.';

        if (! $actor->exists || $actor->isDirty($actor->getKeyName()) || (int) $actor->getKey() < 1) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    private function validarEntrada(int $pagoId, int $cargoId, string $claveIdempotencia): string
    {
        if ($pagoId < 1 || $cargoId < 1) {
            throw ValidationException::withMessages(['operacion' => ['El pago y el cargo de la operación deben ser identificadores positivos.']]);
        }

        if (! Str::isUuid($claveIdempotencia)) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia debe ser un UUID válido.']]);
        }

        return strtolower($claveIdempotencia);
    }

    /** Singleton global bloqueado: serializa claves del dominio y falla cerrado sin fila o inactivo. */
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

    /**
     * Huella en el JSON del historial comparada en minúsculas bajo el lock de
     * configuración, sin unique SQL: coincide la tripleta original o falla la
     * clave sin efectos. Otra acción no reclama la clave; una huella malformada
     * con UUID coincidente se rechaza sin alterar el historial.
     */
    private function resolverReplay(string $clave, User $actorVigente, int $pagoId, int $cargoId): ?HistorialCobroPlanilla
    {
        $previo = HistorialCobroPlanilla::query()
            ->where('accion', self::ACCION)
            ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(valores_antes, '$.\"clave_idempotencia\"'))) = ?", [$clave])
            ->lockForUpdate()
            ->first();
        if ($previo === null) {
            return null;
        }

        $huella = $previo->valores_antes;
        $coincide = is_array($huella)
            && isset($huella['pago_id'], $huella['cargo_id'])
            && (int) $previo->usuario_id === (int) $actorVigente->getKey()
            && is_int($huella['pago_id'])
            && is_int($huella['cargo_id'])
            && $huella['pago_id'] === $pagoId
            && $huella['cargo_id'] === $cargoId;
        if (! $coincide) {
            throw ValidationException::withMessages(['clave_idempotencia' => ['La clave de idempotencia ya fue utilizada con otra operación de crédito.']]);
        }

        return $previo;
    }
}
