<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TablaGoleador\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\TablaGoleador;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorTablaGoleadores extends Controller
{
    use SeleccionarCategoriaTrait;

    // Mostrar todos los goleadores
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->idCategoria;
        $search_value = $request->search_value;
        $tipo = 'tabla_goleador';

        if (! is_null($idCategoria)) {
            $goleadores_t = TablaGoleador::search($search_value)
                ->join('ediciones as ed', 'tabla_goleadores.idEdicion', '=', 'ed.id')
                ->join('equipos as e', 'tabla_goleadores.idEquipo', '=', 'e.id')
                ->join('categorias as cat', 'tabla_goleadores.idCategoria', '=', 'cat.id')
                ->join('jugadores as j', 'tabla_goleadores.dni_jugador', '=', 'j.dni')
                ->select('tabla_goleadores.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo', 'j.nombre', 'j.apellido')
                ->where('tabla_goleadores.idEdicion', $idEdicion)
                ->where('tabla_goleadores.idCategoria', $idCategoria)
                ->orderBy('cat.nombreCategoria', 'desc')
                ->orderBy('tabla_goleadores.cantidadGoles', 'desc')
                ->paginate(30);
            $goleadores_t->appends(['idEdicion' => $idEdicion, 'idCategoria' => $idCategoria]);
        } else {
            $goleadores_t = TablaGoleador::search($search_value)
                ->join('ediciones as ed', 'tabla_goleadores.idEdicion', '=', 'ed.id')
                ->join('equipos as e', 'tabla_goleadores.idEquipo', '=', 'e.id')
                ->join('categorias as cat', 'tabla_goleadores.idCategoria', '=', 'cat.id')
                ->join('jugadores as j', 'tabla_goleadores.dni_jugador', '=', 'j.dni')
                ->select('tabla_goleadores.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo', 'j.nombre', 'j.apellido')
                ->where('tabla_goleadores.idEdicion', $idEdicion)
                ->orderBy('cat.nombreCategoria', 'desc')
                ->orderBy('tabla_goleadores.cantidadGoles', 'desc')
                ->paginate(30);
            $goleadores_t->appends(['idEdicion' => $idEdicion]);
        }

        if (isset($EdicionSeleccionada)) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->select('id', 'nombreCategoria')
                ->orderBy('nombreCategoria', 'desc')
                ->get();
        }

        return view('Panel.tabla_goleador.index', compact('ediciones', 'EdicionSeleccionada', 'goleadores_t', 'categorias', 'tipo'));
    }

    // Mostrar formulario de creación
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $goleador_t = new TablaGoleador;
        $idEdicion = $request->input('idEdicion');
        $idCategoria = $request->input('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();

        return view('Panel.tabla_goleador.create', compact('ediciones', 'goleador_t', 'idCategoria', 'EdicionSeleccionada', 'equipos', 'CategoriaSeleccionada'));
    }

    // Guardar nuevo goleador
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        TablaGoleador::create($data);

        return to_route('tabla_goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Jugador Cargado');
    }

    // Editar goleador
    public function edit(TablaGoleador $tabla_goleador)
    {
        $ediciones = Edicion::all();
        $idEdicion = $tabla_goleador->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $tabla_goleador->idCategoria;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $equipos = Equipo::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get();
        $goleador_t = $tabla_goleador;

        return view('Panel.tabla_goleador.edit', compact('ediciones', 'goleador_t', 'equipos', 'CategoriaSeleccionada', 'EdicionSeleccionada'));
    }

    // Actualizar goleador
    /**
     * Actualiza los goles del jugador sin permitir cambiar su equipo manualmente.
     *
     * Regla de negocio: en edicion, el equipo se mantiene segun el jugador
     * asociado al registro para evitar inconsistencias en la tabla.
     */
    public function update(StoreRequest $request, TablaGoleador $tabla_goleador)
    {
        $data = $request->validated();
        $data['idEquipo'] = $tabla_goleador->idEquipo;
        unset($data['nombre']);

        $tabla_goleador->update($data);

        return to_route('tabla_goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Jugador Actualizado');
    }

    // Eliminar goleador
    public function destroy(TablaGoleador $tabla_goleador, Request $request)
    {
        $tabla_goleador->delete();

        return to_route('goleador.index', ['idEdicion' => $request->idEdicion])->with('status', 'Jugador Eliminado');
    }
}
