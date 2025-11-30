<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Edicion\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Grupo;
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
                'error' => 'No se encontraron categorías para esta edición.'
            ], 404);
        }

        // Devolver las categorías en formato JSON
        return response()->json([
            'categorias' => $categorias
        ]);
    }

    public function equiposPorCategoria($id)
    {
        // Obtener la categoría y los equipos asociados
        $categoria = Categoria::find($id);
        $equipos = Equipo::where('idCategoria', $id)->get();

        return response()->json([
            'equipos' => $equipos,
            'categoria' => $categoria ? $categoria->nombreCategoria : 'Categoría'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Obtener las 3 últimas ediciones ordenadas por ID descendente
        $ediciones = Edicion::orderBy('id', 'desc')->take(3)->get();
        $edicion = new Edicion();

        // Inicializar variables
        $categorias = collect();
        $equipos = collect();

        // Si el formulario se envió con una edición seleccionada, cargar categorías y equipos
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');

        // Obtener categorías de la edición seleccionada
        if ($idEdicion) {
            $categorias = Categoria::where('idEdicion', $idEdicion)->get();
        }

        // Obtener equipos de la categoría seleccionada
        if ($idCategoria) {
            $equipos = Equipo::where('idCategoria', $idCategoria)->get();
        }

        return view('Panel.edicion.create', compact('ediciones', 'edicion', 'categorias', 'equipos', 'idEdicion', 'idCategoria'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Crear la nueva edición
        $edicion = Edicion::create([
            'nombre' => $data['nombre'],
        ]);

        // Mapa para relacionar categorías existentes o nuevas
        $categoriasMap = [];

        // Usar un array para evitar duplicados
        $uniqueEquipos = array_unique($data['equipos']);

        foreach ($uniqueEquipos as $idEquipo) {
            $equipo = Equipo::find($idEquipo);

            // Verificar si la categoría del equipo ya está creada en la nueva edición
            $categoria = Categoria::where('nombreCategoria', $equipo->categoria->nombreCategoria)
                ->where('idEdicion', $edicion->id)
                ->first();

            // Si no existe, crearla
            if (!$categoria) {
                $categoria = Categoria::create([
                    'nombreCategoria' => $equipo->categoria->nombreCategoria,
                    'idEdicion' => $edicion->id,
                ]);
            }

            // Guardar la categoría en el mapa
            $categoriasMap[$equipo->categoria->id] = $categoria->id;

            // Crear la relación en la tabla `equipos_ediciones`
            $edicion->equiposEdiciones()->create([
                'idEquipo' => $idEquipo,
                'idCategoria' => $categoria->id,
                'golesContra' => 0, // Inicializar goles en contra
            ]);
        }

        // Crear grupos si se definieron en el wizard
        if ($request->has('grupos')) {
            foreach ($request->input('grupos') as $nombreCategoria => $nombresGrupos) {
                if (empty($nombresGrupos)) continue;

                // Buscar la categoría por nombre en la nueva edición
                // Nota: Las categorías ya fueron creadas en el bucle anterior
                $categoriaNueva = Categoria::where('nombreCategoria', $nombreCategoria)
                    ->where('idEdicion', $edicion->id)
                    ->first();

                if ($categoriaNueva) {
                    $listaGrupos = array_map('trim', explode(',', $nombresGrupos));
                    foreach ($listaGrupos as $nombreGrupo) {
                        if (empty($nombreGrupo)) continue;

                        Grupo::create([
                            'nombre' => $nombreGrupo,
                            'idCategoria' => $categoriaNueva->id,
                            'idEdicion' => $edicion->id,
                        ]);
                    }
                }
            }
        }

        return to_route('edicion.index')->with('status', 'Edición creada con éxito.');
    }



    /**
     * Display the specified resource.
     */
    public function show(Request $request, Edicion $edicion)
    {
        $ediciones = Edicion::all();
        return view('Panel.edicion.show', compact('ediciones'));
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
