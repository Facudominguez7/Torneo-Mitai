<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Copa\StoreRequest;
use App\Models\Copa;
use App\Models\Edicion;
use Illuminate\Http\Request;

class ControladorCopa extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $copas = Copa::all();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        return view('Panel.copa.index', compact('ediciones', 'EdicionSeleccionada', 'copas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $copa = new Copa;
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        return view('Panel.copa.create', compact('ediciones', 'copa', 'EdicionSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Copa::create($data);

        return to_route('copa.index', ['idEdicion' => $request->idEdicion])->with('status', 'Copa Creada');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Copa $copa)
    {
        $ediciones = Edicion::all();

        return view('Panel.copa.show', compact('ediciones'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Copa $copa)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        return view('Panel.copa.edit', compact('ediciones', 'copa', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Copa $copa)
    {
        $data = $request->validated();
        $copa->update($data);
        $idEdicion = $request->idEdicion;

        return to_route('copa.index', ['idEdicion' => $idEdicion]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Copa $copa)
    {
        $copa->delete();

        return to_route('copa.index', ['idEdicion' => $request->idEdicion])->with('status', 'Copa Eliminada');
    }
}
