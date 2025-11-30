<?php

namespace App\Http\Controllers\Panel;

use App\Models\Fecha;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fecha\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use Illuminate\Http\Request;

class ControladorFecha extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $fechas = Fecha::where('idEdicion', $idEdicion)->with('categoria')->orderBy('id', 'desc')->paginate(7);
        $fechas->appends(['idEdicion' => $idEdicion]);
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.fecha.index', compact('ediciones', 'EdicionSeleccionada', 'fechas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $fecha = new Fecha();
        return view('Panel.fecha.create', compact('ediciones', 'EdicionSeleccionada', 'fecha'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $idEdicion = $data['idEdicion'];
        $nombre = $data['nombre'];

        // Crea un registro en la tabla fechas
        Fecha::create([
            'nombre' => $nombre,
            'idEdicion' => $idEdicion,
        ]);

        return to_route('fecha.index', ['idEdicion' => $idEdicion])
            ->with('status', 'Fecha creada.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fecha $fecha)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fecha $fecha)
    {
        $ediciones = Edicion::all();
        $idEdicion = $fecha->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.fecha.edit', compact('ediciones', 'fecha', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Fecha $fecha)
    {
        $data = $request->validated();
        $fecha->update($data);
        return to_route('fecha.index', ['idEdicion' => $data['idEdicion']])->with('status', 'Fecha Actualizada');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Fecha $fecha)
    {
        $fecha->delete();
        return to_route('fecha.index', ['idEdicion' => $request->idEdicion])->with('status', 'Fecha Eliminada');
    }
}
