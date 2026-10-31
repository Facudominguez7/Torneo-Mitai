<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

class Suscriptor extends Model
{
    use HasFactory;

    protected $table = 'suscriptores';

    protected $fillable = [
        'email', 'usuario_id', 'recibe_resumen_fecha', 'recibe_avisos', 'recibe_proxima_fecha',
    ];

    protected function casts(): array
    {
        return [
            'recibe_resumen_fecha' => 'boolean',
            'recibe_avisos' => 'boolean',
            'recibe_proxima_fecha' => 'boolean',
            'confirmado_at' => 'datetime',
            'baja_at' => 'datetime',
            'resend_sincronizado_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_suscriptor', 'suscriptor_id', 'idEquipo');
    }

    /** Confirmó su correo y no se dio de baja. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNotNull('confirmado_at')->whereNull('baja_at');
    }

    /**
     * Activos que eligieron ese aviso (clave de config('suscripciones.avisos')); sin aviso, todos los activos.
     */
    public function scopeRecibenAviso(Builder $query, ?string $aviso): Builder
    {
        $query->activos();

        if ($aviso === null) {
            return $query;
        }

        $columna = config("suscripciones.avisos.{$aviso}");

        if (! is_string($columna)) {
            throw new InvalidArgumentException("El aviso {$aviso} no está configurado.");
        }

        return $query->where($columna, true);
    }

    /** Activos que eligieron recibir el resumen de cada fecha. */
    public function scopeRecibenResumenFecha(Builder $query): Builder
    {
        return $query->recibenAviso('resumen_fecha');
    }

    public function estaActivo(): bool
    {
        return $this->confirmado_at !== null && $this->baja_at === null;
    }
}
