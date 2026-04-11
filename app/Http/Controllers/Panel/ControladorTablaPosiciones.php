<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TablaPosicion\StoreRequest;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use Illuminate\Http\Request;

class ControladorTablaPosiciones extends Controller
{
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = (int) $request->query('idEdicion', Edicion::query()->latest('id')->value('id') ?? 0);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idGrupo = $request->query('idGrupo');

        $grupos = collect();
        $equipos = collect();

        if ($EdicionSeleccionada) {
            $grupos = Grupo::where('idEdicion', $idEdicion)
                ->orderBy('nombre')
                ->get();

            $equipos = Equipo::where('idEdicion', $idEdicion)
                ->orderBy('nombre')
                ->get();
        }

        $tablaPosiciones = TablaPosicion::with(['grupo', 'equipo', 'edicion'])
            ->when($idEdicion, fn ($query) => $query->where('idEdicion', $idEdicion))
            ->when($idGrupo, fn ($query) => $query->where('idGrupo', $idGrupo))
            ->orderByDesc('puntos')
            ->orderByDesc('diferenciaGoles')
            ->orderByDesc('golesFavor')
            ->orderBy('idGrupo')
            ->orderBy('idEquipo')
            ->paginate(20);

        $tablaPosiciones->appends(array_filter([
            'idEdicion' => $idEdicion ?: null,
            'idGrupo' => $idGrupo,
        ], static fn ($value) => $value !== null && $value !== ''));

        return view('Panel.tabla_posicion.index', compact(
            'ediciones',
            'EdicionSeleccionada',
            'idEdicion',
            'idGrupo',
            'grupos',
            'equipos',
            'tablaPosiciones'
        ));
    }

    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = (int) $request->query('idEdicion', Edicion::query()->latest('id')->value('id') ?? 0);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $tablaPosicion = new TablaPosicion([
            'idEdicion' => $idEdicion,
        ]);

        $grupos = collect();
        $equipos = collect();

        if ($EdicionSeleccionada) {
            $grupos = Grupo::where('idEdicion', $idEdicion)->orderBy('nombre')->get();
            $equipos = Equipo::where('idEdicion', $idEdicion)->orderBy('nombre')->get();
        }

        return view('Panel.tabla_posicion.create', compact(
            'ediciones',
            'EdicionSeleccionada',
            'tablaPosicion',
            'grupos',
            'equipos'
        ));
    }

    public function store(StoreRequest $request)
    {
        $tablaPosicion = TablaPosicion::create($request->validated());

        return to_route('tabla_posicion.index', ['idEdicion' => $tablaPosicion->idEdicion])
            ->with('status', 'Posición creada');
    }

    public function edit(TablaPosicion $tabla_posicion)
    {
        $ediciones = Edicion::all();
        $idEdicion = $tabla_posicion->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $tablaPosicion = $tabla_posicion;
        $grupos = Grupo::where('idEdicion', $idEdicion)->orderBy('nombre')->get();
        $equipos = Equipo::where('idEdicion', $idEdicion)->orderBy('nombre')->get();

        return view('Panel.tabla_posicion.edit', compact(
            'ediciones',
            'EdicionSeleccionada',
            'tablaPosicion',
            'grupos',
            'equipos'
        ));
    }

    public function update(StoreRequest $request, TablaPosicion $tabla_posicion)
    {
        $tabla_posicion->update($request->validated());

        return to_route('tabla_posicion.index', ['idEdicion' => $tabla_posicion->idEdicion])
            ->with('status', 'Posición actualizada');
    }

    public function destroy(Request $request, TablaPosicion $tabla_posicion)
    {
        $idEdicion = $request->integer('idEdicion', $tabla_posicion->idEdicion);
        $tabla_posicion->delete();

        return to_route('tabla_posicion.index', ['idEdicion' => $idEdicion])
            ->with('status', 'Posición eliminada');
    }
}
