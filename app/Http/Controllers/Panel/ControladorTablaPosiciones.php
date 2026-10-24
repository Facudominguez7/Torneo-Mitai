<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TablaPosicion\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ControladorTablaPosiciones extends Controller
{
    public function index(Request $request): View
    {
        $contexto = $this->resolverContexto($request);

        $tablaPosiciones = TablaPosicion::with(['grupo.categoria', 'equipo', 'edicion'])
            ->when($contexto['idEdicion'], fn ($query) => $query->where('idEdicion', $contexto['idEdicion']))
            ->when($contexto['idCategoria'], fn ($query) => $query->whereHas('grupo', fn ($grupoQuery) => $grupoQuery->where('idCategoria', $contexto['idCategoria'])))
            ->when($contexto['idGrupo'], fn ($query) => $query->where('idGrupo', $contexto['idGrupo']))
            ->orderBy('idGrupo')
            ->orderByDesc('puntos')
            ->orderByDesc('diferenciaGoles')
            ->orderByDesc('golesFavor')
            ->orderBy('idEquipo')
            ->get();

        // Goles en contra generales por equipo: viven en equipo_ediciones (acumulado de la edicion),
        // no en tabla_posiciones (que es por grupo). Se indexan por idEquipo-idCategoria.
        $golesContraGenerales = EquipoEdicion::query()
            ->when($contexto['idEdicion'], fn ($query) => $query->where('idEdicion', $contexto['idEdicion']))
            ->get()
            ->keyBy(fn ($registro) => $registro->idEquipo.'-'.$registro->idCategoria);

        $tablaPosiciones->each(function ($fila) use ($golesContraGenerales) {
            $clave = $fila->idEquipo.'-'.$fila->grupo?->idCategoria;
            $fila->golesContraGeneral = $golesContraGenerales->get($clave)?->golesContra;
        });

        $tablaPosicionesPorGrupo = $tablaPosiciones->groupBy('idGrupo');

        return view('Panel.tabla_posicion.index', array_merge($contexto, compact('tablaPosiciones', 'tablaPosicionesPorGrupo')));
    }

    public function create(Request $request): View
    {
        $contexto = $this->resolverContexto($request);

        $tablaPosicion = new TablaPosicion([
            'idEdicion' => $contexto['idEdicion'],
            'idGrupo' => $contexto['idGrupo'],
        ]);

        return view('Panel.tabla_posicion.create', array_merge($contexto, compact('tablaPosicion')));
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $data['idEdicion'],
            'idCategoria' => $data['idCategoria'] ?? $request->input('idCategoria'),
            'idGrupo' => $data['idGrupo'] ?? $request->input('idGrupo'),
        ]);

        unset($data['idCategoria']);

        TablaPosicion::create($data);

        return to_route('tabla_posicion.index', $parametrosRegreso)
            ->with('status', 'Posición creada');
    }

    public function edit(Request $request, TablaPosicion $tabla_posicion): View
    {
        $tabla_posicion->loadMissing('grupo.categoria', 'equipo', 'edicion');
        $contexto = $this->resolverContexto($request, $tabla_posicion);
        $tablaPosicion = $tabla_posicion;

        return view('Panel.tabla_posicion.edit', array_merge($contexto, compact('tablaPosicion')));
    }

    public function update(StoreRequest $request, TablaPosicion $tabla_posicion): RedirectResponse
    {
        $data = $request->validated();
        $tabla_posicion->update(array_diff_key($data, ['idCategoria' => true]));

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $tabla_posicion->idEdicion,
            'idCategoria' => $request->input('idCategoria', $tabla_posicion->grupo?->idCategoria),
            'idGrupo' => $request->input('idGrupo', $tabla_posicion->idGrupo),
        ]);

        return to_route('tabla_posicion.index', $parametrosRegreso)
            ->with('status', 'Posición actualizada');
    }

    public function destroy(Request $request, TablaPosicion $tabla_posicion): RedirectResponse
    {
        $tabla_posicion->loadMissing('grupo');

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $request->integer('idEdicion', $tabla_posicion->idEdicion),
            'idCategoria' => $request->integer('idCategoria', $tabla_posicion->grupo?->idCategoria),
            'idGrupo' => $request->integer('idGrupo', $tabla_posicion->idGrupo),
        ]);

        $tabla_posicion->delete();

        return to_route('tabla_posicion.index', $parametrosRegreso)
            ->with('status', 'Posición eliminada');
    }

    /**
     * @return array{
     *     ediciones:Collection,
     *     EdicionSeleccionada:?Edicion,
     *     CategoriaSeleccionada:?Categoria,
     *     idEdicion:int,
     *     idCategoria:?int,
     *     idGrupo:?int,
     *     categorias:Collection,
     *     grupos:Collection,
     *     equipos:Collection,
     *     parametrosRegreso:array<string, mixed>
     * }
     */
    private function resolverContexto(Request $request, ?TablaPosicion $tablaPosicion = null): array
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->integer('idEdicion', $tablaPosicion?->idEdicion ?? (int) (Edicion::query()->latest('id')->value('id') ?? 0));
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $categorias = collect();
        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->orderByDesc('id')
                ->get();
        }

        $idCategoriaSolicitada = $request->integer('idCategoria', $tablaPosicion?->grupo?->idCategoria);
        $idCategoria = $idCategoriaSolicitada;

        if ($categorias->isNotEmpty()) {
            if (! $idCategoria || ! $categorias->contains('id', $idCategoria)) {
                $idCategoria = (int) $categorias->first()->id;
            }
        } else {
            $idCategoria = null;
        }

        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        $grupos = collect();
        $equipos = collect();
        if ($EdicionSeleccionada) {
            $grupos = Grupo::where('idEdicion', $idEdicion)
                ->when($idCategoria, fn ($query) => $query->where('idCategoria', $idCategoria))
                ->with('categoria:id,nombreCategoria')
                ->orderBy('nombre')
                ->get();

            $equipos = Equipo::join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
                ->where('equipo_ediciones.idEdicion', $idEdicion)
                ->when($idCategoria, fn ($query) => $query->where('equipo_ediciones.idCategoria', $idCategoria))
                ->select('equipos.*')
                ->orderBy('equipos.nombre')
                ->get();
        }

        $idGrupo = $request->integer('idGrupo', $tablaPosicion?->idGrupo);
        if ($idGrupo && ! $grupos->contains('id', $idGrupo)) {
            $idGrupo = null;
        }

        return [
            'ediciones' => $ediciones,
            'EdicionSeleccionada' => $EdicionSeleccionada,
            'CategoriaSeleccionada' => $CategoriaSeleccionada,
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $idGrupo,
            'categorias' => $categorias,
            'grupos' => $grupos,
            'equipos' => $equipos,
            'parametrosRegreso' => $this->construirParametrosRegreso($request, [
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idGrupo' => $idGrupo,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function construirParametrosRegreso(Request $request, array $sobrescrituras = []): array
    {
        return array_filter(array_merge([
            'idEdicion' => $request->input('idEdicion'),
            'idCategoria' => $request->input('idCategoria'),
            'idGrupo' => $request->input('idGrupo'),
        ], $sobrescrituras), static fn ($valor) => $valor !== null && $valor !== '');
    }
}
