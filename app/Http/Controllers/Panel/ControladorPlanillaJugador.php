<?php

namespace App\Http\Controllers\Panel;

use App\Constants\PartidoConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarPlanillaJugadoresRequest;
use App\Http\Requests\Panel\AplicarGolesArbitralesRequest;
use App\Http\Requests\Panel\CopiarPlanillaPartidoAnteriorRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EquipoEdicion;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use App\Services\AplicarGolesArbitralesService;
use App\Services\BajaJugadorService;
use App\Services\CopiarPlanillaPartidoAnteriorService;
use App\Services\GolesArbitrosService;
use App\Services\PlanillaConfirmacionService;
use App\Services\PlanillaJugadorAutosaveService;
use App\Services\ValidadorReglasJugador;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControladorPlanillaJugador extends Controller
{
    protected $validadorReglas;

    public function __construct(
        ValidadorReglasJugador $validadorReglas,
        private readonly PlanillaConfirmacionService $planillaConfirmacionService,
        private readonly GolesArbitrosService $golesArbitrosService,
        private readonly AplicarGolesArbitralesService $aplicarGolesArbitralesService,
        private readonly BajaJugadorService $bajaJugadorService,
        private readonly PlanillaJugadorAutosaveService $planillaJugadorAutosaveService,
        private readonly CopiarPlanillaPartidoAnteriorService $copiarPlanillaPartidoAnteriorService,
    ) {
        $this->validadorReglas = $validadorReglas;
    }

    /**
     * Muestra la planilla del partido preservando el contexto de navegación.
     *
     * Mantener estos parámetros evita que el usuario pierda filtros al volver
     * al listado de partidos o instancias finales.
     */
    public function mostrarPlanilla(Request $request, $partidoId, $idEdicion, $tipoPartido, $horario)
    {
        $ediciones = Edicion::all();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $horarioSeleccionado = $horario;
        $contextoNavegacion = $this->obtenerContextoNavegacion($request);

        // Obtener el partido o instancia final
        if (PartidoConstants::isInstanciaFinal($tipoPartido)) {
            $partido = InstanciaFinal::findOrFail($partidoId);
        } else {
            $partido = Partido::findOrFail($partidoId);
        }

        // Verificar si ya existen jugadores en la planilla (local o visitante)
        $planillaYaExiste = PlanillaJugador::where('partido_id', $partidoId)
            ->where('idEdicion', $idEdicion)
            ->exists();

        // Sincronizar planilla con Lista de Buena Fe (Altas y Bajas)
        $this->sincronizarPlanilla($partidoId, $partido->idEquipoLocal, $idEdicion, $tipoPartido);
        $this->sincronizarPlanilla($partidoId, $partido->idEquipoVisitante, $idEdicion, $tipoPartido);

        // Obtener la planilla para mostrar los jugadores locales
        $jugadoresLocalPlanilla = PlanillaJugador::where('partido_id', $partidoId)
            ->where('idEquipo', $partido->idEquipoLocal)
            ->where('idEdicion', $idEdicion)
            ->join('jugadores', 'planilla_jugadores.dni_jugador', '=', 'jugadores.dni')
            ->select('planilla_jugadores.*', 'jugadores.nombre', 'jugadores.apellido')
            ->get();

        // Obtener la planilla para mostrar los jugadores visitantes
        $jugadoresVisitantePlanilla = PlanillaJugador::where('partido_id', $partidoId)
            ->where('idEquipo', $partido->idEquipoVisitante)
            ->where('idEdicion', $idEdicion)
            ->join('jugadores', 'planilla_jugadores.dni_jugador', '=', 'jugadores.dni')
            ->select('planilla_jugadores.*', 'jugadores.nombre', 'jugadores.apellido')
            ->get();

        $this->golesArbitrosService->sincronizarVinculaciones($tipoPartido, (int) $partidoId, (int) $idEdicion);

        $golesArbitrosResumen = $this->golesArbitrosService->obtenerResumenPorEquipos(
            $tipoPartido,
            (int) $partidoId,
            (int) $idEdicion,
            (int) $partido->idEquipoLocal,
            (int) $partido->idEquipoVisitante,
        );

        $candidatosArbitrales = [
            'local' => $this->golesArbitrosService->obtenerCandidatosAplicables(
                (string) $tipoPartido,
                (int) $partidoId,
                (int) $idEdicion,
                (int) $partido->idEquipoLocal,
            ),
            'visitante' => $this->golesArbitrosService->obtenerCandidatosAplicables(
                (string) $tipoPartido,
                (int) $partidoId,
                (int) $idEdicion,
                (int) $partido->idEquipoVisitante,
            ),
        ];

        $equipoBloqueado = [
            'local' => $this->obtenerDetalleBloqueoEquipo((string) $tipoPartido, (int) $partidoId, (int) $idEdicion, (int) $partido->idEquipoLocal),
            'visitante' => $this->obtenerDetalleBloqueoEquipo((string) $tipoPartido, (int) $partidoId, (int) $idEdicion, (int) $partido->idEquipoVisitante),
        ];

        $disponibilidadCopiado = $this->copiarPlanillaPartidoAnteriorService->obtenerDisponibilidadCopiadoPorEquipo(
            (string) $tipoPartido,
            (int) $partidoId,
            (int) $idEdicion,
        );

        $mostrarBotonCopiarLocal = $disponibilidadCopiado['local'];
        $mostrarBotonCopiarVisitante = $disponibilidadCopiado['visitante'];

        return view('Panel.planilla.show', compact('partido', 'jugadoresLocalPlanilla', 'jugadoresVisitantePlanilla', 'ediciones', 'EdicionSeleccionada', 'tipoPartido', 'horarioSeleccionado', 'contextoNavegacion', 'golesArbitrosResumen', 'candidatosArbitrales', 'equipoBloqueado', 'mostrarBotonCopiarLocal', 'mostrarBotonCopiarVisitante'));
    }

    /**
     * @return array{bloqueado: bool, detalle: string}
     */
    private function obtenerDetalleBloqueoEquipo(string $tipoPartido, int $partidoId, int $idEdicion, int $idEquipo): array
    {
        $jugadoresConGoles = PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', PartidoConstants::getModelClass($tipoPartido))
            ->where('idEdicion', $idEdicion)
            ->where('idEquipo', $idEquipo)
            ->where('goles', '>', 0)
            ->leftJoin('jugadores', 'jugadores.dni', '=', 'planilla_jugadores.dni_jugador')
            ->orderByDesc('goles')
            ->get(['planilla_jugadores.numero_camiseta', 'planilla_jugadores.goles', 'jugadores.nombre', 'jugadores.apellido']);

        if ($jugadoresConGoles->isEmpty()) {
            return [
                'bloqueado' => false,
                'detalle' => '',
            ];
        }

        $detalle = $jugadoresConGoles
            ->map(static function (PlanillaJugador $planilla): string {
                $camiseta = $planilla->numero_camiseta ?? '-';
                $goles = (int) $planilla->goles;
                $apellido = trim((string) ($planilla->apellido ?? ''));
                $nombre = trim((string) ($planilla->nombre ?? ''));

                if ($apellido !== '' || $nombre !== '') {
                    $nombreCompleto = trim($apellido.($apellido !== '' && $nombre !== '' ? ', ' : '').$nombre);

                    return 'Camiseta '.$camiseta.' — '.$nombreCompleto.': '.$goles.' gol(es) oficiales';
                }

                return 'Camiseta '.$camiseta.': '.$goles.' gol(es) oficiales';
            })
            ->implode(', ');

        return [
            'bloqueado' => true,
            'detalle' => $detalle,
        ];
    }

    public function actualizarGolesArbitros(Request $request)
    {
        $datosValidados = $request->validate([
            'partido_id' => ['required', 'integer'],
            'idEdicion' => ['required', 'integer'],
            'tipoPartido' => ['required', 'string'],
            'idEquipo' => ['required', 'integer'],
            'reportes' => ['nullable', 'array'],
            'reportes.*.numero_camiseta' => ['nullable', 'integer', 'min:0', 'max:999'],
            'reportes.*.goles_reportados' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $this->golesArbitrosService->guardarReportes(
            $datosValidados['tipoPartido'],
            (int) $datosValidados['partido_id'],
            (int) $datosValidados['idEdicion'],
            (int) $datosValidados['idEquipo'],
            $datosValidados['reportes'] ?? [],
        );

        $resultadoAplicacion = $this->aplicarGolesArbitralesService->aplicarPorEquipo(
            (string) $datosValidados['tipoPartido'],
            (int) $datosValidados['partido_id'],
            (int) $datosValidados['idEdicion'],
            (int) $datosValidados['idEquipo'],
        );

        $mensajeEstado = match ($resultadoAplicacion->status) {
            'ok' => 'Goles arbitrales guardados y aplicados sobre la planilla oficial del equipo.',
            'bloqueado' => 'Goles arbitrales guardados. No se aplicaron goles a la planilla oficial porque el equipo ya tiene goles oficiales cargados.',
            default => 'Goles arbitrales guardados. No hubo cambios aplicables en la planilla oficial.',
        };

        $redirect = $this->redirigirAPlanilla($request)
            ->with('status', $mensajeEstado);

        if ($resultadoAplicacion->advertencia !== null) {
            $redirect->with('warning', $resultadoAplicacion->advertencia);
        }

        return $redirect;
    }

    public function aplicarGolesArbitrales(AplicarGolesArbitralesRequest $request): RedirectResponse
    {
        $resultado = $this->aplicarGolesArbitralesService->aplicarPorEquipo(
            (string) $request->input('tipoPartido'),
            (int) $request->input('partido_id'),
            (int) $request->input('idEdicion'),
            (int) $request->input('idEquipo'),
        );

        $redirect = $this->redirigirAPlanilla($request)->with('status', $resultado->message);

        if ($resultado->advertencia !== null) {
            $redirect->with('warning', $resultado->advertencia);
        }

        return $redirect;
    }

    /**
     * Método auxiliar para auto-poblar la planilla de un equipo desde la Lista de Buena Fe
     *
     * @param  int  $partidoId  ID del partido actual
     * @param  int  $equipoId  ID del equipo a poblar
     * @param  int  $idEdicion  ID de la edición
     * @param  string  $tipoPartido  Tipo de partido (normal o instanciaFinal)
     * @param  mixed  $partido  Objeto del partido (Partido o InstanciaFinal)
     */
    private function autoPoblarPlanilla($partidoId, $equipoId, $idEdicion, $tipoPartido, $partido)
    {
        try {
            // Verificar si ya existen jugadores cargados para este equipo en este partido
            $jugadoresExistentes = PlanillaJugador::where('partido_id', $partidoId)
                ->where('idEquipo', $equipoId)
                ->where('idEdicion', $idEdicion)
                ->pluck('dni_jugador')
                ->toArray();

            // Si ya hay jugadores, no hacer nada (evitar duplicados)
            if (! empty($jugadoresExistentes)) {
                Log::info("Planilla ya existe para partido {$partidoId}, equipo {$equipoId}");

                return;
            }

            // Obtener jugadores ACTIVOS de la Lista de Buena Fe
            $jugadoresBuenaFe = ListaBuenaFe::with('jugador')
                ->deEquipo($equipoId, $idEdicion)
                ->activos()
                ->where('idCategoria', $partido->idCategoria)
                ->get();

            // Si no hay jugadores en la lista de buena fe, intentar migrar desde el último partido
            if ($jugadoresBuenaFe->isEmpty()) {
                Log::warning("No hay jugadores en Lista de Buena Fe para equipo {$equipoId}. Considera migrar jugadores existentes.");

                return;
            }

            // Log de información
            Log::info("Auto-poblando planilla para partido {$partidoId}, equipo {$equipoId}. Encontrados ".$jugadoresBuenaFe->count().' jugadores en Lista de Buena Fe');

            // Insertar jugadores en la planilla
            $jugadoresInsertados = 0;
            $jugadoresSuspendidos = 0;

            foreach ($jugadoresBuenaFe as $listaBuenaFe) {
                $jugadorSuspendido = $listaBuenaFe->estaSuspendido();
                if ($jugadorSuspendido) {
                    Log::info("Jugador {$listaBuenaFe->dni_jugador} está suspendido, se agrega a la planilla con roja marcada");
                    $jugadoresSuspendidos++;
                }

                // Validar que el jugador y sus datos existan
                if (! $listaBuenaFe->jugador || empty($listaBuenaFe->fecha_nacimiento) || empty($listaBuenaFe->idCategoria)) {
                    Log::warning("Jugador {$listaBuenaFe->dni_jugador} tiene datos incompletos en Lista de Buena Fe, se omite");

                    continue;
                }

                // Insertar en la planilla
                try {
                    PlanillaJugador::create([
                        'partido_id' => $partidoId,
                        'partido_type' => PartidoConstants::getModelClass($tipoPartido),
                        'dni_jugador' => $listaBuenaFe->dni_jugador,
                        'fecha_nacimiento' => $listaBuenaFe->fecha_nacimiento,
                        'idEquipo' => $equipoId,
                        'idEdicion' => $idEdicion,
                        'idCategoria' => $partido->idCategoria,
                        'numero_camiseta' => $listaBuenaFe->numero_camiseta ?? 0,
                        'goles' => 0,
                        'asistio' => false,
                        'tarjeta_roja' => $jugadorSuspendido,
                    ]);
                    $jugadoresInsertados++;
                } catch (\Exception $e) {
                    Log::error("Error insertando jugador {$listaBuenaFe->dni_jugador}: ".$e->getMessage());
                }
            }

            Log::info("Planilla partido {$partidoId}: {$jugadoresInsertados} jugadores insertados, {$jugadoresSuspendidos} suspendidos marcados con roja");

        } catch (\Exception $e) {
            // Log del error pero no interrumpir la ejecución
            Log::error("Error en autoPoblarPlanilla para partido {$partidoId}, equipo {$equipoId}: ".$e->getMessage());
        }
    }

    /**
     * Agregar jugador manualmente a planilla.
     *
     * Flujo simplificado:
     * - Bloquea solo validaciones duras (type=error).
     * - Si hay warning administrativo, permite alta y marca conflicto en Lista de Buena Fe.
     */
    public function agregarJugador(Request $request)
    {
        $idEdicion = $request->idEdicion;
        $tipoPartido = $request->tipoPartido;
        $horarioSeleccionado = $request->horario;
        $fecha_nacimiento = $request->fecha_nacimiento;
        $partidoId = $request->partido_id;

        // Validar número de camiseta único en el equipo para este partido
        if ($request->numero_camiseta) {
            $numeroDuplicado = PlanillaJugador::where('partido_id', $partidoId)
                ->where('idEquipo', $request->equipo_id)
                ->where('numero_camiseta', $request->numero_camiseta)
                ->exists();

            if ($numeroDuplicado) {
                return redirect()->back()->with('status', 'El número de camiseta '.$request->numero_camiseta.' ya está en uso en este equipo para este partido.');
            }
        }

        // 2. Obtener Equipo y Categoría (Necesarios para validación)
        $equipo = EquipoEdicion::where('idEquipo', $request->equipo_id)
            ->where('idEdicion', $idEdicion)
            ->first();

        if (! $equipo) {
            return redirect()->back()->with('status', 'Equipo no encontrado.');
        }

        // 3. Validar Reglas de Negocio (Servicio)
        $resultadoValidacion = $this->validadorReglas->validarJugador(
            $request->dni_jugador,
            $request->equipo_id,
            $idEdicion,
            $equipo->idCategoria,
            $fecha_nacimiento
        );

        $motivoConflicto = null;

        if ($resultadoValidacion) {
            if ($resultadoValidacion['type'] === 'error') {
                return redirect()->back()->with('status', $resultadoValidacion['message']);
            }

            if ($resultadoValidacion['type'] === 'warning') {
                $motivoConflicto = $resultadoValidacion['message'];
            }
        }

        // 4. Crear o buscar Jugador
        $jugador = Jugador::firstOrCreate(
            ['dni' => $request->dni_jugador],
            [
                'nombre' => $request->nombre_jugador,
                'apellido' => $request->apellido_jugador,
                'fecha_nacimiento' => $fecha_nacimiento,
            ]
        );

        // Agregar el jugador a la planilla
        $nuevaPlanilla = PlanillaJugador::create([
            'partido_id' => $partidoId,
            'partido_type' => PartidoConstants::getModelClass($tipoPartido),
            'dni_jugador' => $jugador->dni,
            'idEquipo' => $equipo->idEquipo,
            'idEdicion' => $idEdicion,
            'numero_camiseta' => $request->numero_camiseta,
            'fecha_nacimiento' => $fecha_nacimiento,
            'idCategoria' => $equipo->idCategoria,
            'goles' => 0,
            'asistio' => false,
            'tarjeta_roja' => isset($request->tarjeta_roja),
        ]);

        // Si se agregó con roja marcada, incrementar el total histórico
        if ($nuevaPlanilla->tarjeta_roja) {
            $jugador->tarjetas_rojas_totales = ($jugador->tarjetas_rojas_totales ?? 0) + 1;
            $jugador->save();
        }

        // --- Sincronización con Lista de Buena Fe ---
        try {
            $limiteJugadores = $this->obtenerLimiteJugadoresPorEdicion($idEdicion, $equipo->idCategoria ?? null);

            // Verificar si ya existe en la lista (activo o inactivo)
            $listaBuenaFe = ListaBuenaFe::where('dni_jugador', $jugador->dni)
                ->where('idEquipo', $equipo->idEquipo)
                ->where('idEdicion', $idEdicion)
                ->first();

            if ($listaBuenaFe) {
                // Si existe pero está inactivo, reactivarlo
                if (! $listaBuenaFe->activo) {
                    // Verificar límite antes de reactivar
                    $cantidadActivos = ListaBuenaFe::where('idEquipo', $equipo->idEquipo)
                        ->where('idEdicion', $idEdicion)
                        ->where('activo', true)
                        ->count();

                    if ($cantidadActivos < $limiteJugadores) {
                        $listaBuenaFe->reactivar();
                        // Actualizar datos si cambiaron
                        $listaBuenaFe->fecha_nacimiento = $fecha_nacimiento;
                        $listaBuenaFe->idCategoria = $equipo->idCategoria;
                        if ($motivoConflicto) {
                            $listaBuenaFe->conflictivo = true;
                            $listaBuenaFe->motivo_conflicto = $motivoConflicto;
                            $listaBuenaFe->resuelto = false;
                            $listaBuenaFe->fecha_resolucion = null;
                            $listaBuenaFe->resuelto_por = null;
                            $listaBuenaFe->notas_resolucion = null;
                        }
                        $listaBuenaFe->save();
                    } else {
                        Log::warning('Jugador agregado a planilla pero Lista de Buena Fe llena (límite edición: '.$limiteJugadores.'). No se pudo reactivar en la lista.');
                    }
                } else {
                    // Ya está activo, actualizar datos
                    $listaBuenaFe->fecha_nacimiento = $fecha_nacimiento;
                    $listaBuenaFe->idCategoria = $equipo->idCategoria;
                    if ($motivoConflicto) {
                        $listaBuenaFe->conflictivo = true;
                        $listaBuenaFe->motivo_conflicto = $motivoConflicto;
                        $listaBuenaFe->resuelto = false;
                        $listaBuenaFe->fecha_resolucion = null;
                        $listaBuenaFe->resuelto_por = null;
                        $listaBuenaFe->notas_resolucion = null;
                    }
                    $listaBuenaFe->save();
                }
            } else {
                // No existe, crear nuevo
                // Verificar límite
                $cantidadActivos = ListaBuenaFe::where('idEquipo', $equipo->idEquipo)
                    ->where('idEdicion', $idEdicion)
                    ->where('activo', true)
                    ->count();

                if ($cantidadActivos < $limiteJugadores) {
                    ListaBuenaFe::create([
                        'dni_jugador' => $jugador->dni,
                        'idEquipo' => $equipo->idEquipo,
                        'idEdicion' => $idEdicion,
                        'idCategoria' => $equipo->idCategoria,
                        'fecha_nacimiento' => $fecha_nacimiento,
                        'fecha_alta' => now(),
                        'activo' => true,
                        'conflictivo' => ! is_null($motivoConflicto),
                        'motivo_conflicto' => $motivoConflicto,
                    ]);
                } else {
                    Log::warning('Jugador agregado a planilla pero Lista de Buena Fe llena (límite edición: '.$limiteJugadores.'). No se pudo agregar a la lista.');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error sincronizando Lista de Buena Fe al agregar en planilla: '.$e->getMessage());
        }

        $mensajeStatus = 'Jugador agregado a la planilla con éxito.';
        if (! is_null($motivoConflicto)) {
            $mensajeStatus .= ' (Marcado como CONFLICTIVO en Lista de Buena Fe).';
        }

        if (PartidoConstants::isInstanciaFinal($tipoPartido)) {
            return redirect()->route('planilla.show', array_merge([
                'partidoId' => $partidoId,
                'idEdicion' => $idEdicion,
                'tipoPartido' => $tipoPartido,
                'horario' => 0,
            ], $this->obtenerContextoNavegacion($request)))->with('status', $mensajeStatus);
        } else {
            return redirect()->route('planilla.show', array_merge([
                'partidoId' => $partidoId,
                'idEdicion' => $idEdicion,
                'tipoPartido' => $tipoPartido,
                'horario' => $horarioSeleccionado,
            ], $this->obtenerContextoNavegacion($request)))->with('status', $mensajeStatus);
        }
    }

    public function actualizarJugadores(ActualizarPlanillaJugadoresRequest $request): JsonResponse|RedirectResponse
    {
        $datosValidados = $request->validated();
        $tipoPartido = (string) $datosValidados['tipoPartido'];
        $resultado = $this->planillaJugadorAutosaveService->actualizarJugadores(
            $tipoPartido,
            (int) $datosValidados['partido_id'],
            (int) $datosValidados['idEdicion'],
            $datosValidados['jugadores'],
        );

        if ($resultado['huboCambiosReales']) {
            $this->golesArbitrosService->sincronizarVinculaciones(
                $tipoPartido,
                (int) $datosValidados['partido_id'],
                (int) $datosValidados['idEdicion'],
            );
        }

        if ($request->wantsJson()) {
            $statusCode = 200;

            if ($resultado['status'] === 'conflict') {
                $statusCode = 409;
            } elseif ($resultado['status'] === 'error') {
                $statusCode = 500;
            }

            return response()->json($resultado, $statusCode);
        }

        $mensaje = 'Jugadores actualizados correctamente.';

        if ($resultado['status'] === 'error') {
            return $this->redirigirAPlanilla($request)
                ->with('error', $resultado['message']);
        }

        if ($resultado['status'] === 'conflict') {
            return $this->redirigirAPlanilla($request)
                ->with('warning', $resultado['message']);
        }

        if ($resultado['confirmacionInvalidada']) {
            $mensaje .= ' La confirmacion de goles se removio porque la planilla fue modificada.';
        }

        return $this->redirigirAPlanilla($request)
            ->with('status', $mensaje);
    }

    public function copiarPlanillaPartidoAnterior(CopiarPlanillaPartidoAnteriorRequest $request): JsonResponse|RedirectResponse
    {
        $datosValidados = $request->validated();
        $resultado = $this->copiarPlanillaPartidoAnteriorService->copiarPlanillaPartidoAnterior(
            (string) $datosValidados['tipoPartido'],
            (int) $datosValidados['partido_id'],
            (int) $datosValidados['idEdicion'],
            (string) $datosValidados['scope'],
            $datosValidados['updated_at'] ?? null,
        );

        if ($request->wantsJson()) {
            $statusCode = 200;

            if ($resultado['status'] === 'no_previous_match') {
                $statusCode = 404;
            } elseif ($resultado['status'] === 'ambiguous_previous_match') {
                $statusCode = 409;
            } elseif ($resultado['status'] === 'error') {
                $statusCode = 500;
            }

            return response()->json($resultado, $statusCode);
        }

        if ($resultado['status'] !== 'ok') {
            return $this->redirigirAPlanilla($request)
                ->with($resultado['status'] === 'no_previous_match' ? 'error' : 'warning', $resultado['message']);
        }

        return $this->redirigirAPlanilla($request)
            ->with('status', $resultado['message']);
    }

    public function confirmarGoles(Request $request)
    {
        $advertenciaGoles = $this->validarGolesCruzados(
            (int) $request->partido_id,
            PartidoConstants::getModelClass($request->tipoPartido),
        );

        $this->planillaConfirmacionService->confirmarGoles(
            $request->tipoPartido,
            (int) $request->partido_id,
            (int) $request->idEdicion,
        );

        $redirect = $this->redirigirAPlanilla($request)
            ->with('status', 'Goles confirmados correctamente.');

        if ($advertenciaGoles !== null) {
            $redirect = $redirect->with('warning', $advertenciaGoles);
        }

        return $redirect;
    }

    public function quitarConfirmacionGoles(Request $request)
    {
        $this->planillaConfirmacionService->quitarConfirmacion(
            $request->tipoPartido,
            (int) $request->partido_id,
            (int) $request->idEdicion,
        );

        return $this->redirigirAPlanilla($request)
            ->with('status', 'Se quito la confirmacion de goles.');
    }

    /**
     * Compara la suma de goles individuales de planilla con el resultado oficial del partido.
     * Retorna un mensaje de advertencia si hay discrepancia, o null si todo coincide.
     */
    private function validarGolesCruzados(int $partidoId, string $partidoType): ?string
    {
        try {
            $partido = $partidoType::find($partidoId);

            if (! $partido || $partido->golesEquipoLocal === null || $partido->golesEquipoVisitante === null) {
                return null;
            }

            $sumasPorEquipo = PlanillaJugador::where('partido_id', $partidoId)
                ->where('partido_type', $partidoType)
                ->selectRaw('idEquipo, SUM(goles) as total_goles')
                ->groupBy('idEquipo')
                ->pluck('total_goles', 'idEquipo');

            $sumaLocal = (int) ($sumasPorEquipo[$partido->idEquipoLocal] ?? 0);
            $sumaVisitante = (int) ($sumasPorEquipo[$partido->idEquipoVisitante] ?? 0);
            $oficialLocal = (int) $partido->golesEquipoLocal;
            $oficialVisitante = (int) $partido->golesEquipoVisitante;

            if ($sumaLocal === $oficialLocal && $sumaVisitante === $oficialVisitante) {
                return null;
            }

            $detalles = [];

            if ($sumaLocal !== $oficialLocal) {
                $detalles[] = "Local: resultado oficial {$oficialLocal}, suma individual {$sumaLocal}";
            }

            if ($sumaVisitante !== $oficialVisitante) {
                $detalles[] = "Visitante: resultado oficial {$oficialVisitante}, suma individual {$sumaVisitante}";
            }

            return 'Atención: la suma de goles individuales no coincide con el resultado oficial del partido. '
                .implode('. ', $detalles).'.';
        } catch (\Throwable $e) {
            Log::warning('Error en validación cruzada de goles: '.$e->getMessage(), [
                'partido_id' => $partidoId,
                'partido_type' => $partidoType,
            ]);

            return null;
        }
    }

    private function planillaTieneCambiosReales(PlanillaJugador $planilla, array $datosActualizados): bool
    {
        return (int) $planilla->goles !== (int) ($datosActualizados['goles'] ?? $planilla->goles)
            || (bool) $planilla->asistio !== (bool) ($datosActualizados['asistencia'] ?? $planilla->asistio)
            || (int) $planilla->numero_camiseta !== (int) ($datosActualizados['numero_camiseta'] ?? $planilla->numero_camiseta)
            || (bool) $planilla->tarjeta_roja !== (bool) ($datosActualizados['tarjeta_roja'] ?? $planilla->tarjeta_roja);
    }

    private function redirigirAPlanilla(Request $request)
    {
        return redirect()->route('planilla.show', array_merge([
            'partidoId' => $request->partido_id,
            'idEdicion' => $request->idEdicion,
            'tipoPartido' => $request->tipoPartido,
            'horario' => PartidoConstants::isInstanciaFinal($request->tipoPartido) ? 0 : $request->horario,
        ], $this->obtenerContextoNavegacion($request)));
    }

    public function generarReportePDF($equipoId, $idEdicion, $partidoId)
    {
        // Obtener los jugadores únicos del equipo en la edición especificada
        $jugadores = PlanillaJugador::where('idEquipo', $equipoId)
            ->where('idEdicion', $idEdicion)
            ->join('jugadores', 'planilla_jugadores.dni_jugador', '=', 'jugadores.dni')
            ->select(
                'jugadores.dni',
                'jugadores.nombre',
                'jugadores.apellido',
                'planilla_jugadores.fecha_nacimiento'
            )
            ->distinct() // Eliminar duplicados
            ->get();

        // Obtener la categoría del partido
        $nombreCategoria = Partido::where('partidos.id', $partidoId)
            ->leftJoin('categorias', 'partidos.idCategoria', '=', 'categorias.id')
            ->select('categorias.nombreCategoria as nombre')
            ->first();

        // Obtener información del equipo
        $equipo = EquipoEdicion::where('equipo_ediciones.idEquipo', $equipoId)
            ->leftJoin('equipos', 'equipo_ediciones.idEquipo', '=', 'equipos.id')
            ->leftJoin('ediciones', 'equipo_ediciones.idEdicion', '=', 'ediciones.id')
            ->select('equipos.nombre', 'equipos.foto', 'equipo_ediciones.*')
            ->first();

        // Generar el PDF
        $pdf = Pdf::loadView('Panel.reporte.jugadores', compact('jugadores', 'equipo', 'nombreCategoria'));

        // Ver PDF en el navegador
        return $pdf->stream('reporte_jugadores_equipo_'.$equipo->nombre.'_categoria_'.$nombreCategoria->nombre.'.pdf', ['Attachment' => false]);
    }

    /**
     * Eliminar un jugador de la planilla de un partido específico
     *
     * @return RedirectResponse
     */
    public function eliminarJugador(Request $request)
    {
        $dniJugador = $request->dni_jugador;
        $partidoId = $request->partido_id;
        $idEdicion = $request->idEdicion;
        $tipoPartido = $request->tipoPartido;
        $horarioSeleccionado = $request->horario;

        // Determinar el tipo de partido correcto
        $partidoType = PartidoConstants::getModelClass($tipoPartido);

        // Buscar el registro en la planilla con el tipo de partido
        $planilla = PlanillaJugador::where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('dni_jugador', $dniJugador)
            ->first();

        if (! $planilla) {
            return redirect()->back()->with('error', 'No se encontró el jugador (DNI: '.$dniJugador.') en la planilla del partido '.$partidoId);
        }

        // Obtener el equipo del jugador para esta edición
        $idEquipo = $planilla->idEquipo;
        $idCategoria = $planilla->idCategoria;

        $listaBuenaFe = ListaBuenaFe::where('dni_jugador', $dniJugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->first();

        if ($listaBuenaFe) {
            $this->bajaJugadorService->procesarBaja(
                $listaBuenaFe,
                'Eliminado desde planilla del partido '.$partidoId,
                auth()->user()->name ?? auth()->user()->email ?? 'Sistema'
            );

            Log::info("Jugador {$dniJugador} dado de baja de Lista de Buena Fe por eliminación en planilla.");
        } else {
            // Obtener TODAS las planillas del jugador en este equipo y edición
            $todasLasPlanillas = PlanillaJugador::where('dni_jugador', $dniJugador)
                ->where('idEquipo', $idEquipo)
                ->where('idEdicion', $idEdicion)
                ->get();

            // Calcular estadísticas totales a restar del jugador
            $totalGoles = $todasLasPlanillas->sum('goles');
            $totalPartidos = $todasLasPlanillas->where('asistio', 1)->count();
            $totalTarjetasRojas = $todasLasPlanillas->where('tarjeta_roja', 1)->count();

            // Actualizar estadísticas globales del jugador
            $jugador = Jugador::where('dni', $dniJugador)->first();
            if ($jugador) {
                $jugador->goles_totales = max(0, ($jugador->goles_totales ?? 0) - $totalGoles);
                $jugador->partidos_totales = max(0, ($jugador->partidos_totales ?? 0) - $totalPartidos);
                $jugador->tarjetas_rojas_totales = max(0, ($jugador->tarjetas_rojas_totales ?? 0) - $totalTarjetasRojas);
                $jugador->save();
            }

            // Eliminar completamente de la tabla de goleadores de esta edición
            TablaGoleador::where('dni_jugador', $dniJugador)
                ->where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->delete();

            // Eliminar TODAS las planillas del jugador en este equipo y edición
            PlanillaJugador::where('dni_jugador', $dniJugador)
                ->where('idEquipo', $idEquipo)
                ->where('idEdicion', $idEdicion)
                ->delete();
        }

        if (PartidoConstants::isInstanciaFinal($tipoPartido)) {
            return redirect()->route('planilla.show', array_merge([
                'partidoId' => $partidoId,
                'idEdicion' => $idEdicion,
                'tipoPartido' => $tipoPartido,
                'horario' => 0,
            ], $this->obtenerContextoNavegacion($request)))->with('status', 'Jugador eliminado completamente del equipo en esta edición.');
        } else {
            return redirect()->route('planilla.show', array_merge([
                'partidoId' => $partidoId,
                'idEdicion' => $idEdicion,
                'tipoPartido' => $tipoPartido,
                'horario' => $horarioSeleccionado,
            ], $this->obtenerContextoNavegacion($request)))->with('status', 'Jugador eliminado completamente del equipo en esta edición.');
        }
    }

    /**
     * Recupera los parámetros de navegación para sostener el mismo contexto.
     *
     * @return array<string, mixed>
     */
    private function obtenerContextoNavegacion(Request $request): array
    {
        $contexto = [
            'origen' => $request->input('origen') ?? $request->query('origen'),
            'idCategoria' => $request->input('idCategoria') ?? $request->query('idCategoria'),
            'idGrupo' => $request->input('idGrupo') ?? $request->query('idGrupo'),
            'idFase' => $request->input('idFase') ?? $request->query('idFase'),
            'idCopa' => $request->input('idCopa') ?? $request->query('idCopa'),
            'estado' => $request->input('estado') ?? $request->query('estado'),
            'idFecha' => $request->input('idFecha') ?? $request->query('idFecha'),
            'fecha' => $request->input('fecha') ?? $request->query('fecha'),
            'horario_filtro' => $request->input('horario_filtro') ?? $request->query('horario_filtro'),
            'busqueda' => $request->input('busqueda') ?? $request->query('busqueda'),
        ];

        return array_filter($contexto, static fn ($valor) => $valor !== null && $valor !== '');
    }

    /**
     * Limpia todas las estadísticas y planillas de un jugador dado de baja.
     * Replica la lógica de eliminarJugador() para mantener consistencia.
     */
    private function limpiarEstadisticasJugadorBaja(
        string $dniJugador,
        int $idEquipo,
        int $idEdicion,
        int $idCategoria
    ): void {
        $listaBuenaFe = ListaBuenaFe::where('dni_jugador', $dniJugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->first();

        if ($listaBuenaFe) {
            $this->bajaJugadorService->procesarBaja(
                $listaBuenaFe,
                $listaBuenaFe->motivo_baja,
                auth()->user()->name ?? auth()->user()->email ?? 'Sistema'
            );

            return;
        }

        // 1. Obtener TODAS las planillas del jugador en este equipo y edición
        $todasLasPlanillas = PlanillaJugador::where('dni_jugador', $dniJugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->get();

        if ($todasLasPlanillas->isEmpty()) {
            return;
        }

        // 2. Calcular estadísticas totales a restar
        $totalGoles = $todasLasPlanillas->sum('goles');
        $totalPartidos = $todasLasPlanillas->where('asistio', 1)->count();
        $totalTarjetasRojas = $todasLasPlanillas->where('tarjeta_roja', 1)->count();

        // 3. Revertir estadísticas globales del jugador
        $jugador = Jugador::where('dni', $dniJugador)->first();
        if ($jugador) {
            $jugador->goles_totales = max(0, ($jugador->goles_totales ?? 0) - $totalGoles);
            $jugador->partidos_totales = max(0, ($jugador->partidos_totales ?? 0) - $totalPartidos);
            $jugador->tarjetas_rojas_totales = max(0, ($jugador->tarjetas_rojas_totales ?? 0) - $totalTarjetasRojas);
            $jugador->save();
        } else {
            Log::warning("Jugador con DNI {$dniJugador} no encontrado al limpiar estadísticas por baja");
        }

        // 4. Eliminar registro de tabla de goleadores
        TablaGoleador::where('dni_jugador', $dniJugador)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->delete();

        // 5. Eliminar TODAS las planillas del jugador en este equipo/edición
        PlanillaJugador::where('dni_jugador', $dniJugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->delete();

        Log::info('Baja sincronización: eliminadas estadísticas y planillas', [
            'dni_jugador' => $dniJugador,
            'idEquipo' => $idEquipo,
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'goles_revertidos' => $totalGoles,
            'partidos_revertidos' => $totalPartidos,
            'tarjetas_revertidas' => $totalTarjetasRojas,
            'planillas_eliminadas' => $todasLasPlanillas->count(),
        ]);
    }

    /**
     * Sincroniza la planilla del partido con la Lista de Buena Fe.
     *
     * Reglas del flujo simplificado:
     * - Agrega solo jugadores activos de la categoría real del equipo en la edición.
     * - Si el jugador viene suspendido por roja previa, se agrega igual pero con
     *   `tarjeta_roja` marcada para visibilidad y control administrativo.
     * - Elimina de planilla a quienes no estén activos/categorizados correctamente.
     */
    private function sincronizarPlanilla($partidoId, $equipoId, $idEdicion, $tipoPartido)
    {
        try {
            $equipoEdicion = EquipoEdicion::where('idEquipo', $equipoId)
                ->where('idEdicion', $idEdicion)
                ->first();

            if (! $equipoEdicion) {
                Log::warning("No se encontró equipo_ediciones para Equipo {$equipoId} y Edición {$idEdicion}");

                return;
            }

            // 1. Obtener jugadores de la Lista de Buena Fe (activos e inactivos para comparar).
            $jugadoresBuenaFe = ListaBuenaFe::where('idEquipo', $equipoId)
                ->where('idEdicion', $idEdicion)
                ->get();

            // Solo se consideran activos que pertenezcan a la categoría del equipo.
            $jugadoresActivosCategoria = $jugadoresBuenaFe->filter(function ($jugador) use ($equipoEdicion) {
                return $jugador->activo && (int) $jugador->idCategoria === (int) $equipoEdicion->idCategoria;
            });

            $activosDni = $jugadoresActivosCategoria->pluck('dni_jugador')->toArray();

            // 2. Obtener jugadores actuales en la planilla
            $planillaActual = PlanillaJugador::where('partido_id', $partidoId)
                ->where('idEquipo', $equipoId)
                ->where('idEdicion', $idEdicion)
                ->get();

            $planillaDni = $planillaActual->pluck('dni_jugador')->toArray();

            // 3. IDENTIFICAR ALTAS (Están activos en Buena Fe pero NO en Planilla)
            $altasDni = array_diff($activosDni, $planillaDni);

            foreach ($altasDni as $dni) {
                $jugadorBF = $jugadoresActivosCategoria->where('dni_jugador', $dni)->first();
                if (! $jugadorBF) {
                    continue;
                }

                $jugadorSuspendido = $jugadorBF->estaSuspendido();

                // Crear registro en planilla
                PlanillaJugador::create([
                    'partido_id' => $partidoId,
                    'partido_type' => PartidoConstants::getModelClass($tipoPartido),
                    'dni_jugador' => $dni,
                    'fecha_nacimiento' => $jugadorBF->fecha_nacimiento,
                    'idEquipo' => $equipoId,
                    'idEdicion' => $idEdicion,
                    'idCategoria' => $equipoEdicion->idCategoria,
                    'numero_camiseta' => $jugadorBF->numero_camiseta ?? 0,
                    'goles' => 0,
                    'asistio' => false,
                    'tarjeta_roja' => $jugadorSuspendido,
                ]);
            }

            // 4. IDENTIFICAR BAJAS (Están en Planilla pero NO activos en Buena Fe)
            // Regla de negocio: un jugador dado de baja desaparece de TODAS las planillas
            // y se revierten TODAS sus estadísticas (goles, partidos, tarjetas).
            $dnisParaBaja = collect();
            foreach ($planillaActual as $registroPlanilla) {
                if (! in_array($registroPlanilla->dni_jugador, $activosDni)) {
                    $dnisParaBaja->push($registroPlanilla->dni_jugador);
                }
            }
            $dnisParaBaja = $dnisParaBaja->unique();

            if ($dnisParaBaja->isNotEmpty()) {
                DB::transaction(function () use ($dnisParaBaja, $equipoId, $idEdicion, $equipoEdicion) {
                    foreach ($dnisParaBaja as $dniBaja) {
                        $this->limpiarEstadisticasJugadorBaja(
                            $dniBaja,
                            $equipoId,
                            $idEdicion,
                            $equipoEdicion->idCategoria
                        );
                    }
                });
            }

        } catch (\Exception $e) {
            Log::error('Error en sincronizarPlanilla: '.$e->getMessage());
        }
    }

    /**
     * Resuelve el límite de jugadores por edición para mantener sincronizada
     * la carga de planilla con la regla configurable de Lista de Buena Fe.
     */
    private function obtenerLimiteJugadoresPorEdicion($idEdicion, $idCategoria = null)
    {
        if ($idCategoria) {
            $categoria = Categoria::find($idCategoria);
            if ($categoria && (int) ($categoria->jugadores_maximos ?? 0) > 0) {
                return (int) $categoria->jugadores_maximos;
            }
        }

        $edicion = Edicion::find($idEdicion);
        $limite = (int) ($edicion->jugadores_por_planilla ?? 18);

        return $limite > 0 ? $limite : 18;
    }
}
