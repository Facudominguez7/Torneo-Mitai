<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grupo\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorGrupos extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use SeleccionarCategoriaTrait;
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $grupos = Grupo::where('idEdicion', $idEdicion)->with('categoria')->orderBy('id', 'desc')->paginate(7);
        $grupos->appends(['idEdicion' => $idEdicion]);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $tipo = 'grupos';
        return view('Panel.grupo.index', compact('tipo', 'EdicionSeleccionada', 'grupos', 'ediciones'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $grupo = new Grupo();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->input('idCategoria');
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.grupo.create', compact('ediciones', 'grupo', 'EdicionSeleccionada', 'CategoriaSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $idEdicion = $data['idEdicion'];
        Grupo::create($data);
        return to_route('grupos.index', ['idEdicion' => $idEdicion])->with('status', 'Grupo Cargado');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request , Grupo $grupo)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idGrupo = $grupo->id;
        $idCategoria = $grupo->idCategoria;
        $equiposGrupo = EquipoGrupo::join('equipos', 'equipos_grupos.idEquipo', '=', 'equipos.id')
            ->join('grupos', 'equipos_grupos.idGrupo', '=', 'grupos.id')
            ->where('equipos_grupos.idGrupo', $idGrupo)
            ->select('equipos.id AS idEquipo', 'equipos.nombre AS nombreEquipo', 'equipos.foto AS fotoEquipo', 'grupos.nombre AS nombreGrupo', 'equipos_grupos.id AS idEquipoGrupo')
            ->get();
            $nombreGrupo = $equiposGrupo->first()->nombreGrupo ?? 'Sin Nombre';
        return view('Panel.grupo.show', compact('EdicionSeleccionada', 'ediciones', 'equiposGrupo', 'nombreGrupo', 'idCategoria', 'idGrupo'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Grupo $grupo)
    {
        $ediciones = Edicion::all();
        $idEdicion = $grupo->idEdicion;
        $idCategoria = $grupo->idCategoria;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.grupo.edit', compact('grupo', 'ediciones', 'EdicionSeleccionada', 'idCategoria'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Grupo $grupo)
    {
        $data = $request->validated();
        $grupo->update($data);
        return to_route('grupos.index', ['idEdicion' => $grupo->idEdicion])->with('status', 'Grupo Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Grupo $grupo)
    {
        $grupo->delete();
        return to_route('grupos.index', ['idEdicion' => $request->idEdicion])->with('status', 'Grupo Eliminado');
    }
}
