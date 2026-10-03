<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EquipoEdicion;
use App\Models\VallaMenosVencida;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ranking de Vallas menos vencidas (ruta nueva, T8c, panel-admin-redesign):
 * los equipos de la edición ordenados por goles en contra (mismo criterio
 * que ControladorHome::tablaVallas, la versión pública), con el arquero
 * guardado (VallaMenosVencida) cuando existe. Es de solo lectura: cargar o
 * cambiar el arquero se hace desde Premios individuales.
 */
class ControladorVallasRanking extends Controller
{
    public function index(Request $request): View
    {
        $idEdicion = $this->resolverIdEdicion($request);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->query('idCategoria');
        // Un valor mal formado (array, texto) se ignora: se muestran todas las categorías.
        $idCategoria = is_string($idCategoria) && ctype_digit($idCategoria) ? $idCategoria : null;

        $categorias = $idEdicion
            ? Categoria::where('idEdicion', $idEdicion)->orderByDesc('nombreCategoria')->get()
            : collect();

        $equipos = $idEdicion ? $this->obtenerEquipos($idEdicion, $idCategoria) : collect();
        $arquerosPorEquipo = $idEdicion ? $this->obtenerArqueros($idEdicion, $idCategoria) : collect();

        return view('Panel.vallas.index', [
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'idCategoria' => $idCategoria,
            'categorias' => $categorias,
            'equipos' => $equipos,
            'arquerosPorEquipo' => $arquerosPorEquipo,
        ]);
    }

    /**
     * @return Collection<int, EquipoEdicion>
     */
    private function obtenerEquipos(int $idEdicion, ?string $idCategoria): Collection
    {
        return EquipoEdicion::with(['equipo:id,nombre', 'categoria:id,nombreCategoria'])
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->get()
            ->sort(function (EquipoEdicion $a, EquipoEdicion $b) {
                $porGoles = ((int) $a->golesContra) <=> ((int) $b->golesContra);

                return $porGoles !== 0 ? $porGoles : strcmp($a->equipo->nombre ?? '', $b->equipo->nombre ?? '');
            })
            ->values();
    }

    /**
     * @return Collection<int, VallaMenosVencida> arqueros guardados, indexados por idEquipo
     */
    private function obtenerArqueros(int $idEdicion, ?string $idCategoria): Collection
    {
        return VallaMenosVencida::where('idEdicion', $idEdicion)
            ->when($idCategoria, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->get()
            ->keyBy('idEquipo');
    }

    /**
     * Misma resolución de contexto que ControladorPodio::resolverIdEdicion.
     */
    private function resolverIdEdicion(Request $request): ?int
    {
        $idEdicion = $request->attributes->has('panel.idEdicion')
            ? $request->attributes->get('panel.idEdicion')
            : ($request->query('idEdicion') ?: $request->session()->get('panel.idEdicion'));

        return $idEdicion ? (int) $idEdicion : null;
    }
}
