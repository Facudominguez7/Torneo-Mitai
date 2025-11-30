<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'idCategoria',
    ];

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
