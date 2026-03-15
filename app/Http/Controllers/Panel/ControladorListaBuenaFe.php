<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use App\Services\ActualizadorIdentidadJugador;
use App\Services\EstadisticasService;
use App\Services\ValidadorReglasJugador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControladorListaBuenaFe extends Controller
{
    protected $validadorReglas;

    protected $actualizadorIdentidadJugador;

    public function __construct(ValidadorReglasJugador $validadorReglas, ActualizadorIdentidadJugador $actualizadorIdentidadJugador)
    {
        $this->validadorReglas = $validadorReglas;
        $this->actualizadorIdentidadJugador = $actualizadorIdentidadJugador;
    }

    /**
     * Construye el listado base de gestión para que filtros de edición, categoría
     * y búsqueda se reutilicen también en la impresión masiva.
     */
    public function index(Request $request)
    {
        $idEdicion = $request->get('idEdicion');
        $idCategoria = $request->get('idCategoria');
        $search = $request->get('search');

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : Edicion::latest()->first();
        $limiteJugadoresPorPlanilla = $this->obtenerLimiteJugadoresPorPlanilla($EdicionSeleccionada);
        $ediciones = Edicion::all();

        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $EdicionSeleccionada->id)->get();
        } else {
            $categorias = collect(); // Colección vacía si no hay edición
        }

        $equipos = $this->obtenerEquiposFiltrados(
            $EdicionSeleccionada?->id,
            $idCategoria,
            $search
        );

        // Obtener estadísticas de jugadores
        $estadisticasJugadores = null;
        $jugadoresPorCategoria = null;
        if ($EdicionSeleccionada) {
            $estadisticasService = new EstadisticasService();
            $estadisticasJugadores = $estadisticasService->obtenerJugadoresDosUltimasFechas($EdicionSeleccionada->id);
            $jugadoresPorCategoria = $estadisticasService->obtenerJugadoresPorCategoriaDosUltimasFechas($EdicionSeleccionada->id);
        }

        // Obtener equipos que superan el límite configurado en la edición.
        $equiposExcedidos = [];
        if ($EdicionSeleccionada) {
            $equiposExcedidos = ListaBuenaFe::select('idEquipo', 'idCategoria', DB::raw('count(*) as total'))
                ->where('idEdicion', $EdicionSeleccionada->id)
                ->where('activo', true)
                ->groupBy('idEquipo', 'idCategoria')
                ->having('total', '>', $limiteJugadoresPorPlanilla)
                ->with(['equipo', 'categoria']) // Cargar relaciones
                ->get();
        }

        return view('Panel.lista-buena-fe.index', compact(
            'ediciones',
            'categorias',
            'EdicionSeleccionada',
            'equipos',
            'idCategoria',
            'estadisticasJugadores',
            'jugadoresPorCategoria',
            'equiposExcedidos',
            'limiteJugadoresPorPlanilla'
        ));
    }

    /**
     * Mostrar la lista de buena fe de un equipo específico
     */
    public function show($idEquipo, $idEdicion)
    {
        $equipoSeleccionado = EquipoEdicion::with('equipo', 'categoria')
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->firstOrFail();

        $EdicionSeleccionada = Edicion::findOrFail($idEdicion);
        $limiteJugadoresPorPlanilla = $this->obtenerLimiteJugadoresPorPlanilla($EdicionSeleccionada);

        // Obtener jugadores de la lista de buena fe (activos e inactivos)
        $jugadoresActivos = ListaBuenaFe::with('jugador', 'categoria')
            ->deEquipo($idEquipo, $idEdicion)
            ->activos()
            ->get();

        $jugadoresInactivos = ListaBuenaFe::with('jugador', 'categoria')
            ->deEquipo($idEquipo, $idEdicion)
            ->where('activo', false)
            ->orderBy('fecha_baja', 'desc')
            ->get();

        // Obtener estadísticas de jugadores
        $estadisticasService = new EstadisticasService();
        $estadisticasJugadores = $estadisticasService->obtenerJugadoresDosUltimasFechas($idEdicion);
        $jugadoresPorCategoria = $estadisticasService->obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion);

        $ediciones = Edicion::all();

        return view('Panel.lista-buena-fe.show', compact(
            'EdicionSeleccionada',
            'equipoSeleccionado',
            'jugadoresActivos',
            'jugadoresInactivos',
            'estadisticasJugadores',
            'jugadoresPorCategoria',
            'ediciones',
            'limiteJugadoresPorPlanilla'
        ));
    }

    /**
     * Genera la planilla individual con auto-impresión para un equipo,
     * usando el formato de mesa de control.
     */
    public function imprimirPlanilla($idEquipo, $idEdicion)
    {
        $equipoSeleccionado = EquipoEdicion::with('equipo', 'categoria')
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->firstOrFail();

        $EdicionSeleccionada = Edicion::findOrFail($idEdicion);
        $limiteJugadoresPorPlanilla = $this->obtenerLimiteJugadoresPorPlanilla($EdicionSeleccionada);

        $jugadoresActivos = ListaBuenaFe::with('jugador', 'categoria')
            ->deEquipo($idEquipo, $idEdicion)
            ->activos()
            ->get();

        return view('Panel.lista-buena-fe.imprimir', compact(
            'EdicionSeleccionada',
            'equipoSeleccionado',
            'jugadoresActivos',
            'limiteJugadoresPorPlanilla'
        ));
    }

    /**
     * Centraliza la impresión de todas las planillas para evitar imprimir equipo por equipo.
     *
     * Respeta los mismos filtros visibles en la pantalla principal de Lista de Buena Fe.
     */
    public function imprimirPlanillas(Request $request, $idEdicion)
    {
        $idCategoria = $request->get('idCategoria');
        $search = $request->get('search');

        $EdicionSeleccionada = Edicion::findOrFail($idEdicion);
        $limiteJugadoresPorPlanilla = $this->obtenerLimiteJugadoresPorPlanilla($EdicionSeleccionada);
        $equipos = $this->obtenerEquiposFiltrados($idEdicion, $idCategoria, $search);

        $jugadoresActivos = ListaBuenaFe::with(['jugador', 'categoria'])
            ->where('idEdicion', $idEdicion)
            ->where('activo', true)
            ->when($idCategoria, function ($query) use ($idCategoria) {
                $query->where('idCategoria', $idCategoria);
            })
            ->whereIn('idEquipo', $equipos->pluck('idEquipo')->unique()->values())
            ->get()
            ->groupBy(function ($jugador) {
                return $jugador->idEquipo.'-'.$jugador->idCategoria;
            });

        return view('Panel.lista-buena-fe.imprimir-todas', compact(
            'EdicionSeleccionada',
            'equipos',
            'jugadoresActivos',
            'limiteJugadoresPorPlanilla'
        ));
    }

    /**
     * Generar PDF de la lista de buena fe
     */
    public function pdf($idEquipo, $idEdicion)
    {
        $equipoSeleccionado = EquipoEdicion::with('equipo', 'categoria')
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->firstOrFail();

        $EdicionSeleccionada = Edicion::findOrFail($idEdicion);

        $jugadoresActivos = ListaBuenaFe::with('jugador', 'categoria')
            ->deEquipo($idEquipo, $idEdicion)
            ->activos()
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('Panel.lista-buena-fe.pdf', compact(
            'EdicionSeleccionada',
            'equipoSeleccionado',
            'jugadoresActivos'
        ));

        return $pdf->download('Lista_Buena_Fe_'.$equipoSeleccionado->equipo->nombre.'.pdf');
    }

    /**
     * Agregar un jugador a la lista de buena fe
     *
     * Flujo simplificado:
     * - Si la validación devuelve error, bloquea el alta.
     * - Si devuelve warning, permite el alta y marca el registro como conflictivo.
     */
    public function agregarJugador(Request $request)
    {
        $request->validate([
            'dni_jugador' => 'required|digits_between:7,8',
            'nombre_jugador' => 'required|string|max:255',
            'apellido_jugador' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'numero_camiseta' => 'nullable|integer|min:0|max:999',
            'idEquipo' => 'required|integer',
            'idEdicion' => 'required|integer',
            'idCategoria' => 'required|integer',
            'force' => 'nullable|boolean',
        ]);

        // --- VALIDACIONES DE NEGOCIO ---
        Log::info("Validando jugador DNI: {$request->dni_jugador} para Equipo: {$request->idEquipo}");

        $resultadoValidacion = $this->validadorReglas->validarJugador(
            $request->dni_jugador,
            $request->idEquipo,
            $request->idEdicion,
            $request->idCategoria,
            $request->fecha_nacimiento
        );

        Log::info('Resultado validación:', $resultadoValidacion ?? ['null']);

        if ($resultadoValidacion && $resultadoValidacion['type'] === 'error') {
            return redirect()->back()->with('error', $resultadoValidacion['message']);
        }

        if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning' && ! $request->boolean('force')) {
            return redirect()->back()
                ->withInput()
                ->with('warning', $resultadoValidacion['message'])
                ->with('warning_data', [
                    'dni_jugador' => $request->dni_jugador,
                    'nombre_jugador' => $request->nombre_jugador,
                    'apellido_jugador' => $request->apellido_jugador,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                    'numero_camiseta' => $request->numero_camiseta,
                    'idEquipo' => $request->idEquipo,
                    'idEdicion' => $request->idEdicion,
                    'idCategoria' => $request->idCategoria,
                ]);
        }

        $esConflictivo = $resultadoValidacion && $resultadoValidacion['type'] === 'warning';
        $motivoConflicto = $esConflictivo ? $resultadoValidacion['message'] : null;

        // Solo ahora iniciamos la transacción para guardar en lista de buena fe
        try {
            DB::beginTransaction();

            $jugador = $this->actualizadorIdentidadJugador->sincronizarJugador(
                $request->dni_jugador,
                $request->nombre_jugador,
                $request->apellido_jugador
            );

            // Agregar a la lista de buena fe
            $nuevoJugador = ListaBuenaFe::create([
                'dni_jugador' => $jugador->dni,
                'idEquipo' => $request->idEquipo,
                'idEdicion' => $request->idEdicion,
                'idCategoria' => $request->idCategoria,
                'numero_camiseta' => $request->numero_camiseta,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'fecha_alta' => now(),
                'activo' => true,
                'conflictivo' => $esConflictivo,
                'motivo_conflicto' => $motivoConflicto,
            ]);

            DB::commit();

            Log::info("Jugador agregado a Lista de Buena Fe: {$request->dni_jugador} en Equipo {$request->idEquipo}");
            if ($esConflictivo) {
                Log::warning("Jugador agregado con observación administrativa: {$motivoConflicto}");
            }

            $mensaje = 'Jugador agregado correctamente.';
            if ($nuevoJugador->conflictivo) {
                $mensaje .= ' (Marcado como CONFLICTIVO)';
            }

            return redirect()->back()->with('success', $mensaje);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error al agregar jugador: '.$e->getMessage());

            return redirect()->back()->with('error', 'Ocurrió un error al agregar el jugador: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar jugadores conflictivos
     */
    public function conflictivos(Request $request)
    {
        $ediciones = Edicion::all();
        $EdicionSeleccionada = $request->get('idEdicion')
            ? Edicion::find($request->get('idEdicion'))
            : Edicion::orderBy('id', 'desc')->first();

        // Filtro de estado (pendientes o resueltos)
        $estado = $request->get('estado', 'pendientes'); // default: pendientes

        // Query base
        $query = ListaBuenaFe::with(['jugador', 'equipo', 'edicion', 'categoria'])
            ->where('conflictivo', true)
            ->where('activo', true);

        // Filtrar por estado de resolución
        if ($estado === 'resueltos') {
            $query->where('resuelto', true);
        } else {
            $query->where('resuelto', false);
        }

        // Filtrar por edición si se especifica
        if ($request->has('idEdicion') && $request->get('idEdicion')) {
            $query->where('idEdicion', $request->get('idEdicion'));
        }

        // Búsqueda por jugador (DNI, nombre, apellido)
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('dni_jugador', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('jugador', function ($subQ) use ($search) {
                        $subQ->where('nombre', 'LIKE', '%'.$search.'%')
                            ->orWhere('apellido', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        // Filtrar por equipo si se especifica
        if ($request->has('idEquipo') && $request->get('idEquipo')) {
            $query->where('idEquipo', $request->get('idEquipo'));
        }

        $jugadores = $query->orderBy('created_at', 'desc')->get();

        // Obtener equipos para filtro
        $equipos = [];
        if ($EdicionSeleccionada) {
            $equipos = EquipoEdicion::with('equipo')
                ->where('idEdicion', $EdicionSeleccionada->id)
                ->get()
                ->pluck('equipo')
                ->unique('id');
        }

        return view('Panel.lista-buena-fe.conflictivos', compact(
            'jugadores',
            'ediciones',
            'EdicionSeleccionada',
            'estado',
            'equipos'
        ));
    }

    /**
     * Dar de baja un jugador de la lista de buena fe
     */
    public function darDeBaja(Request $request, $id)
    {
        $request->validate([
            'motivo_baja' => 'nullable|string|max:255',
        ]);

        try {
            $listaBuenaFe = ListaBuenaFe::findOrFail($id);

            // Si el jugador es conflictivo y no está resuelto, resolver automáticamente
            if ($listaBuenaFe->conflictivo && ! $listaBuenaFe->resuelto) {
                $usuario = auth()->user()->name ?? auth()->user()->email ?? 'Sistema';
                $notas = 'Conflicto resuelto automáticamente al dar de baja al jugador. Motivo de baja: '.($request->motivo_baja ?? 'No especificado');
                $listaBuenaFe->resolverConflicto($usuario, $notas);
                Log::info("Conflicto auto-resuelto para jugador {$listaBuenaFe->dni_jugador} al dar de baja");
            }

            $listaBuenaFe->darDeBaja($request->motivo_baja);

            Log::info("Jugador {$listaBuenaFe->dni_jugador} dado de baja de Lista de Buena Fe. Motivo: ".($request->motivo_baja ?? 'Sin especificar'));

            return redirect()->back()->with('success', 'Jugador dado de baja correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al dar de baja jugador: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al dar de baja el jugador.');
        }
    }

    /**
     * Reactivar un jugador en la lista de buena fe
     */
    public function reactivar(Request $request, $id)
    {
        try {
            $listaBuenaFe = ListaBuenaFe::findOrFail($id);

            // Si era conflictivo y estaba resuelto, vamos a limpiar la resolución antes de reactivar
            // porque al reactivar podría volver a ser conflictivo
            $eraConflictivoResuelto = $listaBuenaFe->conflictivo && $listaBuenaFe->resuelto;

            // Validar antes de reactivar
            $resultadoValidacion = $this->validadorReglas->validarJugador(
                $listaBuenaFe->dni_jugador,
                $listaBuenaFe->idEquipo,
                $listaBuenaFe->idEdicion,
                $listaBuenaFe->idCategoria,
                $listaBuenaFe->fecha_nacimiento,
                true // esReactivacion
            );

            if ($resultadoValidacion && $resultadoValidacion['type'] === 'error') {
                return redirect()->back()->with('error', $resultadoValidacion['message']);
            }

            if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning' && ! $request->boolean('force')) {
                return redirect()->back()
                    ->with('warning_reactivar', $resultadoValidacion['message'])
                    ->with('warning_reactivar_id', $id);
            }

            $esConflictivo = $resultadoValidacion && $resultadoValidacion['type'] === 'warning';
            $motivoConflicto = $esConflictivo ? $resultadoValidacion['message'] : null;

            // Reactivar el jugador
            $listaBuenaFe->reactivar();

            // Si hay advertencia administrativa, marcar como conflictivo
            if ($esConflictivo) {
                $listaBuenaFe->conflictivo = true;
                $listaBuenaFe->motivo_conflicto = $motivoConflicto;
                $listaBuenaFe->resuelto = false;
                $listaBuenaFe->fecha_resolucion = null;
                $listaBuenaFe->resuelto_por = null;
                $listaBuenaFe->notas_resolucion = null;
                $listaBuenaFe->save();
            } elseif ($eraConflictivoResuelto && ! $resultadoValidacion) {
                // Si era conflictivo resuelto y ahora no hay warnings, limpiar el conflicto
                $usuario = auth()->user()->name ?? auth()->user()->email ?? 'Sistema';
                $notas = 'Conflicto resuelto automáticamente al reactivar jugador sin advertencias administrativas.';
                $listaBuenaFe->conflictivo = false;
                $listaBuenaFe->motivo_conflicto = null;
                $listaBuenaFe->resolverConflicto($usuario, $notas);
            }

            Log::info("Jugador {$listaBuenaFe->dni_jugador} reactivado en Lista de Buena Fe");

            $mensaje = 'Jugador reactivado correctamente.';
            if ($listaBuenaFe->conflictivo) {
                $mensaje .= ' (Marcado como CONFLICTIVO)';
            }

            return redirect()->back()->with('success', $mensaje);

        } catch (\Exception $e) {
            Log::error('Error al reactivar jugador: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al reactivar el jugador.');
        }
    }

    /**
     * Resolver un conflicto administrativo
     */
    public function resolverConflicto(Request $request, $id)
    {
        $request->validate([
            'notas_resolucion' => 'nullable|string|max:1000',
        ]);

        try {
            $listaBuenaFe = ListaBuenaFe::findOrFail($id);

            // Verificar que sea efectivamente un conflicto
            if (! $listaBuenaFe->conflictivo) {
                return redirect()->back()->with('error', 'Este jugador no está marcado como conflictivo.');
            }

            // Verificar que no esté ya resuelto
            if ($listaBuenaFe->resuelto) {
                return redirect()->back()->with('info', 'Este conflicto ya fue resuelto previamente.');
            }

            // Resolver el conflicto
            $usuario = auth()->user()->name ?? auth()->user()->email ?? 'Sistema';
            $listaBuenaFe->resolverConflicto($usuario, $request->notas_resolucion);

            Log::info("Conflicto resuelto para jugador {$listaBuenaFe->dni_jugador} por usuario {$usuario}");

            return redirect()->back()->with('success', 'Conflicto resuelto correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al resolver conflicto: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al resolver el conflicto.');
        }
    }

    /**
     * Actualizar datos de un jugador en la lista de buena fe
     *
     * Revalida categoría/edad al modificar fecha de nacimiento para evitar
     * inconsistencias silenciosas entre regla de negocio y dato persistido.
     */
    public function actualizar(Request $request, $id)
    {
        $request->validate([
            'dni_jugador' => 'required|digits_between:7,8',
            'nombre_jugador' => 'required|string|max:255',
            'apellido_jugador' => 'required|string|max:255',
            'numero_camiseta' => 'nullable|integer|min:0|max:999',
            'fecha_nacimiento' => 'required|date',
            'force' => 'nullable|boolean',
        ]);

        try {
            $listaBuenaFe = ListaBuenaFe::findOrFail($id);

            $resultadoValidacion = $this->validadorReglas->validarActualizacionJugador(
                $listaBuenaFe->id,
                $request->dni_jugador,
                $listaBuenaFe->idEquipo,
                $listaBuenaFe->idEdicion,
                $listaBuenaFe->idCategoria,
                $request->fecha_nacimiento
            );

            if ($resultadoValidacion && $resultadoValidacion['type'] === 'error') {
                return redirect()->back()->with('error', $resultadoValidacion['message']);
            }

            if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning' && ! $request->boolean('force')) {
                return redirect()->back()
                    ->with('warning_actualizar', $resultadoValidacion['message'])
                    ->with('warning_actualizar_action', route('lista-buena-fe.actualizar', $listaBuenaFe->id))
                    ->with('warning_actualizar_data', [
                        'dni_jugador' => $request->dni_jugador,
                        'nombre_jugador' => $request->nombre_jugador,
                        'apellido_jugador' => $request->apellido_jugador,
                        'fecha_nacimiento' => $request->fecha_nacimiento,
                        'numero_camiseta' => $request->numero_camiseta,
                    ]);
            }

            DB::beginTransaction();

            $jugador = $this->actualizadorIdentidadJugador->actualizarIdentidad(
                $listaBuenaFe->dni_jugador,
                $request->dni_jugador,
                $request->nombre_jugador,
                $request->apellido_jugador
            );

            $listaBuenaFe->dni_jugador = $jugador->dni;
            $listaBuenaFe->numero_camiseta = $request->numero_camiseta;
            $listaBuenaFe->fecha_nacimiento = $request->fecha_nacimiento;
            $this->aplicarEstadoConflicto($listaBuenaFe, $resultadoValidacion);

            $listaBuenaFe->save();

            DB::commit();

            Log::info("Datos actualizados para jugador {$listaBuenaFe->dni_jugador} en Lista de Buena Fe");

            $mensaje = 'Datos actualizados correctamente.';
            if ($listaBuenaFe->conflictivo) {
                $mensaje .= ' (Marcado como CONFLICTIVO)';
            }

            return redirect()->back()->with('success', $mensaje);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error al actualizar jugador: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al actualizar los datos: '.$e->getMessage());
        }
    }

    /**
     * Buscar jugador por DNI para autocompletado
     *
     * Busca si un DNI ya está registrado en el sistema y retorna
     * los datos del jugador (nombre, apellido, fecha de nacimiento, numero_camiseta)
     * para autocompletar el formulario de alta en Lista de Buena Fe.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function buscarPorDni(Request $request)
    {
        $request->validate([
            'dni' => 'required|digits_between:7,8',
        ]);

        $dni = $request->dni;

        // Buscar jugador en la tabla principal
        $jugador = Jugador::find($dni);

        if (! $jugador) {
            return response()->json([
                'encontrado' => false,
                'mensaje' => 'DNI no registrado en el sistema',
            ]);
        }

        // Buscar fecha de nacimiento y número de camiseta más reciente
        $fechaNacimiento = null;
        $numeroCamiseta = null;

        // Primero buscar en lista_buena_fe (más confiable y reciente)
        $registroLBF = ListaBuenaFe::where('dni_jugador', $dni)
            ->orderBy('id', 'desc')
            ->first();

        if ($registroLBF) {
            if ($registroLBF->fecha_nacimiento) {
                $fechaNacimiento = $registroLBF->fecha_nacimiento->format('Y-m-d');
            }
            if (! is_null($registroLBF->numero_camiseta)) {
                $numeroCamiseta = $registroLBF->numero_camiseta;
            }
        }

        // Si no hay fecha en lista_buena_fe, buscar en planilla_jugadores
        if (! $fechaNacimiento || ! $numeroCamiseta) {
            $registroPlanilla = PlanillaJugador::where('dni_jugador', $dni)
                ->orderBy('id', 'desc')
                ->first();

            if ($registroPlanilla) {
                if (! $fechaNacimiento && $registroPlanilla->fecha_nacimiento) {
                    $fechaNacimiento = $registroPlanilla->fecha_nacimiento instanceof \Carbon\Carbon
                        ? $registroPlanilla->fecha_nacimiento->format('Y-m-d')
                        : $registroPlanilla->fecha_nacimiento;
                }
                if (is_null($numeroCamiseta) && ! is_null($registroPlanilla->numero_camiseta)) {
                    $numeroCamiseta = $registroPlanilla->numero_camiseta;
                }
            }
        }

        return response()->json([
            'encontrado' => true,
            'nombre' => $jugador->nombre,
            'apellido' => $jugador->apellido,
            'fecha_nacimiento' => $fechaNacimiento,
            'numero_camiseta' => $numeroCamiseta,
            'mensaje' => 'Jugador encontrado - Datos cargados automáticamente',
        ]);
    }

    /**
     * Migrar jugadores existentes de planillas a lista de buena fe
     */
    public function migrarDesdePartidos(Request $request)
    {
        $idEquipo = $request->idEquipo;
        $idEdicion = $request->idEdicion;

        try {
            DB::beginTransaction();
            $resultado = $this->realizarMigracion($idEquipo, $idEdicion);
            DB::commit();

            Log::info("Migración completada para equipo {$idEquipo}: {$resultado['migrados']} jugadores migrados, {$resultado['omitidos']} ya existían");

            return redirect()->back()->with('success', "Se migraron {$resultado['migrados']} jugadores. {$resultado['omitidos']} ya existían en la lista.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en migración: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al migrar jugadores: '.$e->getMessage());
        }
    }

    /**
     * Migrar TODOS los equipos de una edición
     */
    public function migrarTodos(Request $request)
    {
        $idEdicion = $request->idEdicion;

        if (! $idEdicion) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición para migrar.');
        }

        try {
            // Obtener todos los equipos de la edición
            $equiposEdicion = EquipoEdicion::where('idEdicion', $idEdicion)->get();

            $totalMigrados = 0;
            $totalOmitidos = 0;
            $equiposProcesados = 0;
            $errores = [];

            DB::beginTransaction();

            foreach ($equiposEdicion as $equipoEdicion) {
                try {
                    $resultado = $this->realizarMigracion($equipoEdicion->idEquipo, $idEdicion);
                    $totalMigrados += $resultado['migrados'];
                    $totalOmitidos += $resultado['omitidos'];
                    $equiposProcesados++;
                } catch (\Exception $e) {
                    $errores[] = "Error en equipo ID {$equipoEdicion->idEquipo}: ".$e->getMessage();
                }
            }

            DB::commit();

            $mensaje = "Migración masiva completada. Equipos procesados: {$equiposProcesados}. Jugadores migrados: {$totalMigrados}. Omitidos: {$totalOmitidos}.";

            if (count($errores) > 0) {
                // Si hubo errores parciales, los mostramos en el modal
                return redirect()->back()
                    ->with('migration_result', [
                        'status' => 'warning',
                        'message' => $mensaje,
                        'errors' => $errores,
                    ]);
            }

            return redirect()->back()->with('migration_result', [
                'status' => 'success',
                'message' => $mensaje,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en migración masiva: '.$e->getMessage());

            return redirect()->back()->with('migration_result', [
                'status' => 'error',
                'message' => 'Error crítico al migrar: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Lógica core de migración para un equipo
     */
    private function realizarMigracion($idEquipo, $idEdicion)
    {
        // Obtener jugadores únicos que han jugado para este equipo en esta edición
        $jugadores = PlanillaJugador::select(
            'dni_jugador',
            'idEquipo',
            'idEdicion',
            'idCategoria',
            'fecha_nacimiento',
            DB::raw('MAX(numero_camiseta) as numero_camiseta'),
            DB::raw('MIN(created_at) as fecha_alta')
        )
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->groupBy('dni_jugador', 'idEquipo', 'idEdicion', 'idCategoria', 'fecha_nacimiento')
            ->get();

        $migrados = 0;
        $omitidos = 0;
        $omitidosConflictivos = 0;

        foreach ($jugadores as $jugador) {
            // Usar firstOrCreate para evitar duplicados
            $created = ListaBuenaFe::firstOrCreate(
                [
                    'dni_jugador' => $jugador->dni_jugador,
                    'idEquipo' => $idEquipo,
                    'idEdicion' => $idEdicion,
                ],
                [
                    'idCategoria' => $jugador->idCategoria,
                    'numero_camiseta' => $jugador->numero_camiseta,
                    'fecha_nacimiento' => $jugador->fecha_nacimiento,
                    'fecha_alta' => $jugador->fecha_alta ?? now(),
                    'activo' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $migrados++;

                // Validar jugador recién migrado contra reglas administrativas
                $resultadoValidacion = $this->validadorReglas->validarJugador(
                    $jugador->dni_jugador,
                    $idEquipo,
                    $idEdicion,
                    $jugador->idCategoria,
                    $jugador->fecha_nacimiento
                );

                // Si tiene advertencias, marcarlo como conflictivo
                if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning') {
                    $created->conflictivo = true;
                    $created->motivo_conflicto = $resultadoValidacion['message'];
                    $created->resuelto = false;
                    $created->save();
                    $omitidosConflictivos++;

                    Log::info("Jugador migrado marcado como conflictivo: DNI {$jugador->dni_jugador}, Motivo: {$resultadoValidacion['message']}");
                }
            } else {
                $omitidos++;

                // Validar jugador omitido contra reglas administrativas
                $resultadoValidacion = $this->validadorReglas->validarJugador(
                    $jugador->dni_jugador,
                    $idEquipo,
                    $idEdicion,
                    $jugador->idCategoria,
                    $jugador->fecha_nacimiento
                );

                // Si tiene advertencias, marcarlo como conflictivo
                if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning') {
                    $created->conflictivo = true;
                    $created->motivo_conflicto = $resultadoValidacion['message'];
                    $created->resuelto = false;
                    $created->save();
                    $omitidosConflictivos++;

                    Log::info("Jugador omitido marcado como conflictivo: DNI {$jugador->dni_jugador}, Motivo: {$resultadoValidacion['message']}");
                }
            }
        }

        return [
            'migrados' => $migrados,
            'omitidos' => $omitidos,
            'omitidos_conflictivos' => $omitidosConflictivos,
        ];
    }

    private function aplicarEstadoConflicto(ListaBuenaFe $listaBuenaFe, $resultadoValidacion): void
    {
        if ($resultadoValidacion && $resultadoValidacion['type'] === 'warning') {
            $listaBuenaFe->conflictivo = true;
            $listaBuenaFe->motivo_conflicto = $resultadoValidacion['message'];
            $listaBuenaFe->resuelto = false;
            $listaBuenaFe->fecha_resolucion = null;
            $listaBuenaFe->resuelto_por = null;
            $listaBuenaFe->notas_resolucion = null;

            return;
        }

        $listaBuenaFe->conflictivo = false;
        $listaBuenaFe->motivo_conflicto = null;
        $listaBuenaFe->resuelto = false;
        $listaBuenaFe->fecha_resolucion = null;
        $listaBuenaFe->resuelto_por = null;
        $listaBuenaFe->notas_resolucion = null;
    }

    // NOTA: Método validarJugador() eliminado - ahora se usa ValidadorReglasJugador service

    /**
     * Reutiliza el armado del listado para que pantalla e impresión compartan
     * exactamente los mismos criterios de filtrado.
     */
    private function obtenerEquiposFiltrados($idEdicion, $idCategoria = null, $search = null)
    {
        if (! $idEdicion) {
            return collect();
        }

        $query = EquipoEdicion::with('equipo', 'categoria')
            ->where('idEdicion', $idEdicion);

        if ($idCategoria) {
            $query->where('idCategoria', $idCategoria);
        }

        if (! empty($search)) {
            $query->whereHas('equipo', function ($subQuery) use ($search) {
                $subQuery->where('nombre', 'LIKE', '%'.$search.'%');
            });
        }

        $equipos = $query->get();

        if ($equipos->isEmpty()) {
            return $equipos;
        }

        $jugadoresActivosPorEquipo = ListaBuenaFe::select('idEquipo', 'idCategoria', DB::raw('count(*) as total_activos'))
            ->where('idEdicion', $idEdicion)
            ->where('activo', true)
            ->groupBy('idEquipo', 'idCategoria')
            ->get()
            ->keyBy(function ($registro) {
                return $registro->idEquipo.'-'.$registro->idCategoria;
            });

        return $equipos
            ->map(function ($equipo) use ($jugadoresActivosPorEquipo) {
                $clave = $equipo->idEquipo.'-'.$equipo->idCategoria;
                $totalJugadoresActivos = (int) ($jugadoresActivosPorEquipo->get($clave)->total_activos ?? 0);

                $equipo->total_jugadores_activos = $totalJugadoresActivos;
                $equipo->sin_jugadores = $totalJugadoresActivos === 0;

                return $equipo;
            })
            ->sort(function ($equipoA, $equipoB) {
                if ($equipoA->sin_jugadores !== $equipoB->sin_jugadores) {
                    return $equipoA->sin_jugadores ? -1 : 1;
                }

                return strcasecmp($equipoA->equipo->nombre ?? '', $equipoB->equipo->nombre ?? '');
            })
            ->values();
    }

    /**
     * Define el límite de jugadores de la edición para unificar reglas de alta
     * y cantidad de filas que se muestran en las planillas de impresión.
     */
    private function obtenerLimiteJugadoresPorPlanilla($edicion)
    {
        if (! $edicion) {
            return 18;
        }

        $limite = (int) ($edicion->jugadores_por_planilla ?? 18);

        return $limite > 0 ? $limite : 18;
    }
}
