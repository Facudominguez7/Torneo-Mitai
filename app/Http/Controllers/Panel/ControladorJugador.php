<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jugador\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\JugadorEdicion;
use Illuminate\Http\Request;

class ControladorJugador extends Controller
{
    public function index(Request $request)
    {
        // Obtener todas las ediciones
        $ediciones = Edicion::all();

        // Obtener el ID de la edición seleccionada
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        // Obtener los jugadores de la edición seleccionada, usando las relaciones
        $jugadores = Jugador::join('jugadores_ediciones as je', 'jugadores.id', '=', 'je.idJugador')
            ->join('equipos as e', 'jugadores.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'jugadores.idCategoria', '=', 'cat.id')
            ->select('jugadores.*', 'e.nombre as nombreEquipo', 'cat.nombre as nombreCategoria', 'e.id as idEquipo')
            ->where('je.idEdicion', $idEdicion) // Filtro para la edición seleccionada
            ->orderBy('cat.nombre', 'desc') // Ordenar por nombre de categoría
            ->paginate(7); // Paginación

        // Mantener el filtro de edición en la URL
        $jugadores->appends(['idEdicion' => $idEdicion]);

        // Pasar los datos a la vista
        return view('Panel.jugador.index', compact('ediciones', 'EdicionSeleccionada', 'jugadores'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Obtener todas las ediciones
        $ediciones = Edicion::all();

        // Obtener el ID de la edición y la categoría desde el request
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');

        // Obtener las ediciones y categorías seleccionadas
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        // Obtener los equipos correspondientes a la edición y categoría seleccionadas
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();

        // Crear una instancia de jugador vacía
        $jugador = new Jugador();

        // Pasar todos los datos a la vista
        return view('Panel.jugador.create', compact('ediciones', 'jugador', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        // Validar los datos del formulario
        $data = $request->validated();

        // Crear el jugador en la tabla jugadores
        $jugador = Jugador::create($data);

        // Crear la relación en la tabla jugadores_ediciones
        JugadorEdicion::create([
            'idJugador' => $jugador->id,
            'idEdicion' => $request->idEdicion,
            'idCategoria' => $request->idCategoria,
        ]);

        // Redirigir al índice de jugadores con un mensaje de éxito
        return to_route('jugador.index', ['idEdicion' => $request->idEdicion])
            ->with('status', 'Jugador Cargado Correctamente');
    }


    /**
     * Display the specified resource.
     */
    public function show(Jugador $goleador)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jugador $jugador)
    {
        // Obtener todas las ediciones del torneo
        $ediciones = Edicion::all();

        // Obtener la edición y categoría del jugador seleccionado
        $idEdicion = $jugador->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $idCategoria = $jugador->idCategoria;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        // Obtener los equipos que pertenecen a la edición y categoría seleccionada
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();

        // Retornar la vista con la información necesaria
        return view('Panel.jugador.edit', compact('ediciones', 'jugador', 'equipos', 'CategoriaSeleccionada', 'EdicionSeleccionada'));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, Jugador $jugador)
    {
        // Validar los datos de la solicitud
        $data = $request->validated();

        // Actualizar los datos del jugador
        $jugador->update($data);

        // Actualizar la relación con la edición y categoría, si es necesario
        // Por ejemplo, puedes actualizar o crear una relación entre jugadores y ediciones si cambia la edición o categoría
        $jugador->ediciones()->updateOrCreate(
            ['idJugador' => $jugador->id, 'idEdicion' => $request->idEdicion],
            ['idCategoria' => $request->idCategoria]
        );

        // Redirigir a la vista de jugadores con un mensaje de éxito
        return to_route('jugador.index', ['idEdicion' => $request->idEdicion])
            ->with('status', 'Jugador Actualizado Correctamente');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jugador $jugador, Request $request)
    {
        // Eliminar al jugador
        $jugador->delete();

        // Eliminar la relación del jugador con la edición, si existe en la tabla intermedia
        $jugador->ediciones()->detach();

        // Redirigir a la lista de jugadores con un mensaje de éxito
        return to_route('jugador.index', ['idEdicion' => $request->idEdicion])
            ->with('status', 'Jugador Eliminado Correctamente');
    }
}
