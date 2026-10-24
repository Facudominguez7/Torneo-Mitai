<?php

namespace App\Models;

use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Recibo interno del ingreso, separado de sus futuras aplicaciones a deuda.
 * El futuro servicio autenticará al actor y coordinará bloqueos y auditoría atómica.
 * Estas guardas no protegen escrituras SQL directas ni eventos deshabilitados.
 */
class PagoPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['cuenta_id', 'cargo_origen_id', 'usuario_id', 'importe_ars', 'medio', 'clave_idempotencia'];

    protected function casts(): array
    {
        return [
            'cuenta_id' => 'integer', 'cargo_origen_id' => 'integer', 'usuario_id' => 'integer',
            'importe_ars' => 'integer', 'recibido_at' => 'immutable_datetime',
        ];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCobroPlanilla::class, 'cuenta_id');
    }

    public function cargoOrigen(): BelongsTo
    {
        return $this->belongsTo(CargoPlanilla::class, 'cargo_origen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected static function booted(): void
    {
        static::creating(function (PagoPlanilla $pago): void {
            $atributos = $pago->getAttributes();
            foreach (['cuenta_id', 'cargo_origen_id', 'usuario_id', 'importe_ars'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1 || ($campo === 'importe_ars' && $valor > 2147483647)) {
                    throw new LogicException('Los identificadores y el importe del pago requieren enteros positivos; el importe debe caber en INT firmado.');
                }
            }

            if (! in_array($pago->medio, PagosCobroService::MEDIOS, true)) {
                throw new LogicException('El medio de pago no está permitido.');
            }
            $clave = $atributos['clave_idempotencia'] ?? null;
            if (! is_string($clave) || ! Str::isUuid($clave)) {
                throw new LogicException('El pago requiere una clave UUID de idempotencia.');
            }

            $cuenta = CuentaCobroPlanilla::query()->find($pago->cuenta_id);
            if (! $cuenta || ! CargoPlanilla::query()->whereKey($pago->cargo_origen_id)
                ->where('idEquipo', $cuenta->idEquipo)->where('idEdicion', $cuenta->idEdicion)->exists()) {
                throw new LogicException('El pago requiere un cargo de origen del equipo y edición de la cuenta.');
            }

            $usuario = User::query()->find($pago->usuario_id);
            if (! $usuario || ! $usuario->isAdmin()) {
                throw new AuthorizationException('El operador del pago debe ser un administrador vigente.');
            }

            $pago->clave_idempotencia = strtolower($clave);
            $pago->actor_nombre = $usuario->name;
            $pago->recibido_at = now();
        });

        static::updating(function (): void {
            throw new LogicException('Los recibos de pago no se pueden editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Los recibos de pago se conservan para la historia financiera.');
        });
    }
}
