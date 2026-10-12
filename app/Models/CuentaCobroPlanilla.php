<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Identidad estable del equipo en una edición para futuros bloqueos financieros.
 * No almacena saldos: se derivarán de pagos y aplicaciones. Su creación es interna;
 * la autorización del administrador corresponderá al futuro servicio de pagos.
 * Las guardas de instancia no protegen contra SQL directo ni eventos deshabilitados.
 */
class CuentaCobroPlanilla extends Model
{
    protected $fillable = ['idEquipo', 'idEdicion'];

    protected function casts(): array
    {
        return ['idEquipo' => 'integer', 'idEdicion' => 'integer'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }

    public function edicion(): BelongsTo
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    protected static function booted(): void
    {
        static::creating(function (CuentaCobroPlanilla $cuenta): void {
            foreach (['idEquipo', 'idEdicion'] as $campo) {
                $id = $cuenta->getAttributes()[$campo] ?? null;
                if (! is_int($id) || $id < 1 || $id > 2147483647) {
                    throw new LogicException('La identidad de la cuenta requiere identificadores enteros positivos dentro del rango legacy.');
                }
            }

            $inscripto = EquipoEdicion::query()
                ->where('idEquipo', $cuenta->idEquipo)
                ->where('idEdicion', $cuenta->idEdicion)
                ->whereHas('equipo')
                ->whereHas('edicion')
                ->exists();

            if (! $inscripto) {
                throw new LogicException('La cuenta requiere un equipo existente inscripto en la edición existente.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Las cuentas de cobro no se pueden reasignar ni editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Las cuentas de cobro se conservan para la historia financiera y no se pueden borrar.');
        });
    }
}
