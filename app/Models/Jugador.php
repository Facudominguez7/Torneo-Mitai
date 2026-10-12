<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    use HasFactory;

    protected $table = 'jugadores';

    public $timestamps = false;

    // La PK real es el DNI
    protected $primaryKey = 'dni';

    public $incrementing = false; // DNI no es autoincremental

    protected $keyType = 'string'; // suele almacenarse como CHAR/VARCHAR(8)

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'partidos_totales',
        'goles_totales',
        'tarjetas_rojas_totales',
    ];

    public function planillas()
    {
        return $this->hasMany(PlanillaJugador::class, 'dni_jugador', 'dni');
    }
}
