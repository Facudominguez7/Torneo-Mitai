<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\VallaMenosVencida\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\VallaMenosVencida;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorValla extends Controller
{
    use SeleccionarCategoriaTrait;
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $vallas = VallaMenosVencida::join('ediciones as ed', 'vallas_menos_vencidas.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'vallas_menos_vencidas.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'vallas_menos_vencidas.idCategoria', '=', 'cat.id')
            ->select('vallas_menos_vencidas.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo')
            ->where('vallas_menos_vencidas.idEdicion', $idEdicion)
            ->orderBy('cat.nombreCategoria', 'desc')
            ->paginate(7);
        $vallas->appends(['idEdicion' => $idEdicion]);
        $tipo = 'valla';
        return view('Panel.valla.index', compact('tipo', 'ediciones', 'EdicionSeleccionada', 'vallas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $valla = new VallaMenosVencida();
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = EquipoEdicion::join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->select('equipo_ediciones.*', 'e.nombre as nombreEquipo')
            ->get();
        return view('Panel.valla.create', compact('ediciones', 'valla', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        VallaMenosVencida::create($data);
        return to_route('valla.index', ['idEdicion' => $request->idEdicion])->with('status', 'Arquero Cargado');
    }

    /**
     * Display the specified resource.
     */
    public function show(VallaMenosVencida $vallaMenosVencida)
    {
        //return view('panel.valla.show', compact('vallaMenosVencida'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VallaMenosVencida $valla)
    {
        $ediciones = Edicion::all();
        $idEdicion = $valla->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $valla->idCategoria;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();
        return view('Panel.valla.edit', compact('ediciones', 'valla', 'equipos', 'idCategoria', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, VallaMenosVencida $valla)
    {
        $data = $request->validated();
        $valla->update($data);
        return to_route('valla.index', ['idEdicion' => $request->idEdicion])->with('status', 'Arquero Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VallaMenosVencida $valla,  Request $request)
    {
        $valla->delete();
        return to_route('valla.index', ['idEdicion' => $request->idEdicion])->with('status', 'Arquero Eliminado');
    }
}
