<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fase\StoreRequest;
use App\Models\Edicion;
use Illuminate\Http\Request;
use App\Models\Fase;


class ControladorFase extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $fases = Fase::all();
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.fase.index', compact('EdicionSeleccionada', 'fases', 'ediciones'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $fase = new Fase();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.fase.create', compact('ediciones', 'fase', 'EdicionSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Fase::create($data);
        return to_route('fase.index',['idEdicion' => $request->idEdicion])->with('status', 'Fase Creada');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Fase $fase)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.fase.edit', compact('ediciones', 'fase', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Fase $fase)
    {
        $data = $request->validated();
        $fase->update($data);
        $idEdicion = $request->idEdicion;
        return to_route('fase.index', ['idEdicion' => $idEdicion]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Fase $fase)
    {
        $fase->delete();
        return to_route('fase.index', ['idEdicion' => $request->idEdicion]);
    }
}