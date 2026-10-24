<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Singleton global de configuración de cobros de planillas (fila id = 1).
 *
 * La fila se inicializa por migración; el servicio falla cerrado si falta.
 *
 * @property int $id
 * @property int $tarifa_ars Tarifa vigente en ARS enteros (campo INT firmado).
 * @property Carbon|null $activado_at
 */
class ConfiguracionCobroPlanilla extends Model
{
    protected $fillable = ['tarifa_ars', 'activado_at'];

    /**
     * Obtener los atributos que deben ser casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarifa_ars' => 'integer',
            'activado_at' => 'datetime',
        ];
    }
}
