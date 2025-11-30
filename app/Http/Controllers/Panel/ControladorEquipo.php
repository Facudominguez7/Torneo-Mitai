<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipo\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ControladorEquipo extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = (int) $request->idEdicion;
        $idCategoria = $request->idCategoria; // Capturar el filtro de categoría

        $query = Equipo::join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
            ->join('categorias', 'equipos.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->orderBy('equipo_ediciones.golesContra', 'asc')
            ->select('equipos.*', 'categorias.nombreCategoria', 'equipo_ediciones.golesContra as goles_en_contra')
            ->where('equipo_ediciones.idEdicion', $idEdicion);

        if ($idCategoria) {
            $query->where('equipo_ediciones.idCategoria', $idCategoria); // Filtrar por categoría si está presente
        }

        $equipos = $query->paginate(20);
        $equipos->appends(['idEdicion' => $idEdicion, 'idCategoria' => $idCategoria]);

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categorias = Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get(); // Obtener categorías de la edición seleccionada

        return view('Panel.equipo.index', compact('EdicionSeleccionada', 'ediciones', 'equipos', 'idEdicion', 'categorias', 'idCategoria'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $equipo = new Equipo();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderBy('nombreCategoria', 'desc')
            ->pluck('nombreCategoria', 'id');

        return view('Panel.equipo.create', compact('ediciones', 'categorias', 'EdicionSeleccionada',  'equipo', 'idEdicion'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $filename = time() . '.' . $request->file('foto')->getClientOriginalExtension();
            $request->file('foto')->move(public_path('../public_html/fotos/equipos'), $filename);
            $data['foto'] = $filename; // Solo guarda el nombre del archivo con su extensión
        }
        $equipo = Equipo::create($data);

        // Crear registro en EquipoEdicion
        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $request->idEdicion,
            'idCategoria' => $request->idCategoria,
            'golesContra' => 0 // o cualquier valor predeterminado que desees
        ]);

        return to_route('equipo.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Creado');
    }
    /**
     * Display the specified resource.
     */
    public function show(Request $request, Equipo $equipo)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        // Filtrar equipos por categoría si se selecciona una
        $equipos = EquipoEdicion::where('idCategoria', $idCategoria)
            ->whereHas('ediciones', function ($query) use ($idEdicion) {
                $query->where('idEdicion', $idEdicion);
            })
            ->get();

        $categorias = Categoria::where('idEdicion', $idEdicion)->get();

        return view('Panel.equipo.show', compact('equipo', 'ediciones', 'idEdicion', 'EdicionSeleccionada', 'categorias', 'equipos', 'idCategoria'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Equipo $equipo)
    {
        $ediciones = Edicion::all();
        $idEdicion = $equipo->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderBy('nombreCategoria', 'desc')
            ->pluck('nombreCategoria', 'id');

        return view('Panel.equipo.edit', compact('ediciones', 'categorias', 'equipo', 'EdicionSeleccionada', 'idEdicion'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Equipo $equipo)
    {
        $data = $request->validated();

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $filename = time() . '.' . $request->file('foto')->getClientOriginalExtension();
            $request->file('foto')->move(public_path('../public_html/fotos/equipos'), $filename);
            $data['foto'] = $filename; // Solo guarda el nombre del archivo con su extensión
        }

        $equipo->update($data);
        return to_route('equipo.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Equipo $equipo)
    {
        $idEdicion = $request->input('idEdicion');
        //dd($equipo);
        $equipoedicion = EquipoEdicion::where('idEdicion', $idEdicion)
            ->where('idEquipo', $equipo->id)
            ->first();
        if ($equipoedicion) {
            $equipoedicion->delete();
        }
        return to_route('equipo.index', ['idEdicion' => $request->idEdicion])->with('status', 'Equipo Eliminado');
    }
}
