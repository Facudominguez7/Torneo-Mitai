<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /** Nombre y apellido se guardan con la inicial de cada palabra en mayúscula: "JUAN pablo" → "Juan Pablo". */
    public static function capitalizarNombre(?string $valor): ?string
    {
        return $valor === null ? null : Str::title(trim($valor));
    }

    protected function nombre(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => self::capitalizarNombre($valor));
    }

    protected function apellido(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => self::capitalizarNombre($valor));
    }

    public function planillas()
    {
        return $this->hasMany(PlanillaJugador::class, 'dni_jugador', 'dni');
    }
}
