<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Porción histórica de un ingreso destinada a un cargo, no un nuevo ingreso.
 * Creación interna: los límites son por fila, no saldos disponibles agregados.
 * El futuro servicio validará deuda efectiva, disponibilidad, autorización y
 * auditoría dentro de una transacción con bloqueo de la cuenta equipo-edición.
 * Las guardas de instancia no protegen SQL directo ni eventos deshabilitados.
 */
class AplicacionPagoPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['pago_id', 'cargo_id', 'importe_ars'];

    protected function casts(): array
    {
        return [
            'pago_id' => 'integer', 'cargo_id' => 'integer', 'importe_ars' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoPlanilla::class, 'pago_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoPlanilla::class, 'cargo_id');
    }

    protected static function booted(): void
    {
        static::creating(function (AplicacionPagoPlanilla $aplicacion): void {
            $atributos = $aplicacion->getAttributes();
            foreach (['pago_id', 'cargo_id', 'importe_ars'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1 || ($campo === 'importe_ars' && $valor > 2147483647)) {
                    throw new LogicException('La aplicación requiere identificadores enteros positivos e importe ARS dentro de INT firmado.');
                }
            }

            $pago = PagoPlanilla::query()->with('cuenta')->find($aplicacion->pago_id);
            $cargo = CargoPlanilla::query()->find($aplicacion->cargo_id);
            $cuenta = $pago?->cuenta;
            if (! $pago || ! $cargo || ! $cuenta
                || (int) $cargo->idEquipo !== $cuenta->idEquipo
                || (int) $cargo->idEdicion !== $cuenta->idEdicion) {
                throw new LogicException('La aplicación requiere un pago y un cargo del mismo equipo y edición.');
            }

            if ($aplicacion->importe_ars > $pago->importe_ars || $aplicacion->importe_ars > $cargo->precio_original) {
                throw new LogicException('Una porción no puede superar el ingreso original ni el precio original del cargo.');
            }

            $aplicacion->setCreatedAt(now());
        });

        static::updating(function (): void {
            throw new LogicException('Las aplicaciones de pagos no se pueden editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Las aplicaciones se conservan para la historia financiera.');
        });
    }
}
