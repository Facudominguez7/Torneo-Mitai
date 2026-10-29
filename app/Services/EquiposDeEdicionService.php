<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\EquipoEdicion;
use Illuminate\Support\Collection;

class EquiposDeEdicionService
{
    /**
     * Padrón de participantes de una edición. En el legado la inscripción vive
     * en dos lugares: equipos.idEdicion (equipo nativo) y equipo_ediciones
     * (equipo heredado de otra edición). La lista correcta es la unión de
     * ambas fuentes; la identidad es equipos.id, nunca el nombre visible, así
     * dos registros homónimos siguen siendo opciones distintas.
     *
     * La categoría de la edición es la de la fila pivote cuando existe; si la
     * fila pivote no trae categoría se usa la del propio equipo. La foto
     * acompaña para mostrar el escudo real en las sugerencias del buscador.
     *
     * @return Collection<int, array{id: int, nombre: string, idCategoria: int|null, nombreCategoria: string|null, foto: string|null}>
     */
    public function participantes(int $idEdicion): Collection
    {
        $inscriptosPivote = EquipoEdicion::query()
            ->where('idEdicion', $idEdicion)
            ->with([
                'equipo:id,nombre,idCategoria,foto',
                'categoria:id,nombreCategoria',
            ])
            ->get()
            ->filter(fn (EquipoEdicion $fila): bool => $fila->equipo !== null)
            ->map(fn (EquipoEdicion $fila): array => [
                'id' => (int) $fila->idEquipo,
                'nombre' => $fila->equipo->nombre,
                'idCategoria' => $fila->idCategoria !== null ? (int) $fila->idCategoria : null,
                'nombreCategoria' => $fila->categoria?->nombreCategoria,
                'foto' => $fila->equipo->foto,
            ]);

        $inscriptosDirectos = Equipo::query()
            ->where('idEdicion', $idEdicion)
            ->with('categoria:id,nombreCategoria')
            ->get(['id', 'nombre', 'idCategoria', 'foto'])
            ->map(fn (Equipo $equipo): array => [
                'id' => (int) $equipo->id,
                'nombre' => $equipo->nombre,
                'idCategoria' => $equipo->idCategoria !== null ? (int) $equipo->idCategoria : null,
                'nombreCategoria' => $equipo->categoria?->nombreCategoria,
                'foto' => $equipo->foto,
            ]);

        // Fusión por equipos.id: la fila pivote pisa a la inscripción directa y
        // una futura fila pivote duplicada no puede generar opciones repetidas.
        $participantes = $inscriptosDirectos->keyBy('id');
        $inscriptosPivote->keyBy('id')->each(
            fn (array $inscripto, int $idEquipo) => $participantes->put($idEquipo, $inscripto)
        );

        return $participantes
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
