<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TablaPosicion extends Model
{
    use HasFactory;
    protected $table = 'tabla_posiciones';
    public $timestamps = false;
    protected $fillable = [
        'idGrupo',
        'idEquipo',
        'idEdicion',
        'puntos',
        'golesFavor',
        'golesContra',
        'diferenciaGoles',
        'jugado',
        'ganado',
        'perdido',
        'empatado',
    ];
    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'idGrupo');
    }
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }
}
