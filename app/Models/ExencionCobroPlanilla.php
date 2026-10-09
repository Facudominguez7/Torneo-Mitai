<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Importe histórico eximido de un cargo concreto de un equipo, no un saldo.
 * Persistencia interna e inerte: el futuro servicio calculará deuda pendiente,
 * autenticará al actor y coordinará bloqueos y auditoría atómica.
 * Los límites son por fila; las guardas no protegen SQL directo ni eventos deshabilitados.
 */
class ExencionCobroPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'exencion_cobro_planillas';

    protected $fillable = ['cargo_id', 'usuario_id', 'importe_ars', 'motivo'];

    protected function casts(): array
    {
        return [
            'cargo_id' => 'integer', 'usuario_id' => 'integer', 'importe_ars' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoPlanilla::class, 'cargo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected static function booted(): void
    {
        static::creating(function (ExencionCobroPlanilla $exencion): void {
            $atributos = $exencion->getAttributes();
            foreach (['cargo_id', 'usuario_id', 'importe_ars'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1 || ($campo === 'importe_ars' && $valor > 2147483647)) {
                    throw new LogicException('La exención requiere identificadores enteros positivos e importe ARS dentro de INT firmado.');
                }
            }

            $motivo = $atributos['motivo'] ?? null;
            if ($motivo !== null && ! is_string($motivo)) {
                throw new LogicException('El motivo de la exención debe ser texto o null.');
            }

            $cargo = CargoPlanilla::query()->find($exencion->cargo_id);
            if (! $cargo || $exencion->importe_ars > $cargo->precio_original) {
                throw new LogicException('La exención requiere un cargo existente y no puede superar su precio original.');
            }

            $usuario = User::query()->find($exencion->usuario_id);
            if (! $usuario || ! $usuario->isAdmin()) {
                throw new AuthorizationException('El operador de la exención debe ser un administrador vigente.');
            }

            $exencion->actor_nombre = $usuario->name;
            $exencion->setCreatedAt(now());
        });

        static::updating(function (): void {
            throw new LogicException('Las exenciones de cobros no se pueden editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Las exenciones se conservan para la historia financiera.');
        });
    }
}
