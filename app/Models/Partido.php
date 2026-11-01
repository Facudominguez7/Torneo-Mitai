<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Partido extends Model
{
    use HasFactory;

    protected $table = 'partidos';

    public $timestamps = false;

    protected $fillable = [
        'idFechas',
        'idEquipoLocal',
        'idEquipoVisitante',
        'idGrupo',
        'idEdicion',
        'golesEquipoLocal',
        'golesEquipoVisitante',
        'horario_datetime',
        'cancha',
        'jugado',
        'ganador_por_no_vino',
        'idCategoria',
        'es_interzonal',
        'goles_confirmados_at',
        'public_token',
    ];

    protected $casts = [
        'goles_confirmados_at' => 'datetime',
        'ganador_por_no_vino' => 'integer',
    ];

    protected $appends = ['public_token'];

    public function getPublicTokenAttribute(): ?string
    {
        return $this->attributes['public_token'] ?? null;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Partido $partido) {
            if (empty($partido->public_token)) {
                $partido->public_token = Str::uuid()->toString();
            }
        });
    }

    /**
     * Determina si el partido es interzonal
     */
    public function esInterzonal(): bool
    {
        return (bool) $this->es_interzonal;
    }

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'idEquipoLocal');
    }

    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'idEquipoVisitante');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'idGrupo');
    }

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'idFechas');
    }

    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    public function planillas()
    {
        return $this->hasMany(PlanillaJugador::class, 'partido_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
}
