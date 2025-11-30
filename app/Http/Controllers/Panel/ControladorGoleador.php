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
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $goleadores = Goleador::join('ediciones as ed', 'goleadores.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'goleadores.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'goleadores.idCategoria', '=', 'cat.id')
            ->select('goleadores.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo')
            ->where('goleadores.idEdicion', $idEdicion)
            ->orderBy('cat.nombreCategoria', 'desc')
            ->paginate(7);
        $goleadores->appends(['idEdicion' => $idEdicion]);
        $tipo = 'goleador';
        return view('Panel.goleador.index', compact('tipo', 'ediciones', 'EdicionSeleccionada', 'goleadores'));
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
        return view('Panel.goleador.create', compact('ediciones', 'goleador', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada'));
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
