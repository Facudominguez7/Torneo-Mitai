<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\EquipoGrupo\AsignacionMasivaRequest;
use App\Http\Requests\EquipoGrupo\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Services\AsignadorEquiposGrupoService;
use App\Traits\SeleccionarCategoriaTrait;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ControladorEquiposGrupos extends Controller
{
    /**
     * Servicio que concentra las reglas de asignación automática.
     */
    public function __construct(private readonly AsignadorEquiposGrupoService $asignadorEquiposGrupoService) {}

    /**
     * Display a listing of the resource.
     */
    use SeleccionarCategoriaTrait;

    public function index(Request $request)
    {
        //
    }

    /**
     * Mostrar formulario de alta de equipo en grupo.
     *
     * Este flujo requiere grupo y categoría válidos para evitar altas
     * incompletas o inconsistentes desde accesos directos por URL.
     */
    public function create(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $idCategoria = $request->idCategoria;
        $idEdicion = $request->idEdicion;

        if (! $idGrupo || ! $idCategoria || ! $idEdicion) {
            return to_route('seleccionar-categoria', [
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'tipo' => 'equipogrupo',
            ])->with('error', 'Primero selecciona categoría y grupo para poder agregar equipos.');
        }

        $ediciones = Edicion::all();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $GrupoSeleccionado = $idGrupo ? Grupo::find($idGrupo) : null;

        if ($idCategoria && $idEdicion) {
            $equipos = Equipo::join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
                ->where('equipo_ediciones.idCategoria', $idCategoria)
                ->where('equipo_ediciones.idEdicion', $idEdicion)
                ->select('equipos.id', 'equipos.nombre')
                ->get();
        } else {
            $equipos = collect(); // Si no hay categoría o edición seleccionada, devuelve un conjunto vacío.
        }
        $equipogrupo = new EquipoGrupo;

        return view('Panel.equipogrupo.create', compact('equipos', 'equipogrupo', 'ediciones', 'EdicionSeleccionada', 'CategoriaSeleccionada', 'GrupoSeleccionado'));
    }

    /**
     * Persistir la relación equipo-grupo y su tabla de posición inicial.
     */
    public function store(StoreRequest $request)
    {

        EquipoGrupo::create($request->validated());
        TablaPosicion::firstOrCreate([
            'idGrupo' => $request->idGrupo,
            'idEquipo' => $request->idEquipo,
            'idEdicion' => $request->idEdicion,
            'golesFavor' => 0,
            'golesContra' => 0,
            'diferenciaGoles' => 0,
            'jugado' => 0,
            'ganado' => 0,
            'empatado' => 0,
            'perdido' => 0,
            'puntos' => 0,
        ]);

        return redirect()->action([ControladorGrupos::class, 'show'], [
            'grupo' => $request->idGrupo,
            'idEdicion' => $request->idEdicion,
            'idCategoria' => $request->idCategoria,
        ])->with('status', 'Equipo agregado al grupo correctamente');
    }

    /**
     * Ejecuta la asignación masiva de equipos pendientes en grupos.
     *
     * Este endpoint está pensado para UX interactiva desde la pantalla
     * de selección de categoría y devuelve un resumen para feedback inmediato.
     */
    public function asignarPendientes(AsignacionMasivaRequest $request): JsonResponse
    {
        $idEdicion = (int) $request->validated('idEdicion');
        $idCategoria = (int) $request->validated('idCategoria');

        try {
            $resultado = $this->asignadorEquiposGrupoService->asignarPendientes($idEdicion, $idCategoria);

            return response()->json([
                'success' => true,
                'message' => $resultado['asignados'] > 0
                    ? 'Asignación automática completada correctamente.'
                    : 'No había equipos pendientes para asignar.',
                'data' => $resultado,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Error en asignación masiva de equipos a grupos', [
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la asignación automática. Intentá nuevamente.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, EquipoGrupo $equipogrupo)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->idCategoria;
        $idGrupo = $request->idGrupo;
        $equipos = Equipo::where('idCategoria', $idCategoria)->get();
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        return view('Panel.equipogrupo.edit', compact('equipos', 'equipogrupo', 'ediciones', 'EdicionSeleccionada', 'CategoriaSeleccionada', 'idGrupo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRequest $request, EquipoGrupo $equipogrupo)
    {
        $equipogrupo->update($request->validated());
        $grupo = $equipogrupo->idGrupo;

        return redirect()->action([ControladorGrupos::class, 'show'], ['idEdicion' => $request->idEdicion, 'grupo' => $grupo])->with('status', 'Equipo actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, EquipoGrupo $equipogrupo)
    {
        $equipogrupo->delete();
        $grupo = $equipogrupo->idGrupo;

        return redirect()->action([ControladorGrupos::class, 'show'], ['idEdicion' => $request->idEdicion, 'grupo' => $grupo])->with('status', 'Equipo eliminado correctamente');
    }
}
