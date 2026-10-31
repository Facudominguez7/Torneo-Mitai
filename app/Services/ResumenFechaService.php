<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fecha;
use App\Models\Partido;
use Carbon\Carbon;

/**
 * Arma el resumen de resultados de una fecha de liga de la última edición: un único correo
 * con todos los marcadores cargados, en lugar de un aviso por partido.
 */
class ResumenFechaService
{
    /**
     * El resumen siempre corresponde a la edición más reciente, aunque la interfaz mande otra.
     */
    public function idUltimaEdicion(): ?int
    {
        $idEdicion = Edicion::query()->orderByDesc('id')->value('id');

        return $idEdicion !== null ? (int) $idEdicion : null;
    }

    /**
     * @return array<string, mixed>|null null si la fecha no es de esa edición o no tiene resultados cargados
     */
    public function armar(int $idEdicion, int $idFecha, ?int $idCategoria = null): ?array
    {
        $fecha = Fecha::query()
            ->where('idEdicion', $idEdicion)
            ->whereKey($idFecha)
            ->first();

        if (! $fecha) {
            return null;
        }

        $idCategoriaFinal = $idCategoria ?: (int) $fecha->idCategoria;

        $consulta = Partido::query()
            ->with(['equipoLocal:id,nombre', 'equipoVisitante:id,nombre', 'grupo:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->where('idFechas', $idFecha)
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante');

        if ($idCategoriaFinal > 0) {
            $consulta->where('idCategoria', $idCategoriaFinal);
        }

        $partidos = $consulta
            ->orderBy('idGrupo')
            ->orderBy('horario_datetime')
            ->orderBy('id')
            ->get();

        if ($partidos->isEmpty()) {
            return null;
        }

        $edicionNombre = Edicion::query()
            ->whereKey($idEdicion)
            ->value('nombre') ?? 'Edición vigente';

        $categoriaNombre = null;
        if ($idCategoriaFinal > 0) {
            $categoriaNombre = Categoria::query()
                ->whereKey($idCategoriaFinal)
                ->value('nombreCategoria');
        }

        return [
            'id_edicion' => $idEdicion,
            'id_fecha' => $idFecha,
            'id_categoria' => $idCategoriaFinal > 0 ? $idCategoriaFinal : null,
            'edicion_nombre' => $edicionNombre,
            'fecha_nombre' => $fecha->nombre,
            'categoria_nombre' => $categoriaNombre,
            'url_fixture' => '/fixture?idEdicion='.$idEdicion,
            'partidos_count' => $partidos->count(),
            'partidos' => $partidos->map(fn (Partido $partido): array => [
                'id' => $partido->id,
                'equipo_local' => $partido->equipoLocal->nombre ?? 'Local',
                'equipo_visitante' => $partido->equipoVisitante->nombre ?? 'Visitante',
                'goles_local' => (int) $partido->golesEquipoLocal,
                'goles_visitante' => (int) $partido->golesEquipoVisitante,
                'grupo' => $this->etiquetaGrupo($partido->grupo->nombre ?? null),
                'cancha' => $partido->cancha ? ('Cancha '.$partido->cancha) : null,
                'horario' => $partido->horario_datetime
                    ? Carbon::parse($partido->horario_datetime)->format('d/m H:i')
                    : ($partido->horario ?: null),
            ])->values()->all(),
        ];
    }

    /**
     * Hay ediciones legacy donde el grupo ya se llama "Grupo Único" y otras donde es solo la letra.
     */
    private function etiquetaGrupo(?string $nombreGrupo): ?string
    {
        $nombre = trim((string) $nombreGrupo);

        if ($nombre === '') {
            return null;
        }

        return preg_match('/^grupo\b/i', $nombre) === 1 ? $nombre : 'Grupo '.$nombre;
    }
}
