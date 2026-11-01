<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['nombreCategoria', 'idEdicion', 'jugadores_maximos', 'anio_desde', 'anio_hasta'];

    protected function casts(): array
    {
        return [
            'anio_desde' => 'integer',
            'anio_hasta' => 'integer',
        ];
    }

    /**
     * Año de nacimiento más viejo permitido en la categoría.
     *
     * Usa el rango cargado y, si falta, el año más viejo del nombre
     * (ej. "2021/2020" → 2020). Null si la categoría no es por año.
     */
    public function anioMinimoNacimiento(): ?int
    {
        return self::anioMinimoNacimientoDe($this->anio_desde, $this->nombreCategoria);
    }

    public static function anioMinimoNacimientoDe(?int $anioDesde, ?string $nombreCategoria): ?int
    {
        if ($anioDesde !== null) {
            return $anioDesde;
        }

        preg_match_all('/20\\d{2}/', (string) $nombreCategoria, $coincidencias);
        $anios = array_map('intval', $coincidencias[0]);

        return $anios === [] ? null : min($anios);
    }

    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'idCategoria');
    }

    public function campeones()
    {
        return $this->hasMany(Campeon::class, 'idCategoria');
    }

    public function fechas()
    {
        return $this->hasMany(Fecha::class, 'idCategoria');
    }

    public function goleadores()
    {
        return $this->hasMany(Goleador::class, 'idCategoria');
    }

    public function subcampeones()
    {
        return $this->hasMany(Subcampeon::class, 'idCategoria');
    }

    public function vallasMenosVencidas()
    {
        return $this->hasMany(VallaMenosVencida::class, 'idCategoria');
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'idCategoria');
    }

    // Relación con InstanciaFinal (una categoría tiene muchas instancias finales)
    public function instanciasFinales()
    {
        return $this->hasMany(InstanciaFinal::class);
    }

    // Relación con jugadores (una categoría tiene muchos jugadores)
    public function jugadores()
    {
        return $this->hasMany(Jugador::class, 'idCategoria');
    }

    // Relación con planillaJugadores (una categoría tiene muchas planillas de jugadores)
    public function planillaJugadores()
    {
        return $this->hasMany(PlanillaJugador::class, 'idCategoria');
    }
}
