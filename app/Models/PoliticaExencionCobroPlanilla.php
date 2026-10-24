<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Autorización histórica de un equipo en una edición, sin importe ni saldo.
 * Persistencia inerte: registro auditado y materialización son operaciones futuras.
 * Las guardas por instancia no protegen SQL directo ni eventos deshabilitados.
 */
class PoliticaExencionCobroPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'politica_exencion_cobro_planillas';

    protected $fillable = ['idEquipo', 'idEdicion', 'usuario_id', 'motivo'];

    protected function casts(): array
    {
        return [
            'idEquipo' => 'integer', 'idEdicion' => 'integer', 'usuario_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'idEquipo', 'id');
    }

    public function edicion(): BelongsTo
    {
        return $this->belongsTo(Edicion::class, 'idEdicion', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    protected static function booted(): void
    {
        static::creating(function (PoliticaExencionCobroPlanilla $politica): void {
            $atributos = $politica->getAttributes();
            foreach (['idEquipo', 'idEdicion', 'usuario_id'] as $campo) {
                $valor = $atributos[$campo] ?? null;
                if (! is_int($valor) || $valor < 1 || $valor > 2147483647) {
                    throw new LogicException('La política requiere identificadores enteros positivos dentro de INT firmado.');
                }
            }

            $motivo = $atributos['motivo'] ?? null;
            if ($motivo !== null && ! is_string($motivo)) {
                throw new LogicException('El motivo de la política debe ser texto o null.');
            }

            if (! Equipo::query()->whereKey($politica->idEquipo)->exists()
                || ! Edicion::query()->whereKey($politica->idEdicion)->exists()
                || ! EquipoEdicion::query()->where('idEquipo', $politica->idEquipo)->where('idEdicion', $politica->idEdicion)->exists()) {
                throw new LogicException('La política requiere equipo y edición existentes con inscripción vigente.');
            }

            $usuario = User::query()->find($politica->usuario_id);
            if (! $usuario || ! $usuario->isAdmin()) {
                throw new AuthorizationException('El otorgante de la política debe ser un administrador vigente.');
            }

            $politica->actor_nombre = $usuario->name;
            $politica->setCreatedAt(now());
        });

        static::updating(function (): void {
            throw new LogicException('Las políticas de exención no se pueden editar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Las políticas se conservan para la historia financiera.');
        });
    }
}
