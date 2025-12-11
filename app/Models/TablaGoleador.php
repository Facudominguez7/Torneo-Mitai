<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TablaGoleador extends Model
{
    use HasFactory;

    protected $table = 'tabla_goleadores'; // Nombre de la tabla
    public $timestamps = true; // Habilitado para rastrear actualizaciones

    protected $fillable = [
        'idEquipo', // ID del equipo al que pertenece el goleador
        'cantidadGoles', // Número de goles anotados
        'idEdicion', // ID de la edición del torneo
        'idCategoria',
        'dni_jugador',
        // 'nombre' eliminado - se obtiene dinámicamente desde relación jugador
    ];
    // Relación con la tabla de equipos
    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'idEquipo');
    }

    // Relación con la tabla de ediciones
    public function edicion()
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    // Relación con Jugador para obtener nombre dinámicamente
    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'dni_jugador', 'dni');
    }

    /**
     * Accessor para obtener el nombre completo del jugador dinámicamente
     * Mantiene compatibilidad si aún existe columna 'nombre' en BD
     */
    public function getNombreCompletoAttribute()
    {
        if ($this->jugador) {
            return $this->jugador->apellido . ' ' . $this->jugador->nombre;
        }
        // Fallback si no hay relación (mantiene compatibilidad)
        return $this->attributes['nombre'] ?? 'Desconocido';
    }

    public function scopeSearch($query, $val)
    {
        if (!$val) {
            return $query;
        }

        return $query->whereHas('jugador', function ($q) use ($val) {
            $q->where('nombre', 'LIKE', '%' . $val . '%')
              ->orWhere('apellido', 'LIKE', '%' . $val . '%');
        });
    }
}
