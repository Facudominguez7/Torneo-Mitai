<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrazabilidadBajaJugador extends Model
{
    use HasFactory;

    protected $table = 'trazabilidad_bajas_jugadores';

    protected $fillable = [
        'lista_buena_fe_id',
        'dni_jugador',
        'idEquipo',
        'idEdicion',
        'idCategoria',
        'nombre_jugador',
        'apellido_jugador',
        'equipo_nombre',
        'categoria_nombre',
        'edicion_nombre',
        'fecha_alta_lista',
        'fecha_baja',
        'motivo_baja',
        'usuario_baja',
        'total_goles',
        'total_partidos',
        'total_tarjetas_rojas',
        'cantidad_planillas_archivadas',
        'historial_planillas',
    ];

    protected $casts = [
        'fecha_alta_lista' => 'datetime',
        'fecha_baja' => 'datetime',
        'historial_planillas' => 'array',
    ];

    public function listaBuenaFe()
    {
        return $this->belongsTo(ListaBuenaFe::class, 'lista_buena_fe_id');
    }
}
