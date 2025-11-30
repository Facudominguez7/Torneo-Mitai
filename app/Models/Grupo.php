<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;
    protected $table = 'grupos';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'idCategoria',
        'idEdicion',
    ];

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipos_grupos', 'idGrupo', 'idEquipo');
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }
    public function partidos()
    {
        return $this->hasMany(Partido::class, 'idGrupo');
    }
    public function tablaPosiciones()
    {
        return $this->hasMany(TablaPosicion::class, 'idGrupo');
    }
}
