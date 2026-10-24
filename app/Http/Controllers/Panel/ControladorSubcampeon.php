<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subcampeon\StoreRequest;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Subcampeon;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorSubcampeon extends Controller
{
    use SeleccionarCategoriaTrait;

    /**
     * Subcampeones se fusionó con Campeones en Podio (T8b,
     * panel-admin-redesign): el listado ahora vive ahí. Se conserva la ruta
     * `subcampeon.index` (que usan `seleccionar-categoria` y los redirects de
     * store/update/destroy) redirigiendo a Podio con el mismo idEdicion.
     */
    public function index(Request $request)
    {
        return to_route('podio.index', array_filter(['idEdicion' => $request->idEdicion]));
    }

    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $subcampeon = new Subcampeon;
        $ediciones = Edicion::all();
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

        return view('Panel.subcampeon.create', compact('ediciones', 'subcampeon', 'EdicionSeleccionada', 'equipos', 'copas', 'CategoriaSeleccionada'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Subcampeon::create($data);

        return to_route('subcampeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Creado');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Subcampeon $subcampeon)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subcampeon $subcampeon)
    {
        $ediciones = Edicion::all();
        $idEdicion = $subcampeon->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $subcampeon->idCategoria;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();
        $copas = Copa::where('idEdicion', $idEdicion)->get();

        return view('Panel.subcampeon.edit', compact('ediciones', 'subcampeon', 'equipos', 'copas', 'idCategoria', 'EdicionSeleccionada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Subcampeon $subcampeon)
    {
        $data = $request->validated();
        $subcampeon->update($data);

        return to_route('subcampeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Subcampeon $subcampeon)
    {
        $subcampeon->delete();

        return to_route('subcampeon.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Eliminado');
    }
}
