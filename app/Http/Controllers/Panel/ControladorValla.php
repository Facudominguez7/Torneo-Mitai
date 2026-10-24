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

    /**
     * Valla se fusionó con Goleador en Premios individuales (T8c,
     * panel-admin-redesign): el listado ahora vive ahí, junto con el
     * ranking de Vallas menos vencidas (`vallas.index`). Se conserva la ruta
     * `valla.index` (que usan `seleccionar-categoria` y los redirects de
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
        $valla = new VallaMenosVencida;
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = EquipoEdicion::join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->select('equipo_ediciones.*', 'e.nombre as nombreEquipo')
            ->get();

        // Obtener todos los equipos ordenados por goles en contra para el SELECT
        $listaEquiposVallas = collect();
        if ($idEdicion && $idCategoria) {
            $listaEquiposVallas = EquipoEdicion::join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
                ->where('equipo_ediciones.idEdicion', $idEdicion)
                ->where('equipo_ediciones.idCategoria', $idCategoria)
                ->select('equipo_ediciones.*', 'e.nombre as nombreEquipo')
                ->orderBy('equipo_ediciones.golesContra', 'asc')
                ->get();
        }

        return view('Panel.valla.create', compact('ediciones', 'valla', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada', 'listaEquiposVallas'));
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
        // return view('panel.valla.show', compact('vallaMenosVencida'));
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
    public function destroy(VallaMenosVencida $valla, Request $request)
    {
        $valla->delete();

        return to_route('valla.index', ['idEdicion' => $request->idEdicion])->with('status', 'Arquero Eliminado');
    }
}
