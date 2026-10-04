<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un equipo que toma el lugar de otro a mitad de torneo,
 * en una edición, categoría y grupo determinados.
 */
class ReemplazoEquipo extends Model
{
    protected $table = 'reemplazos_equipos';

    protected $fillable = [
        'idEdicion',
        'idCategoria',
        'idGrupo',
        'idEquipoReemplazado',
        'idEquipoReemplazante',
    ];

    /**
     * Ids del equipo y de todos los equipos vinculados a él por reemplazos
     * (en ambos sentidos y encadenados) dentro de una edición y categoría.
     *
     * @return list<int>
     */
    public static function equiposEquivalentes(int $idEquipo, int $idEdicion, int $idCategoria): array
    {
        $vinculos = static::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get(['idEquipoReemplazado', 'idEquipoReemplazante']);

        $equivalentes = [$idEquipo];
        $huboCambios = true;

        while ($huboCambios) {
            $huboCambios = false;

            foreach ($vinculos as $vinculo) {
                $reemplazado = (int) $vinculo->idEquipoReemplazado;
                $reemplazante = (int) $vinculo->idEquipoReemplazante;
                $tieneReemplazado = in_array($reemplazado, $equivalentes, true);
                $tieneReemplazante = in_array($reemplazante, $equivalentes, true);

                if ($tieneReemplazado !== $tieneReemplazante) {
                    $equivalentes[] = $tieneReemplazado ? $reemplazante : $reemplazado;
                    $huboCambios = true;
                }
            }
        }

        return $equivalentes;
    }

    public function equipoReemplazado(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'idEquipoReemplazado');
    }

    public function equipoReemplazante(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'idEquipoReemplazante');
    }

    public function edicion(): BelongsTo
    {
        return $this->belongsTo(Edicion::class, 'idEdicion');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'idCategoria');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'idGrupo');
    }
}
