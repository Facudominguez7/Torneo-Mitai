<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolArbitroReportado extends Model
{
    use HasFactory;

    protected $table = 'goles_arbitros_reportados';

    protected $fillable = [
        'partido_id',
        'partido_type',
        'idEdicion',
        'idEquipo',
        'idPlanillaJugador',
        'numero_camiseta',
        'goles_reportados',
        'es_inconsistencia',
        'motivo_inconsistencia',
    ];

    protected $casts = [
        'es_inconsistencia' => 'boolean',
    ];

    public function planillaJugador()
    {
        return $this->belongsTo(PlanillaJugador::class, 'idPlanillaJugador');
    }
}
