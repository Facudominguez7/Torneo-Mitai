<?php

namespace App\Services\Panel;

use App\Models\Categoria;
use App\Models\EquipoEdicion;
use App\Models\Goleador;
use App\Models\TablaGoleador;
use App\Models\VallaMenosVencida;
use Illuminate\Support\Collection;

/**
 * Arma Premios individuales (goleador y valla fusionados, T8c,
 * panel-admin-redesign): por categoría, muestra lo ya guardado (todos los
 * registros, aunque haya duplicados, para que ninguno quede inalcanzable) y,
 * solo cuando todavía no hay nada cargado, una lista de sugerencias para
 * elegir y guardar. Nunca guarda nada — ControladorPremios::guardarGoleador
 * y ::guardarValla siempre vuelven a resolver el candidato elegido contra la
 * base (ConfirmadorPremios), nunca confían en lo que este servicio calculó.
 */
class SugerenciaPremios
{
    /**
     * Tope de candidatos por select, para no volcar categorías enteras (con
     * 30+ jugadores u equipos) en un único <select>: alcanza para mostrar
     * cómodamente el podio de goleadores/vallas de una categoría típica sin
     * necesitar búsqueda ni paginación en el propio select.
     */
    private const TOPE_SUGERENCIAS = 10;

    /**
     * @return array{categorias: Collection<int, array<string, mixed>>, tieneContenido: bool}
     */
    public function obtenerPremios(int $idEdicion): array
    {
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderByDesc('nombreCategoria')
            ->get()
            ->map(fn (Categoria $categoria) => $this->construirCategoria($idEdicion, $categoria))
            ->filter(fn (array $categoria) => $categoria['goleador']['tieneAlgo'] || $categoria['valla']['tieneAlgo'])
            ->values();

        return [
            'categorias' => $categorias,
            'tieneContenido' => $categorias->isNotEmpty(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function construirCategoria(int $idEdicion, Categoria $categoria): array
    {
        return [
            'idCategoria' => $categoria->id,
            'nombreCategoria' => $categoria->nombreCategoria,
            'goleador' => $this->construirGoleador($idEdicion, $categoria->id),
            'valla' => $this->construirValla($idEdicion, $categoria->id),
        ];
    }

    /**
     * @return array{guardados: Collection<int, Goleador>, sugerencias: Collection<int, array<string, mixed>>, tieneAlgo: bool}
     */
    private function construirGoleador(int $idEdicion, int $idCategoria): array
    {
        $guardados = Goleador::with('equipo:id,nombre')
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();

        $sugerencias = $guardados->isEmpty()
            ? $this->sugerenciasGoleador($idEdicion, $idCategoria)
            : collect();

        return [
            'guardados' => $guardados,
            'sugerencias' => $sugerencias,
            'tieneAlgo' => $guardados->isNotEmpty() || $sugerencias->isNotEmpty(),
        ];
    }

    /**
     * @return array{guardados: Collection<int, VallaMenosVencida>, sugerencias: Collection<int, array<string, mixed>>, tieneAlgo: bool}
     */
    private function construirValla(int $idEdicion, int $idCategoria): array
    {
        $guardados = VallaMenosVencida::with('equipo:id,nombre')
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();

        $sugerencias = $guardados->isEmpty()
            ? $this->sugerenciasValla($idEdicion, $idCategoria)
            : collect();

        return [
            'guardados' => $guardados,
            'sugerencias' => $sugerencias,
            'tieneAlgo' => $guardados->isNotEmpty() || $sugerencias->isNotEmpty(),
        ];
    }

    /**
     * Candidatos a goleador: la tabla de goleadores de la categoría,
     * ordenada por goles (de más a menos) y, a igual cantidad, por nombre
     * completo del jugador. El primero queda preseleccionado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sugerenciasGoleador(int $idEdicion, int $idCategoria): Collection
    {
        return TablaGoleador::with(['jugador', 'equipo:id,nombre'])
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get()
            ->sort(fn (TablaGoleador $a, TablaGoleador $b) => $this->compararGoleadores($a, $b))
            ->values()
            ->take(self::TOPE_SUGERENCIAS)
            ->values()
            ->map(function (TablaGoleador $fila, int $indice) {
                $nombre = $fila->nombre_completo;
                $goles = (int) $fila->cantidadGoles;
                $esSugerida = $indice === 0;

                return [
                    'dni_jugador' => (string) $fila->dni_jugador,
                    'idEquipo' => (int) $fila->idEquipo,
                    'nombreEquipo' => $fila->equipo->nombre ?? 'Equipo sin nombre',
                    'nombreJugador' => $nombre,
                    'goles' => $goles,
                    'esSugerida' => $esSugerida,
                    'label' => $esSugerida
                        ? "Sugerido: {$nombre} ({$goles} goles)"
                        : "{$nombre} ({$goles} goles)",
                ];
            });
    }

    private function compararGoleadores(TablaGoleador $a, TablaGoleador $b): int
    {
        $porGoles = ((int) $b->cantidadGoles) <=> ((int) $a->cantidadGoles);

        return $porGoles !== 0 ? $porGoles : strcmp($a->nombre_completo, $b->nombre_completo);
    }

    /**
     * Candidatos a valla menos vencida: los equipos de la categoría en esta
     * edición, ordenados por goles en contra (de menos a más) y, a igual
     * cantidad, por nombre del equipo. El primero queda preseleccionado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sugerenciasValla(int $idEdicion, int $idCategoria): Collection
    {
        return EquipoEdicion::with('equipo:id,nombre')
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get()
            ->sort(fn (EquipoEdicion $a, EquipoEdicion $b) => $this->compararVallas($a, $b))
            ->values()
            ->take(self::TOPE_SUGERENCIAS)
            ->values()
            ->map(function (EquipoEdicion $fila, int $indice) {
                $nombreEquipo = $fila->equipo->nombre ?? 'Equipo sin nombre';
                $golesContra = (int) $fila->golesContra;
                $esSugerida = $indice === 0;

                return [
                    'idEquipo' => (int) $fila->idEquipo,
                    'nombreEquipo' => $nombreEquipo,
                    'golesContra' => $golesContra,
                    'esSugerida' => $esSugerida,
                    'label' => $esSugerida
                        ? "Sugerido: {$nombreEquipo} ({$golesContra} en contra)"
                        : "{$nombreEquipo} ({$golesContra} en contra)",
                ];
            });
    }

    private function compararVallas(EquipoEdicion $a, EquipoEdicion $b): int
    {
        $porGoles = ((int) $a->golesContra) <=> ((int) $b->golesContra);
        $nombreA = $a->equipo->nombre ?? '';
        $nombreB = $b->equipo->nombre ?? '';

        return $porGoles !== 0 ? $porGoles : strcmp($nombreA, $nombreB);
    }
}
