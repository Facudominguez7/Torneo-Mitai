<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanillaJugador extends Model
{
    use HasFactory;
    protected $table = 'planilla_jugadores';
    // Timestamps ahora están habilitados para poder ordenar correctamente
    public $timestamps = true;
    protected $fillable = [
        'partido_id',
        'dni_jugador',
        'partido_type',
        'idEquipo',
        'idEdicion',
        'numero_camiseta',
        'goles',
        'asistio',
        'fecha_nacimiento',
        'idCategoria',
        'tarjeta_roja',
    ];

    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'dni_jugador', 'dni');
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
    public function partido()
    {
        return $this->morphTo();
    }
    public function equipo()
    {
        return $this->belongsTo(EquipoEdicion::class, 'idEquipo', 'idEquipo');
    }
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

}
