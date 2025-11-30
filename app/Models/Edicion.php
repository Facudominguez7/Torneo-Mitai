<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edicion extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'ediciones';
    protected $fillable = ['nombre'];

    public function categorias()
    {
        return $this->hasMany(Categoria::class, 'idEdicion');
    }
    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipo_ediciones', 'idEdicion', 'idEquipo');
    }
    public function fechas()
    {
        return $this->hasMany(Fecha::class, 'idEdicion');
    }
    public function goleadores()
    {
        return $this->hasMany(Goleador::class, 'idEdicion');
    }
    public function campeones()
    {
        return $this->hasMany(Campeon::class, 'idEdicion');
    }
    public function subcampeones()
    {
        return $this->hasMany(Subcampeon::class, 'idEdicion');
    }
    public function copas()
    {
        return $this->hasMany(Copa::class, 'idEdicion');
    }
    public function vallasMenosVencidas()
    {
        return $this->hasMany(VallaMenosVencida::class, 'idEdicion');
    }
    public function partidos()
    {
        return $this->hasMany(Partido::class, 'idEdicion');
    }

      // Relación con InstanciaFinal (una edición tiene muchas instancias finales)
      public function instanciasFinales()
      {
          return $this->hasMany(InstanciaFinal::class);
      }
      // Relación con jugadores (muchos a muchos)
    public function jugadores()
    {
        return $this->belongsToMany(Jugador::class, 'jugadores_ediciones', 'idEdicion', 'idJugador')
                    ->withPivot(['idEquipo', 'idCategoria'])
                    ->withTimestamps();
    }
    // Relación con equipos ediciones (una edición tiene muchos equipos ediciones)
    public function equiposEdiciones()
    {
        return $this->hasMany(EquipoEdicion::class, 'idEdicion');
    }
}
