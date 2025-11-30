<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    use HasFactory;

    // Tabla asociada al modelo
    protected $table = 'fases';

    // Definir los campos que pueden ser asignados masivamente
    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación uno a muchos con la tabla instancias_finales.
     */
    public function instanciasFinales()
    {
        return $this->hasMany(InstanciaFinal::class, 'idFase');
    }
}
