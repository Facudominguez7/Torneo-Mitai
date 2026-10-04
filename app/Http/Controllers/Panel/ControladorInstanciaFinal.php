<?php

namespace App\Http\Controllers\Panel;

use App\Constants\PartidoConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\InstanciaFinal\StoreRequest;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Services\EstadisticasService;
use App\Services\PartidoService;
use App\Traits\SeleccionarCategoriaTrait;
use DomainException;
use Illuminate\Http\Request;

class ControladorInstanciaFinal extends Controller
{
    use SeleccionarCategoriaTrait;

    protected $partidoService;

    protected $estadisticasService;

    public function __construct(PartidoService $partidoService, EstadisticasService $estadisticasService)
    {
        $this->partidoService = $partidoService;
        $this->estadisticasService = $estadisticasService;
    }

    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;
        $idFase = $request->idFase;
        $idCopa = $request->idCopa;
        $estado = $request->estado; // 'pendiente', 'jugado' o null para todos
        $busquedaEquipo = $request->busqueda;
        $fechaFiltro = $request->fecha; // Nueva: filtro por fecha
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $query = InstanciaFinal::query();

        if ($idEdicion) {
            $query->where('idEdicion', $idEdicion);
        }

        if ($idCategoria) {
            $query->where('idCategoria', $idCategoria);
        }

        if ($idFase) {
            $query->where('idFase', $idFase);
        }

        // Filtro por copa
        if ($idCopa) {
            $query->where('idCopa', $idCopa);
        }

        // Filtro por fecha (usando DATE para extraer solo la fecha del campo horario)
        if ($fechaFiltro) {
            $query->whereRaw('DATE(horario) = ?', [$fechaFiltro]);
        }

        // Filtro por hora (HH:mm)
        $horaFiltro = $request->horario;
        if ($horaFiltro) {
            $query->whereRaw('TIME(horario) = ?', [$horaFiltro.':00']);
        }

        // Filtro por estado (pendiente/jugado)
        if ($estado === 'pendiente') {
            $query->whereNull('golesEquipoLocal')->whereNull('golesEquipoVisitante');
        } elseif ($estado === 'jugado') {
            $query->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante');
        }

        // Búsqueda por nombre de equipo
        if ($busquedaEquipo) {
            $query->where(function ($q) use ($busquedaEquipo) {
                $q->whereHas('equipoLocal', function ($q) use ($busquedaEquipo) {
                    $q->where('nombre', 'like', '%'.$busquedaEquipo.'%');
                })->orWhereHas('equipoVisitante', function ($q) use ($busquedaEquipo) {
                    $q->where('nombre', 'like', '%'.$busquedaEquipo.'%');
                });
            });
        }

        $partidos = $query
            ->with([
                'equipoLocal:id,nombre,foto',
                'equipoVisitante:id,nombre,foto',
                'fase:id,nombre',
                'categoria:id,nombreCategoria',
                'copa:id,nombre',
            ])
            ->select('instancias_finales.*')
            // Ordenar: pendientes primero (sin goles), luego por fecha
            ->orderByRaw('CASE WHEN golesEquipoLocal IS NULL AND golesEquipoVisitante IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByRaw('DATE(instancias_finales.horario) ASC')
            ->orderBy('instancias_finales.horario', 'asc')
            ->get();

        $equiposSinAsistencia = $this->estadisticasService->obtenerEquiposSinAsistenciaInstanciasFinales(
            $partidos,
            $idEdicion ? (int) $idEdicion : null,
        );

        $categorias = Categoria::whereIn('id', function ($query) use ($idEdicion) {
            $query->select('idCategoria')->from('instancias_finales')->where('idEdicion', $idEdicion)->distinct();
        })
            ->select('id', 'nombreCategoria')
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        $fases = Fase::whereIn('id', function ($query) use ($idEdicion) {
            $query->select('idFase')->from('instancias_finales')->where('idEdicion', $idEdicion)->distinct();
        })
            ->select('id', 'nombre')
            ->orderBy('nombre', 'desc')
            ->get();

        // Obtener las copas que tienen partidos en esta edición
        $copas = Copa::whereIn('id', function ($query) use ($idEdicion) {
            $query->select('idCopa')->from('instancias_finales')->where('idEdicion', $idEdicion)->whereNotNull('idCopa')->distinct();
        })
            ->orderBy('nombre', 'asc')
            ->get();

        // Obtener las fechas únicas de partidos para el filtro
        $fechasDisponibles = InstanciaFinal::where('idEdicion', $idEdicion)
            ->whereNotNull('horario')
            ->selectRaw('DATE(horario) as fecha')
            ->distinct()
            ->orderBy('fecha', 'asc')
            ->pluck('fecha');

        $nombreFase = $fases->firstWhere('id', $idFase)->nombre ?? null;
        $nombreCategoria = $categorias->firstWhere('id', $idCategoria)->nombreCategoria ?? null;

        return view('Panel.instancia_final.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fases', 'categorias', 'idCategoria', 'nombreFase', 'nombreCategoria', 'copas', 'estado', 'busquedaEquipo', 'idCopa', 'fechaFiltro', 'fechasDisponibles', 'equiposSinAsistencia'));
    }

    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $fases = Fase::query()
            ->select('id', 'nombre')
            ->orderBy('nombre', 'desc')
            ->get();

        $equipos = Equipo::join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->select('equipos.id', 'equipos.nombre')
            ->get();

        $copas = Copa::orderByDesc('nombre')->get();
        $partido = new InstanciaFinal;
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
        ]);

        return view('Panel.instancia_final.create', compact('ediciones', 'partido', 'EdicionSeleccionada', 'fases', 'equipos', 'idCategoria', 'CategoriaSeleccionada', 'copas', 'parametrosRegreso'));
    }

    /**
     * Guarda una instancia final y vuelve al listado con filtros activos.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        InstanciaFinal::create($data);

        // Tras crear, volver al listado sin filtros preseleccionados (solo el contexto de edición).
        return to_route('instancia_final.index', ['idEdicion' => $data['idEdicion']])
            ->with('status', 'Partido creado con éxito.');
    }

    /**
     * Carga el resultado de una instancia final y conserva filtros de navegación.
     */
    public function cargarResultadoInstancia(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idPartido = $request->idPartido;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        // Buscar manualmente para evitar problemas de Route Model Binding si los nombres de parámetros no coinciden
        $partido = InstanciaFinal::find($idPartido);

        if (! $partido) {
            return redirect()->back()->with('error', 'Partido no encontrado.');
        }

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
        ]);
        $rutaRegreso = $this->resolverRutaRegresoResultado($request, $partido, $parametrosRegreso);

        $golesEquipoLocal = $request->input('golesEquipoLocal');
        $golesEquipoVisitante = $request->input('golesEquipoVisitante');
        $penalesEquipoLocal = $request->input('penalesEquipoLocal');
        $penalesEquipoVisitante = $request->input('penalesEquipoVisitante');
        $resultadoGlobal = $request->input('resultadoGlobal');

        if (! is_null($golesEquipoLocal) && ! is_null($golesEquipoVisitante)) {
            try {
                $this->partidoService->asegurarResultadoEditable($partido);
            } catch (DomainException $error) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $error->getMessage()], 422);
                }

                return redirect()->to($rutaRegreso)->with('error', $error->getMessage());
            }

            $partido->golesEquipoLocal = $golesEquipoLocal;
            $partido->golesEquipoVisitante = $golesEquipoVisitante;
            $partido->penalesEquipoLocal = $penalesEquipoLocal;
            $partido->penalesEquipoVisitante = $penalesEquipoVisitante;
            $partido->resultadoGlobal = $resultadoGlobal;
            $partido->save();
            $idGrupo = 0;
            $this->partidoService->actualizarHistorialInstanciasFinales($partido, $golesEquipoLocal, $golesEquipoVisitante);
            $this->partidoService->actualizarGolesContraEquipoEdicion($partido->idEquipoLocal, $partido->idEdicion, $idGrupo);
            $this->partidoService->actualizarGolesContraEquipoEdicion($partido->idEquipoVisitante, $partido->idEdicion, $idGrupo);

            // Las notificaciones se envían desde el frontend via AJAX por lotes
            // Si es AJAX, devolvemos el partido_id para que el frontend envíe notificaciones
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Resultado guardado.',
                ]);
            }

            return redirect()->to($rutaRegreso)->with('status', 'Resultado cargado con éxito.');
        }

        $tipo = 'instancia_final';

        $partidoIda = InstanciaFinal::where('idEdicion', $partido->idEdicion)
            ->where('idCategoria', $partido->idCategoria)
            ->where('id', '!=', $partido->id)
            ->where(function ($q) use ($partido) {
                $q->where(function ($q2) use ($partido) {
                    $q2->where('idEquipoLocal', $partido->idEquipoLocal)
                        ->where('idEquipoVisitante', $partido->idEquipoVisitante);
                })->orWhere(function ($q2) use ($partido) {
                    $q2->where('idEquipoLocal', $partido->idEquipoVisitante)
                        ->where('idEquipoVisitante', $partido->idEquipoLocal);
                });
            })
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante')
            ->where('horario', '<', $partido->horario)
            ->with(['equipoLocal:id,nombre', 'equipoVisitante:id,nombre'])
            ->orderBy('horario', 'desc')
            ->first();

        return view('Panel.cargar-resultado', compact('partido', 'ediciones', 'EdicionSeleccionada', 'tipo', 'parametrosRegreso', 'rutaRegreso', 'partidoIda'));
    }

    private function resolverRutaRegresoResultado(Request $request, InstanciaFinal $partido, array $parametrosRegreso): string
    {
        if ($request->input('origen') === 'planilla') {
            return route('planilla.show', array_merge([
                'partidoId' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'tipoPartido' => PartidoConstants::TIPO_INSTANCIA_FINAL,
                'horario' => $request->input('retorno_horario_planilla', 0),
            ], array_filter([
                'origen' => $request->input('retorno_origen_planilla', $request->input('origen_planilla')),
                'idCategoria' => $request->input('retorno_idCategoria', $request->input('idCategoria')),
                'idFase' => $request->input('retorno_idFase', $request->input('idFase')),
                'idCopa' => $request->input('retorno_idCopa', $request->input('idCopa')),
                'estado' => $request->input('retorno_estado', $request->input('estado')),
                'busqueda' => $request->input('retorno_busqueda', $request->input('busqueda')),
                'fecha' => $request->input('retorno_fecha', $request->input('fecha')),
                'horario_filtro' => $request->input('retorno_horario_filtro_planilla', $request->input('horario_filtro_planilla')),
            ], static fn ($valor) => $valor !== null && $valor !== '')));
        }

        return route('instancia_final.index', $parametrosRegreso);
    }

    /**
     * Muestra la edición de instancia final manteniendo el contexto actual.
     */
    public function edit(Request $request, InstanciaFinal $instancia_final)
    {
        $ediciones = Edicion::all();
        $idEdicion = $instancia_final->idEdicion;
        $EdicionSeleccionada = Edicion::find($idEdicion);
        $idCategoria = $instancia_final->idCategoria;
        $CategoriaSeleccionada = Categoria::find($idCategoria);

        $fases = Fase::query()
            ->select('id', 'nombre')
            ->orderBy('nombre', 'desc')
            ->get();

        $equipos = Equipo::join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
            ->where('equipo_ediciones.idCategoria', $idCategoria)
            ->where('equipo_ediciones.idEdicion', $idEdicion)
            ->select('equipos.id', 'equipos.nombre')
            ->get();

        $copas = Copa::orderByDesc('nombre')->get();
        $partido = $instancia_final;
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
        ]);

        return view('Panel.instancia_final.edit', compact('ediciones', 'partido', 'EdicionSeleccionada', 'fases', 'equipos', 'idCategoria', 'CategoriaSeleccionada', 'copas', 'parametrosRegreso'));
    }

    /**
     * Actualiza una instancia final y regresa al índice con filtros persistidos.
     */
    public function update(StoreRequest $request, InstanciaFinal $instancia_final)
    {
        $data = $request->validated();

        $cambiaEquipos = (int) $data['idEquipoLocal'] !== (int) $instancia_final->idEquipoLocal
            || (int) $data['idEquipoVisitante'] !== (int) $instancia_final->idEquipoVisitante;

        if ($cambiaEquipos) {
            try {
                $this->partidoService->asegurarResultadoEditable($instancia_final);
            } catch (DomainException $error) {
                return redirect()->back()->withInput()->with('error', $error->getMessage());
            }
        }

        $instancia_final->update($data);

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $data['idEdicion'],
            'idCategoria' => $request->input('retorno_idCategoria'),
            'idFase' => $request->input('retorno_idFase'),
            'idCopa' => $request->input('retorno_idCopa'),
            'estado' => $request->input('retorno_estado'),
            'busqueda' => $request->input('retorno_busqueda'),
            'fecha' => $request->input('retorno_fecha'),
            'horario' => $request->input('retorno_horario'),
        ]);

        return to_route('instancia_final.index', $parametrosRegreso)->with('status', 'Partido actualizado con éxito.');
    }

    /**
     * Elimina la instancia final y vuelve al listado en el mismo contexto.
     */
    public function destroy(InstanciaFinal $instancia_final, Request $request)
    {
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $request->idEdicion ?: $instancia_final->idEdicion,
        ]);

        try {
            $this->partidoService->asegurarResultadoEditable($instancia_final);
        } catch (DomainException $error) {
            return to_route('instancia_final.index', $parametrosRegreso)->with('error', $error->getMessage());
        }

        $instancia_final->delete();

        return to_route('instancia_final.index', $parametrosRegreso)->with('status', 'Partido eliminado con éxito.');
    }

    /**
     * Construye parámetros de retorno para no perder filtros al navegar.
     *
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function construirParametrosRegreso(Request $request, array $sobrescrituras = []): array
    {
        $parametros = [
            'idEdicion' => array_key_exists('idEdicion', $sobrescrituras) ? $sobrescrituras['idEdicion'] : $request->input('idEdicion'),
            'idCategoria' => array_key_exists('idCategoria', $sobrescrituras) ? $sobrescrituras['idCategoria'] : $request->input('idCategoria') ?? $request->input('retorno_idCategoria'),
            'idFase' => array_key_exists('idFase', $sobrescrituras) ? $sobrescrituras['idFase'] : $request->input('idFase') ?? $request->input('retorno_idFase'),
            'idCopa' => array_key_exists('idCopa', $sobrescrituras) ? $sobrescrituras['idCopa'] : $request->input('idCopa') ?? $request->input('retorno_idCopa'),
            'estado' => array_key_exists('estado', $sobrescrituras) ? $sobrescrituras['estado'] : $request->input('estado') ?? $request->input('retorno_estado'),
            'busqueda' => array_key_exists('busqueda', $sobrescrituras) ? $sobrescrituras['busqueda'] : $request->input('busqueda') ?? $request->input('retorno_busqueda'),
            'fecha' => array_key_exists('fecha', $sobrescrituras) ? $sobrescrituras['fecha'] : $request->input('fecha') ?? $request->input('retorno_fecha'),
            'horario' => array_key_exists('horario', $sobrescrituras) ? $sobrescrituras['horario'] : $request->input('horario') ?? $request->input('retorno_horario'),
        ];

        return array_filter($parametros, static fn ($valor) => $valor !== null && $valor !== '');
    }
}
