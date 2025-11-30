<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstanciaFinal extends Model
{
    // Especificamos el nombre de la tabla si es diferente al plural del modelo
    protected $table = 'instancias_finales';

    // Especificamos los campos que pueden ser asignados masivamente
    protected $fillable = [
        'idCategoria',
        'idCopa',
        'idEdicion',
        'idFase',
        'idEquipoLocal',
        'idEquipoVisitante',
        'golesEquipoLocal',
        'golesEquipoVisitante',
        'penalesEquipoLocal',
        'penalesEquipoVisitante',
        'resultadoGlobalLocal',      // Corregido: resoltado -> resultado
        'resultadoGlobalVisitante',  // Corregido: resoltado -> resultado
        'horario',
        'cancha'
    ];

    // Relación con la tabla 'Categoria'
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }

    // Relación con la tabla 'Copa'
    public function copa()
    {
        return $this->belongsTo(Copa::class, 'idCopa');
    }
    // Relación con la tabla 'Fase'
    public function fase()
    {
        return $this->belongsTo(Fase::class, 'idFase');
    }

    // Relación con la tabla 'Edicion'
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    // Relación con la tabla 'Equipo' (Equipo Local)
    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'idEquipoLocal');
    }

    // Relación con la tabla 'Equipo' (Equipo Visitante)
    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'idEquipoVisitante');
    }
     // Relación con la tabla 'Dia'
     public function dia()
     {
         return $this->belongsTo(Dia::class, 'idDia');
     }
     public function jugadoresPartido()
    {
        return $this->morphMany(JugadorPartido::class, 'partido');
    }
}
