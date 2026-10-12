<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gestionar instituciones y sus equipos asociados
 *
 * Permite visualizar y vincular equipos que no tienen institución asignada
 * a una institución existente para resolver conflictos de validación de jugadores.
 */
class ControladorInstitucion extends Controller
{
    /**
     * Mostrar lista de instituciones con sus equipos asociados
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::orderBy('id', 'desc')->get();
        $EdicionSeleccionada = $request->get('idEdicion')
            ? Edicion::find($request->get('idEdicion'))
            : Edicion::orderBy('id', 'desc')->first();

        // Obtener todas las instituciones con conteo de equipos
        $instituciones = Institucion::withCount('equipos')
            ->orderBy('nombre')
            ->get();

        // Filtrar equipos por edición si está seleccionada
        $equiposPorInstitucion = [];
        if ($EdicionSeleccionada) {
            foreach ($instituciones as $institucion) {
                $equipos = Equipo::where('idInstitucion', $institucion->id)
                    ->whereHas('equipoEdiciones', function ($q) use ($EdicionSeleccionada) {
                        $q->where('idEdicion', $EdicionSeleccionada->id);
                    })
                    ->with(['equipoEdiciones' => function ($q) use ($EdicionSeleccionada) {
                        $q->where('idEdicion', $EdicionSeleccionada->id)->with('categoria');
                    }])
                    ->get();

                if ($equipos->count() > 0) {
                    $equiposPorInstitucion[$institucion->id] = $equipos;
                }
            }
        }

        // Obtener equipos SIN institución asignada
        $equiposSinInstitucionQuery = Equipo::whereNull('idInstitucion');

        if ($EdicionSeleccionada) {
            $equiposSinInstitucionQuery->whereHas('equipoEdiciones', function ($q) use ($EdicionSeleccionada) {
                $q->where('idEdicion', $EdicionSeleccionada->id);
            })->with(['equipoEdiciones' => function ($q) use ($EdicionSeleccionada) {
                $q->where('idEdicion', $EdicionSeleccionada->id)->with('categoria');
            }]);
        }

        $equiposSinInstitucion = $equiposSinInstitucionQuery->orderBy('nombre')->get();

        // Estadísticas
        $totalInstituciones = $instituciones->count();
        $institucionesConEquipos = $instituciones->where('equipos_count', '>', 0)->count();
        $totalEquiposSinInstitucion = $equiposSinInstitucion->count();

        return view('Panel.institucion.index', compact(
            'instituciones',
            'equiposPorInstitucion',
            'equiposSinInstitucion',
            'ediciones',
            'EdicionSeleccionada',
            'totalInstituciones',
            'institucionesConEquipos',
            'totalEquiposSinInstitucion'
        ));
    }

    /**
     * Actualizar el nombre de una institución
     */
    public function update(Request $request, Institucion $institucion)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:instituciones,nombre,'.$institucion->id,
        ]);

        $nombreAnterior = $institucion->nombre;
        $institucion->update(['nombre' => $request->nombre]);

        Log::info("Institución renombrada: '{$nombreAnterior}' -> '{$request->nombre}'");

        return redirect()->back()->with('success',
            "Institución renombrada de $nombreAnterior a {$request->nombre}."
        );
    }

    /**
     * Cambiar la institución de un equipo específico
     */
    public function cambiarInstitucionEquipo(Request $request)
    {
        $request->validate([
            'equipo_id' => 'required|exists:equipos,id',
            'institucion_id' => 'required|exists:instituciones,id',
        ]);

        $equipo = Equipo::findOrFail($request->equipo_id);
        $institucion = Institucion::findOrFail($request->institucion_id);

        $institucionAnterior = $equipo->institucion?->nombre ?? 'Sin institución';
        $equipo->idInstitucion = $institucion->id;
        $equipo->save();

        Log::info("Equipo '{$equipo->nombre}' movido de '{$institucionAnterior}' a '{$institucion->nombre}'");

        return redirect()->back()->with('success',
            "Equipo {$equipo->nombre} vinculado a institución {$institucion->nombre}."
        );
    }

    /**
     * Desvincular un equipo de su institución (poner idInstitucion a NULL)
     */
    public function desvincularEquipo(Request $request)
    {
        $request->validate([
            'equipo_id' => 'required|exists:equipos,id',
        ]);

        $equipo = Equipo::findOrFail($request->equipo_id);
        $institucionAnterior = $equipo->institucion?->nombre ?? 'Sin institución';

        $equipo->idInstitucion = null;
        $equipo->save();

        Log::info("Equipo '{$equipo->nombre}' desvinculado de institución '{$institucionAnterior}'");

        return redirect()->back()->with('success',
            "Equipo {$equipo->nombre} desvinculado de institución $institucionAnterior."
        );
    }

    /**
     * Crear una nueva institución (verificando que no exista)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:instituciones,nombre',
        ], [
            'nombre.unique' => 'Ya existe una institución con ese nombre.',
            'nombre.required' => 'El nombre de la institución es requerido.',
        ]);

        $institucion = Institucion::create([
            'nombre' => trim($request->nombre),
        ]);

        Log::info("Nueva institución creada: '{$institucion->nombre}'");

        return redirect()->back()->with('success',
            "Institución {$institucion->nombre} creada correctamente."
        );
    }
}
