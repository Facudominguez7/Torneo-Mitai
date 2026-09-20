<?php

namespace App\Http\Controllers\Panel;

use App\Constants\PartidoConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partido\StoreRequest;
use App\Http\Requests\Partido\UpdateRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Services\PartidoService;
use App\Traits\SeleccionarCategoriaTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ControladorPartidos extends Controller
{
    protected $partidoService;

    use SeleccionarCategoriaTrait;

    public function __construct(PartidoService $partidoService)
    {
        $this->partidoService = $partidoService;
    }

    /**
     * Lista partidos aplicando filtros de edición, categoría, grupo y fecha.
     *
     * Regla UX: cuando se selecciona una categoría que tiene un único grupo
     * disponible, se aplica automáticamente ese grupo para evitar un paso extra
     * y mantener coherencia en enlaces/acciones del contexto.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idFecha = $request->idFecha;
        $idGrupo = $request->idGrupo;
        $idCategoria = $request->idCategoria;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if ($idEdicion && $idCategoria && ! $idGrupo) {
            $gruposCategoria = Grupo::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->orderBy('id')
                ->get(['id']);

            if ($gruposCategoria->count() === 1) {
                return to_route('partido.index', array_filter([
                    'idEdicion' => $idEdicion,
                    'idCategoria' => $idCategoria,
                    'idGrupo' => $gruposCategoria->first()->id,
                    'idFecha' => $idFecha,
                    'horario' => $request->query('horario'),
                    'busqueda' => $request->query('busqueda'),
                ], static fn ($valor) => $valor !== null && $valor !== ''));
            }
        }

        $query = Partido::where('partidos.idEdicion', $idEdicion);

        if ($idGrupo) {
            $query->where('partidos.idGrupo', $idGrupo);
        }

        if ($idFecha) {
            $query->where('partidos.idFechas', $idFecha);
        }
        if ($idCategoria) {
            $query->where('partidos.idCategoria', $idCategoria);
        }

        // Filtro por horario. Soporta formato legacy en `horario`
        // y formato actual `horario_datetime`.
        $horario = $request->query('horario');
        if ($horario) {
            $horarioNormalizado = str_contains($horario, ':')
                ? (substr_count($horario, ':') === 1 ? $horario.':00' : $horario)
                : str_pad($horario, 2, '0', STR_PAD_LEFT).':00:00';

            $query->where(function ($subquery) use ($horario, $horarioNormalizado) {
                $subquery->where('partidos.horario', $horario)
                    ->orWhereRaw('TIME(partidos.horario_datetime) = ?', [$horarioNormalizado]);
            });
        }

        $partidos = $query
            ->with([
                'equipoLocal:id,nombre,foto',
                'equipoVisitante:id,nombre,foto',
                'fecha:id,nombre',
                'categoria:id,nombreCategoria',
                'grupo:id,nombre',
            ])
            ->select('partidos.*')
            ->orderByDesc('partidos.id')
            ->get();

        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->select('id', 'nombreCategoria')
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        $fechas = Fecha::query()
            ->select('fechas.id', 'fechas.nombre')
            ->join('partidos', 'partidos.idFechas', '=', 'fechas.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->when($idCategoria, static function ($query, $idCategoria) {
                $query->where('partidos.idCategoria', $idCategoria);
            })
            ->when($idGrupo, static function ($query, $idGrupo) {
                $query->where('partidos.idGrupo', $idGrupo);
            })
            ->distinct()
            ->orderByDesc('fechas.id')
            ->get();

        $grupos = Grupo::where('idEdicion', $idEdicion)
            ->select('id', 'nombre')
            ->where('idCategoria', $idCategoria)
            ->get();

        // Determinar si mostrar el filtro de grupo (solo si hay más de uno o si no hay categoría)
        $mostrarFiltroGrupo = ! $idCategoria || $grupos->count() > 1;

        // Obtener horarios disponibles para filtro según el contexto actual.
        $horariosDisponibles = Partido::where('idEdicion', $idEdicion)
            ->when($idCategoria, static function ($query, $idCategoria) {
                $query->where('idCategoria', $idCategoria);
            })
            ->when($idGrupo, static function ($query, $idGrupo) {
                $query->where('idGrupo', $idGrupo);
            })
            ->when($idFecha, static function ($query, $idFecha) {
                $query->where('idFechas', $idFecha);
            })
            ->where(function ($query) {
                $query->whereNotNull('horario_datetime')
                    ->orWhereNotNull('horario');
            })
            ->get(['horario', 'horario_datetime'])
            ->map(static function (Partido $partido): ?string {
                if ($partido->horario_datetime) {
                    return Carbon::parse($partido->horario_datetime)->format('H:i');
                }

                if ($partido->horario !== null && $partido->horario !== '') {
                    return str_pad((string) $partido->horario, 2, '0', STR_PAD_LEFT).':00';
                }

                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $fechas = $fechas->unique('nombre')->values();

        if (isset($idFecha) || isset($idGrupo) || isset($idCategoria)) {
            $fecha = $fechas->firstWhere('id', $idFecha);
            $nombreFecha = $fecha ? $fecha->nombre : null;
            $grupo = $grupos->firstWhere('id', $idGrupo);
            $nombreGrupo = $grupo ? $grupo->nombre : null;
            $categoria = $categorias->firstWhere('id', $idCategoria);
            $nombreCategoria = $categoria ? $categoria->nombreCategoria : null;

            return view('Panel.partido.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fechas', 'categorias', 'grupos', 'idCategoria', 'idGrupo', 'nombreFecha', 'nombreGrupo', 'nombreCategoria', 'horariosDisponibles', 'mostrarFiltroGrupo'));
        }

        return view('Panel.partido.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fechas', 'categorias', 'grupos', 'idCategoria', 'idGrupo', 'horariosDisponibles', 'mostrarFiltroGrupo'));
    }

    /**
     * Mostrar formulario de partido regular con validación de contexto.
     *
     * El alta regular requiere edición, categoría y grupo. Si falta alguno,
     * se redirige al selector para mantener un flujo guiado y consistente.
     */
    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;
        $idGrupo = $request->idGrupo;

        if (! $idGrupo || ! $idCategoria || ! $idEdicion) {
            return to_route('seleccionar-categoria', [
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'tipo' => 'partido',
            ])->with('error', 'Primero selecciona categoría y grupo para cargar un partido regular.');
        }

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $GrupoSeleccionado = $idGrupo ? Grupo::find($idGrupo) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $fechas = Fecha::where('idEdicion', $idEdicion)
            ->select('id', 'nombre')
            ->orderBy('id')
            ->get();
        if ($idEdicion > 3) {
            $equipos = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo') // Relación con ediciones
                ->join('equipos_grupos', 'equipos.id', '=', 'equipos_grupos.idEquipo')     // Relación con grupos
                ->where('equipo_ediciones.idCategoria', $idCategoria)                              // Filtrar por categoría
                ->where('equipo_ediciones.idEdicion', $idEdicion)                         // Filtrar por edición
                ->where('equipos_grupos.idGrupo', $idGrupo)                               // Filtrar por grupo
                ->get();
        } else {
            $equipos = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipos_grupos', 'equipos.id', '=', 'equipos_grupos.idEquipo')
                ->where('idCategoria', $idCategoria)
                ->where('equipos_grupos.idGrupo', $idGrupo)
                ->where('equipos.idEdicion', $idEdicion)
                ->get();
        }
        $grupo = Grupo::where('id', $idGrupo)->select('id', 'nombre')->get();
        $partido = new Partido;
        $fechaPorDefecto = $this->resolverFechaPorDefecto($fechas, $request->input('idFecha'));
        $horarioPorDefecto = $this->resolverHorarioPorDefecto($request->input('horario'));
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $idGrupo,
        ]);

        return view('Panel.partido.create', compact('ediciones', 'partido', 'EdicionSeleccionada', 'GrupoSeleccionado', 'fechas', 'equipos', 'grupo', 'idGrupo', 'idCategoria', 'CategoriaSeleccionada', 'fechaPorDefecto', 'horarioPorDefecto', 'parametrosRegreso'));
    }

    /**
     * Guarda un partido regular y regresa al listado preservando filtros activos.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Partido::create($data);

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $data['idEdicion'],
            'idCategoria' => $data['idCategoria'] ?? $request->input('idCategoria'),
            'idGrupo' => $data['idGrupo'] ?? $request->input('idGrupo'),
            'idFecha' => $request->input('idFechas') ?: $request->input('idFecha'),
        ]);

        return to_route('partido.index', $parametrosRegreso)->with('status', 'Partido creado con éxito.');
    }

    /**
     * Vista para crear un partido interzonal (entre equipos de diferentes grupos)
     */
    public function createInterzonal(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        $fechas = Fecha::where('idEdicion', $idEdicion)
            ->select('id', 'nombre')
            ->orderBy('id')
            ->get();

        // Obtener todos los grupos de esta categoría
        $grupos = Grupo::where('idCategoria', $idCategoria)
            ->where('idEdicion', $idEdicion)
            ->get();

        // Obtener equipos de todos los grupos de la categoría con su grupo
        $equipos = collect();
        foreach ($grupos as $grupo) {
            $equiposGrupo = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipos_grupos', 'equipos.id', '=', 'equipos_grupos.idEquipo')
                ->where('equipos_grupos.idGrupo', $grupo->id)
                ->get()
                ->map(function ($equipo) use ($grupo) {
                    $equipo->grupo_nombre = $grupo->nombre;
                    $equipo->grupo_id = $grupo->id;

                    return $equipo;
                });
            $equipos = $equipos->merge($equiposGrupo);
        }

        $partido = new Partido;
        $fechaPorDefecto = $this->resolverFechaPorDefecto($fechas, $request->input('idFecha'));
        $horarioPorDefecto = $this->resolverHorarioPorDefecto($request->input('horario'));
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
        ]);

        return view('Panel.partido.create-interzonal', compact(
            'ediciones',
            'partido',
            'EdicionSeleccionada',
            'CategoriaSeleccionada',
            'fechas',
            'equipos',
            'grupos',
            'idCategoria',
            'fechaPorDefecto',
            'horarioPorDefecto',
            'parametrosRegreso'
        ));
    }

    /**
     * Guardar un partido interzonal
     */
    public function storeInterzonal(Request $request)
    {
        $data = $request->validate([
            'idEdicion' => ['required', 'exists:ediciones,id'],
            'idCategoria' => ['required', 'exists:categorias,id'],
            'idEquipoLocal' => ['required', 'exists:equipos,id'],
            'idEquipoVisitante' => ['required', 'exists:equipos,id', 'different:idEquipoLocal'],
            'idFechas' => ['required', 'exists:fechas,id'],
            'horario_datetime' => ['nullable', 'date'],
            'cancha' => ['nullable', 'string', 'max:250'],
        ]);

        // Para interzonales, idGrupo será null ya que no pertenece a un grupo específico
        $data['idGrupo'] = null;
        $data['es_interzonal'] = true;

        Partido::create($data);

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $data['idEdicion'],
            'idCategoria' => $data['idCategoria'],
            'idFecha' => $data['idFechas'] ?? null,
        ]);

        return to_route('partido.index', $parametrosRegreso)
            ->with('status', 'Partido interzonal creado con éxito.');
    }

    public function show(Partido $partido)
    {
        return view('partidos.show', compact('partido'));
    }

    /**
     * Muestra la edición del partido manteniendo parámetros de regreso.
     */
    public function edit(Request $request, Partido $partido)
    {
        // Cargar datos auxiliares similares a create(), basados en el partido
        $ediciones = Edicion::all();
        $idEdicion = $partido->idEdicion;
        $idCategoria = $partido->idCategoria;
        $idGrupo = $partido->idGrupo;

        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $GrupoSeleccionado = $idGrupo ? Grupo::find($idGrupo) : null;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;

        $fechas = Fecha::where('idEdicion', $idEdicion)
            ->select('id', 'nombre')
            ->orderBy('id')
            ->get();

        // Verificar si es partido interzonal (sin grupo)
        if ($partido->es_interzonal || $idGrupo === null) {
            // Para interzonales: obtener todos los equipos de la categoría
            $equipos = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
                ->where('equipo_ediciones.idCategoria', $idCategoria)
                ->where('equipo_ediciones.idEdicion', $idEdicion)
                ->get();
        } elseif ($idEdicion > 3) {
            $equipos = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipo_ediciones', 'equipos.id', '=', 'equipo_ediciones.idEquipo')
                ->join('equipos_grupos', 'equipos.id', '=', 'equipos_grupos.idEquipo')
                ->where('equipo_ediciones.idCategoria', $idCategoria)
                ->where('equipo_ediciones.idEdicion', $idEdicion)
                ->where('equipos_grupos.idGrupo', $idGrupo)
                ->get();
        } else {
            $equipos = Equipo::select('equipos.id', 'equipos.nombre')
                ->join('equipos_grupos', 'equipos.id', '=', 'equipos_grupos.idEquipo')
                ->where('idCategoria', $idCategoria)
                ->where('equipos_grupos.idGrupo', $idGrupo)
                ->where('equipos.idEdicion', $idEdicion)
                ->get();
        }
        $grupo = Grupo::where('id', $idGrupo)->select('id', 'nombre')->get();

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $idGrupo,
            'idFecha' => $partido->idFechas,
        ]);

        $puedeEditarEquipos = $this->partidoService->razonesBloqueoCambioEquipos($partido) === [];

        return view('Panel.partido.edit', compact(
            'partido',
            'ediciones',
            'EdicionSeleccionada',
            'GrupoSeleccionado',
            'CategoriaSeleccionada',
            'fechas',
            'equipos',
            'grupo',
            'idGrupo',
            'idCategoria',
            'puedeEditarEquipos',
            'parametrosRegreso'
        ));
    }

    /**
     * Actualiza un partido y regresa al listado con el mismo contexto de filtros.
     */
    public function update(UpdateRequest $request, Partido $partido)
    {
        $partido->update($request->validated());

        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $partido->idEdicion,
            'idCategoria' => $partido->idCategoria,
            'idGrupo' => $partido->idGrupo,
            'idFecha' => $partido->idFechas,
        ]);

        return to_route('partido.index', $parametrosRegreso)
            ->with('status', 'Partido actualizado con éxito.');
    }

    /**
     * Elimina un partido y vuelve al listado contextual del módulo.
     */
    public function destroy(Request $request, Partido $partido)
    {
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $partido->idEdicion,
            'idCategoria' => $partido->idCategoria,
            'idGrupo' => $partido->idGrupo,
            'idFecha' => $partido->idFechas,
        ]);

        try {
            $this->partidoService->eliminarPartido($partido);
        } catch (Throwable $error) {
            Log::error('No se pudo eliminar el partido', [
                'partido_id' => $partido->id,
                'idEdicion' => $partido->idEdicion,
            ]);

            return to_route('partido.index', $parametrosRegreso)
                ->with('error', 'No se pudo eliminar el partido. Intenta nuevamente.');
        }

        return to_route('partido.index', $parametrosRegreso)->with('status', 'Partido eliminado con éxito.');
    }

    /**
     * Carga el resultado del partido y preserva filtros al regresar.
     */
    public function cargarResultado(Request $request, Partido $partido)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idPartido = $request->idPartido;
        $partido = Partido::with(['equipoLocal', 'equipoVisitante', 'grupo', 'categoria'])->findOrFail($idPartido);
        $parametrosRegreso = $this->construirParametrosRegreso($request, [
            'idEdicion' => $idEdicion,
            'idCategoria' => $request->input('idCategoria') ?: $partido->idCategoria,
            'idGrupo' => $request->input('idGrupo') ?: $partido->idGrupo,
            'idFecha' => $request->input('idFecha') ?: $partido->idFechas,
        ]);
        $rutaRegreso = $this->resolverRutaRegresoResultado($request, $partido, $parametrosRegreso);

        if ($request->isMethod('post')) {
            $datosValidados = $request->validate([
                'golesEquipoLocal' => ['required', 'integer', 'min:0'],
                'golesEquipoVisitante' => ['required', 'integer', 'min:0'],
            ]);

            $golesEquipoLocal = (int) $datosValidados['golesEquipoLocal'];
            $golesEquipoVisitante = (int) $datosValidados['golesEquipoVisitante'];

            $this->partidoService->actualizarResultado($partido, $golesEquipoLocal, $golesEquipoVisitante);
            $this->partidoService->actualizarGolesContraEquipoEdicion($partido->idEquipoLocal, $partido->idEdicion, $partido->idGrupo);
            $this->partidoService->actualizarGolesContraEquipoEdicion($partido->idEquipoVisitante, $partido->idEdicion, $partido->idGrupo);

            // Si es AJAX, devolvemos confirmación simple de guardado.
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Resultado guardado.',
                ]);
            }

            return redirect()->to($rutaRegreso)->with('status', 'Resultado guardado con éxito.');
        }

        return view('Panel.cargar-resultado', compact('partido', 'ediciones', 'EdicionSeleccionada', 'parametrosRegreso', 'rutaRegreso'));
    }

    private function resolverRutaRegresoResultado(Request $request, Partido $partido, array $parametrosRegreso): string
    {
        $origen = $request->input('origen')
            ?? $request->input('retorno_origen_planilla')
            ?? $request->input('origen_planilla');

        if ($origen === 'planilla') {
            return route('planilla.show', array_merge([
                'partidoId' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'tipoPartido' => PartidoConstants::TIPO_PARTIDO_NORMAL,
                'horario' => $request->input('retorno_horario_planilla', $request->input('horario_planilla', 0)),
            ], array_filter([
                'origen' => $request->input('retorno_origen_planilla', $request->input('origen_planilla')),
                'idCategoria' => $request->input('retorno_idCategoria', $request->input('idCategoria')),
                'idGrupo' => $request->input('retorno_idGrupo', $request->input('idGrupo')),
                'idFecha' => $request->input('retorno_idFecha', $request->input('idFecha')),
                'horario_filtro' => $request->input('retorno_horario_filtro_planilla', $request->input('horario_filtro_planilla')),
                'busqueda' => $request->input('retorno_busqueda', $request->input('busqueda')),
            ], static fn ($valor) => $valor !== null && $valor !== '')));
        }

        return route('partido.index', $parametrosRegreso);
    }

    /**
     * Resuelve la fecha preseleccionada en las altas a partir del filtro de
     * fecha del listado; si el filtro no pertenece a la edición se usa la
     * última fecha cargada.
     *
     * @param  Collection<int, Fecha>  $fechas
     */
    private function resolverFechaPorDefecto(Collection $fechas, mixed $idFechaSolicitada): ?int
    {
        return $fechas->firstWhere('id', $idFechaSolicitada)?->id ?? $fechas->last()?->id;
    }

    /**
     * Resuelve el horario por defecto de las altas a partir del filtro de
     * horario del listado, situado sobre el próximo domingo. Los formatos
     * inválidos o fuera de rango caen a las 00:00 de ese domingo.
     */
    private function resolverHorarioPorDefecto(mixed $horario): string
    {
        $proximoDomingo = static fn (): string => Carbon::now()
            ->next(Carbon::SUNDAY)
            ->startOfDay()
            ->format('Y-m-d\TH:i');

        if (! is_string($horario)
            || preg_match('/^(\d{1,2})(?::(\d{2}))?(?::(\d{2}))?$/', trim($horario), $partes) !== 1) {
            return $proximoDomingo();
        }

        $horas = (int) $partes[1];
        $minutos = (int) ($partes[2] ?? 0);
        $segundos = (int) ($partes[3] ?? 0);

        if ($horas > 23 || $minutos > 59 || $segundos > 59) {
            return $proximoDomingo();
        }

        return Carbon::now()
            ->next(Carbon::SUNDAY)
            ->startOfDay()
            ->setTime($horas, $minutos, $segundos)
            ->format('Y-m-d\TH:i');
    }

    /**
     * Construye parámetros de navegación para conservar filtros y reducir clicks.
     *
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function construirParametrosRegreso(Request $request, array $sobrescrituras = []): array
    {
        $parametros = [
            'idEdicion' => $sobrescrituras['idEdicion'] ?? $request->input('idEdicion'),
            'idCategoria' => $sobrescrituras['idCategoria'] ?? $request->input('idCategoria') ?? $request->input('retorno_idCategoria'),
            'idGrupo' => $sobrescrituras['idGrupo'] ?? $request->input('idGrupo') ?? $request->input('retorno_idGrupo'),
            'idFecha' => $sobrescrituras['idFecha'] ?? $request->input('idFecha') ?? $request->input('retorno_idFecha'),
            'horario' => $sobrescrituras['horario'] ?? $request->input('horario') ?? $request->input('retorno_horario'),
            'busqueda' => $sobrescrituras['busqueda'] ?? $request->input('busqueda') ?? $request->input('retorno_busqueda'),
        ];

        if (! isset($sobrescrituras['idFecha']) && $request->filled('idFechas')) {
            $parametros['idFecha'] = $request->input('idFechas');
        }

        return array_filter($parametros, static fn ($valor) => $valor !== null && $valor !== '');
    }
}
