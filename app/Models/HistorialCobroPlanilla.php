<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Registro append-only de auditoría de cobros de planillas.
 *
 * Solo el servicio crea eventos; no existen endpoints de edición ni borrado.
 * El nombre del actor se guarda como snapshot para sobrevivir al borrado de la cuenta.
 *
 * @property int $id
 * @property string $accion actualizar_tarifa|activar_cobros
 * @property int|null $usuario_id
 * @property string $actor_nombre
 * @property array<string, mixed>|null $valores_antes
 * @property array<string, mixed>|null $valores_despues
 * @property Carbon|null $created_at
 */
class HistorialCobroPlanilla extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['accion', 'usuario_id', 'actor_nombre', 'valores_antes', 'valores_despues'];

    /**
     * Obtener los atributos que deben ser casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valores_antes' => 'array',
            'valores_despues' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Protección a nivel modelo contra edición y borrado (Eloquent).
     * No protege contra QueryBuilder/SQL directo.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('El historial de cobros es append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('El historial de cobros es append-only.');
        });
    }
}
