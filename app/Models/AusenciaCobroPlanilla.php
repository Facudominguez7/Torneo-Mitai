<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Marca "No vino" de un cargo: el equipo no pagó la planilla de ese partido pero no la debe.
 * No es una exención ni un importe. Se deshace una sola vez registrando quién y cuándo;
 * volver a marcar crea otra fila. Los servicios coordinan bloqueos y auditoría atómica.
 */
class AusenciaCobroPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ausencia_cobro_planillas';

    protected $fillable = ['cargo_id', 'usuario_id', 'motivo'];

    protected function casts(): array
    {
        return [
            'cargo_id' => 'integer', 'usuario_id' => 'integer', 'revertido_por' => 'integer',
            'created_at' => 'immutable_datetime', 'revertido_at' => 'immutable_datetime',
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

    /** @param  Builder<AusenciaCobroPlanilla>  $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('revertido_at');
    }

    protected static function booted(): void
    {
        static::creating(function (AusenciaCobroPlanilla $ausencia): void {
            $atributos = $ausencia->getAttributes();
            foreach (['cargo_id', 'usuario_id'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1) {
                    throw new LogicException('La marca de no vino requiere identificadores enteros positivos.');
                }
            }

            $motivo = $atributos['motivo'] ?? null;
            if ($motivo !== null && ! is_string($motivo)) {
                throw new LogicException('El motivo de no vino debe ser texto o null.');
            }

            if (! CargoPlanilla::query()->whereKey($ausencia->cargo_id)->exists()) {
                throw new LogicException('La marca de no vino requiere un cargo existente.');
            }

            $usuario = User::query()->find($ausencia->usuario_id);
            if (! $usuario || ! $usuario->isAdmin()) {
                throw new AuthorizationException('El operador de la marca de no vino debe ser un administrador vigente.');
            }

            $ausencia->actor_nombre = $usuario->name;
            $ausencia->revertido_at = null;
            $ausencia->revertido_por = null;
            $ausencia->revertido_por_nombre = null;
            $ausencia->setCreatedAt(now());
        });

        // La única edición admitida es deshacer una marca vigente, una sola vez.
        static::updating(function (AusenciaCobroPlanilla $ausencia): void {
            $permitidos = ['revertido_at', 'revertido_por', 'revertido_por_nombre'];
            if ($ausencia->getOriginal('revertido_at') !== null
                || array_diff(array_keys($ausencia->getDirty()), $permitidos) !== []
                || $ausencia->revertido_at === null || ! is_int($ausencia->revertido_por) || $ausencia->revertido_por < 1) {
                throw new LogicException('Las marcas de no vino solo se pueden deshacer una vez y no se editan.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Las marcas de no vino se conservan para la historia de cobros.');
        });
    }
}
