<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Edicion\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use Illuminate\Http\Request;

class ControladorEdicion extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        return view('Panel.edicion.index', compact('ediciones', 'EdicionSeleccionada', 'idEdicion'));
    }

    public function categoriasPorEdicion($id)
    {
        // Obtener las categorías asociadas a la edición seleccionada
        $categorias = Categoria::where('idEdicion', $id)->get();

        // Si no hay categorías para la edición seleccionada, devolver un error
        if ($categorias->isEmpty()) {
            return response()->json([
                'error' => 'No se encontraron categorías para esta edición.',
            ], 404);
        }

        // Devolver las categorías en formato JSON
        return response()->json([
            'categorias' => $categorias,
        ]);
    }

    public function equiposPorCategoria($id)
    {
        // Obtener la categoría y los equipos asociados
        $categoria = Categoria::find($id);
        $equipos = Equipo::where('idCategoria', $id)->get();

        return response()->json([
            'equipos' => $equipos,
            'categoria' => $categoria ? $categoria->nombreCategoria : 'Categoría',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $edicion = new Edicion();

        return view('Panel.edicion.create', compact('edicion'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * La edición se crea sin copiar equipos/categorías desde otras ediciones.
     * La asociación de equipos históricos se realiza exclusivamente desde
     * "Agregar Equipo de una Edición Anterior" en el módulo de equipos.
     *
     * Además, guarda la cantidad configurable de jugadores por planilla,
     * que se usa como límite operativo de Lista de Buena Fe en la edición.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Edicion::create([
            'nombre' => $data['nombre'],
            'jugadores_por_planilla' => $data['jugadores_por_planilla'],
        ]);

        return to_route('edicion.index')->with('status', 'Edición creada con éxito.');
    }

    /**
     * Muestra el detalle de una edición sin perder el contexto del panel.
     *
     * Se envía también la edición seleccionada para que el layout mantenga
     * el selector superior y los enlaces con el mismo `idEdicion` activo.
     */
    public function show(Request $request, Edicion $edicion)
    {
        $ediciones = Edicion::all();
        $EdicionSeleccionada = $edicion;

        return view('Panel.edicion.show', compact('edicion', 'ediciones', 'EdicionSeleccionada'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Edicion $edicion)
    {
        $ediciones = Edicion::all();

        return view('Panel.edicion.edit', compact('ediciones', 'edicion'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Edicion $edicion)
    {
        $data = $request->validated();

        $edicion->update($data);

        return to_route('edicion.index', ['idEdicion' => $request->id]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Edicion $edicion)
    {
        $edicion->delete();

        return to_route('edicion.index', ['idEdicion' => $request->id]);
    }
}
