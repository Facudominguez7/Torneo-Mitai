<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campeon\StoreRequest;
use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorCampeon extends Controller
{
    use SeleccionarCategoriaTrait;

    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $campeones = Campeon::join('ediciones as ed', 'campeones.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'campeones.idEquipo', '=', 'e.id')
            ->join('copas as co', 'campeones.idCopa', '=', 'co.id')
            ->join('categorias as cat', 'campeones.idCategoria', '=', 'cat.id')
            ->select('campeones.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'co.nombre as nombreCopa', 'e.id as idEquipo')
            ->where('campeones.idEdicion', $idEdicion)
            ->orderBy('cat.nombreCategoria', 'desc')
            ->paginate(7);
        $campeones->appends(['idEdicion' => $idEdicion]);
        $tipo = 'campeon';
        return view('Panel.campeon.index', compact('ediciones', 'EdicionSeleccionada', 'campeones', 'tipo'));
    }

    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $campeon = new Campeon();
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = EquipoEdicion::join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->select('equipo_ediciones.*', 'e.nombre as nombreEquipo')
            ->get();
        $copas = Copa::orderByDesc('nombre')->get();
        return view('Panel.campeon.create', compact('ediciones', 'campeon', 'EdicionSeleccionada', 'equipos', 'copas', 'CategoriaSeleccionada'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Campeon::create($data);
        return to_route('campeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Creado');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Campeon $campeon)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        return view('Panel.campeon.show', compact('ediciones', 'campeon', 'EdicionSeleccionada'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campeon $campeon)
    {
        $ediciones = Edicion::all();
        $idEdicion = $campeon->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $campeon->idCategoria;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();
        $copas = Copa::all();
        return view('Panel.campeon.edit', compact('ediciones', 'campeon', 'equipos', 'copas', 'idCategoria', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Campeon $campeon)
    {
        $data = $request->validated();
        $campeon->update($data);
        return to_route('campeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Campeon $campeon)
    {
        $campeon->delete();
        return to_route('campeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Eliminado');
    }
}
