<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Goleador\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Goleador;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorGoleador extends Controller
{
    use SeleccionarCategoriaTrait;
    /**
     * Goleador se fusionó con Valla en Premios individuales (T8c,
     * panel-admin-redesign): el listado ahora vive ahí. Se conserva la ruta
     * `goleador.index` (que usan `seleccionar-categoria` y los redirects de
     * store/update/destroy) redirigiendo a Premios con el mismo idEdicion.
     */
    public function index(Request $request)
    {
        return to_route('premios.index', array_filter(['idEdicion' => $request->idEdicion]));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $goleador = new Goleador();
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = EquipoEdicion::join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->select('equipo_ediciones.*', 'e.nombre as nombreEquipo')
            ->get();

        // Obtener todos los goleadores ordenados por cantidad de goles para el SELECT
        $listaGoleadores = collect();
        if ($idEdicion && $idCategoria) {
            $listaGoleadores = \App\Models\TablaGoleador::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->with(['jugador', 'equipo'])
                ->orderByDesc('cantidadGoles')
                ->get();
        }

        return view('Panel.goleador.create', compact('ediciones', 'goleador', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada', 'listaGoleadores'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Goleador::create($data);
        return to_route('goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Goleador Cargado');
    }

    /**
     * Display the specified resource.
     */
    public function show(Goleador $goleador)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Goleador $goleador)
    {
        $ediciones = Edicion::all();
        $idEdicion = $goleador->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $goleador->idCategoria;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();
        return view('Panel.goleador.edit', compact('ediciones', 'goleador', 'equipos', 'idCategoria', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Goleador $goleador)
    {
        $data = $request->validated();
        $goleador->update($data);
        return to_route('goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Goleador Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Goleador $goleador, Request $request)
    {
        $goleador->delete();
        return to_route('goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Goleador Eliminado');
    }
}
