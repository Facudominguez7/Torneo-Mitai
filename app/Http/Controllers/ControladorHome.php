<?php

namespace App\Http\Controllers;

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Goleador;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\Subcampeon;
use App\Models\TablaGoleador;
use App\Models\TablaPosicion;
use App\Models\VallaMenosVencida;
use App\Services\EquiposDeEdicionService;
use App\Services\EstadisticasService;
use App\Services\FixtureExploradorService;
use App\Services\Panel\ResumenDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ControladorHome extends Controller
{
    public function __construct(
        private readonly EquiposDeEdicionService $equiposDeEdicion,
        private readonly FixtureExploradorService $fixtureExplorador,
        private readonly ResumenDashboard $resumenDashboard,
    ) {}

    public function index(Request $request)
    {
        // Recuperar todas las ediciones de la base de datos
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $ultimaEdicion = null;
        if (is_null($idEdicion)) {
            $ultimaEdicion = Edicion::latest('id')->first();
        }
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $categorias = collect();
        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        } elseif ($ultimaEdicion) {
            $categorias = Categoria::where('idEdicion', $ultimaEdicion->id)->orderBy('nombreCategoria', 'desc')->get();
        }

        $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion;

        // Enmienda de categoría (#100): cada opción del selector lleva su
        // categoría para desambiguar equipos homónimos de la misma edición.
        // El padrón incluye también los equipos heredados vía equipo_ediciones.
        $equiposDeLaEdicion = collect();
        if ($edicionActual) {
            $equiposDeLaEdicion = $this->equiposDeEdicion->participantes((int) $edicionActual->id);
        }


        return view('welcome', compact(
            'ediciones',
            'ultimaEdicion',
            'EdicionSeleccionada',
            'categorias',
            'idEdicion',
            'equiposDeLaEdicion',
        ));
    }

    /**
     * Renderiza el panel admin y redirige módulos desde el selector global.
     *
     * Regla de navegación:
     * - Si llega `tipo`, se interpreta como cambio de módulo y se envía
     *   al índice correspondiente preservando la edición activa.
     * - Si no llega `tipo`, mantiene el comportamiento normal del dashboard.
     */
    public function admin(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $tipoModulo = $request->query('tipo');

        $rutasPorTipo = [
            'admin' => 'admin',
            'partido' => 'partido.index',
            'grupos' => 'grupos.index',
            'instancia_final' => 'instancia_final.index',
            'fecha' => 'fecha.index',
            'edicion' => 'edicion.index',
            'categoria' => 'categoria.index',
            'equipo' => 'equipo.index',
            'equipoedicion' => 'equipoedicion.index',
            'lista_buena_fe' => 'lista-buena-fe.index',
            'jugador_consulta' => 'jugador.consulta',
            'institucion' => 'institucion.index',
            'copa' => 'copa.index',
            'fase' => 'fase.index',
            'campeon' => 'campeon.index',
            'subcampeon' => 'subcampeon.index',
            'goleador' => 'goleador.index',
            'tabla_posicion' => 'tabla_posicion.index',
            'valla' => 'valla.index',
            // Compatibilidad por enlaces viejos cacheados en navegador.
            'equipogrupo' => 'grupos.index',
            'lista-buena-fe' => 'lista-buena-fe.index',
        ];

        if ($tipoModulo && isset($rutasPorTipo[$tipoModulo])) {
            $parametros = [
                'idEdicion' => $idEdicion,
            ];

            if ($tipoModulo === 'tabla_posicion') {
                $parametros['idCategoria'] = $request->query('idCategoria');
                $parametros['idGrupo'] = $request->query('idGrupo');
            }

            return to_route($rutasPorTipo[$tipoModulo], array_filter($parametros, static fn ($valor) => $valor !== null && $valor !== ''));
        }

        $horarioString = $request->query('horario');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $busquedaFecha = $request->query('busqueda');

        // Obtener la última fecha de la edición seleccionada
        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)->latest('id')->first();

        // Obtener horarios únicos de los partidos de la última fecha
        $horariosDisponibles = collect();
        if ($ultimaFecha) {
            $horariosDisponibles = Partido::where('idEdicion', $idEdicion)
                ->where('idFechas', $ultimaFecha->id)
                ->whereNotNull('horario_datetime')
                ->pluck('horario_datetime')
                ->map(function ($datetime) {
                    return \Carbon\Carbon::parse($datetime)->format('H:i');
                })
                ->unique()
                ->sort()
                ->values();
        }

        $fechasFixtureImpresion = collect();
        if ($idEdicion) {
            $fechasFixtureImpresion = Fecha::query()
                ->select('fechas.id', 'fechas.nombre')
                ->where('fechas.idEdicion', $idEdicion)
                ->whereExists(function ($query) use ($idEdicion): void {
                    $query->select(DB::raw(1))
                        ->from('partidos')
                        ->whereColumn('partidos.idFechas', 'fechas.id')
                        ->where('partidos.idEdicion', $idEdicion);
                })
                ->orderBy('fechas.id')
                ->get();
        }

        $ultimaFecha = Fecha::where('idEdicion', $idEdicion)->latest('id')->first();
        $partidosQuery = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'f.nombre as nombre_fecha', 'c.nombreCategoria as nombre_categoria')
            ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
            ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
            ->join('categorias as c', 'partidos.idCategoria', '=', 'c.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->when($ultimaFecha, function ($query) use ($ultimaFecha) {
                return $query->where('partidos.idFechas', $ultimaFecha->id);
            });

        // Filtrar por horario usando TIME() para comparar solo la hora
        if ($horarioString) {
            $partidosQuery->whereRaw('TIME(partidos.horario_datetime) = ?', [$horarioString.':00']);
        }

        $partidos = $partidosQuery->orderByDesc('f.id')
            ->orderByDesc('c.nombreCategoria')
            // Antes un when(whereNotNull(...)) filtraba sin querer los partidos sin horario_datetime.
            ->orderBy('partidos.horario_datetime', 'asc')
            ->get()
            ->groupBy('nombre_categoria');

        $categorias = Categoria::where('idEdicion', $idEdicion)->get();

        // Obtener estadísticas de jugadores de las últimas dos fechas
        $estadisticasJugadores = null;
        $jugadoresPorCategoria = null;
        $equiposSinAsistencia = [
            'nombre_fecha' => null,
            'horario_filtrado' => $horarioString,
            'total_pendientes' => 0,
            'equipos' => [],
        ];
        $resumenDashboard = null;
        if ($idEdicion) {
            $estadisticasService = new EstadisticasService();
            $estadisticasJugadores = $estadisticasService->obtenerJugadoresDosUltimasFechas($idEdicion);
            $jugadoresPorCategoria = $estadisticasService->obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion);
            $equiposSinAsistencia = $estadisticasService->obtenerEquiposSinAsistenciaUltimaFecha((int) $idEdicion, $horarioString);
        }

        // El resumen del dashboard se calcula sobre la edición activa que el
        // layout resuelve (query o, en su defecto, sesión vía middleware) para
        // no desincronizarse con el `$EdicionSeleccionada` que ve la vista.
        $idEdicionResumen = $idEdicion ?: $request->attributes->get('panel.idEdicion');
        if ($idEdicionResumen) {
            $resumenDashboard = $this->resumenDashboard->obtenerResumen((int) $idEdicionResumen);
        }

        return view('Panel.admin', compact('ediciones', 'EdicionSeleccionada', 'partidos', 'categorias', 'horarioString', 'horariosDisponibles', 'estadisticasJugadores', 'jugadoresPorCategoria', 'equiposSinAsistencia', 'fechasFixtureImpresion', 'resumenDashboard'));
    }

    public function campeones(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $categorias = Categoria::query();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        if ($EdicionSeleccionada) {
            $categorias = $categorias->where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        }
        $campeonesOro = Campeon::where('campeones.idEdicion', $idEdicion)
            ->join('copas', 'campeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Oro')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'campeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        $campeonesPlata = Campeon::where('campeones.idEdicion', $idEdicion)
            ->join('copas', 'campeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Plata')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'campeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        $campeonesMitai = Campeon::where('campeones.idEdicion', $idEdicion)
            ->join('copas', 'campeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', "Copa Mita'í")
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'campeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        $campeonesBronce = Campeon::where('campeones.idEdicion', $idEdicion)
            ->join('copas', 'campeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Bronce')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'campeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        return view('layouts.campeones', compact('ediciones', 'EdicionSeleccionada', 'categorias', 'campeonesOro', 'campeonesPlata', 'campeonesMitai', 'campeonesBronce'));
    }

    public function subcampeones(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $categorias = Categoria::query();
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        if ($EdicionSeleccionada) {
            $categorias = $categorias->where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        }
        $subcampeonesOro = Subcampeon::where('subcampeones.idEdicion', $idEdicion)
            ->join('copas', 'subcampeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Oro')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'subcampeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();
        $subcampeonesPlata = Subcampeon::where('subcampeones.idEdicion', $idEdicion)
            ->join('copas', 'subcampeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Plata')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'subcampeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        $subcampeonesMitai = Subcampeon::where('subcampeones.idEdicion', $idEdicion)
            ->join('copas', 'subcampeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', "Copa Mita'í")
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'subcampeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        $subcampeonesBronce = Subcampeon::where('subcampeones.idEdicion', $idEdicion)
            ->join('copas', 'subcampeones.idCopa', '=', 'copas.id')
            ->where('copas.nombre', 'Copa de Bronce')
            ->with(['equipo', 'categoria'])
            ->join('categorias', 'subcampeones.idCategoria', '=', 'categorias.id')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->get();

        return view('layouts.subcampeones', compact('ediciones', 'EdicionSeleccionada', 'categorias', 'subcampeonesOro', 'subcampeonesPlata', 'subcampeonesMitai', 'subcampeonesBronce'));
    }

    public function goleadores(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        // Obtener los datos de los goleadores
        $goleadores = Goleador::where('goleadores.idEdicion', $idEdicion)
            ->join('equipos', 'goleadores.idEquipo', '=', 'equipos.id')
            ->join('categorias', 'goleadores.idCategoria', '=', 'categorias.id')
            ->select('goleadores.nombre as nombre_jugador', 'equipos.nombre as nombre_equipo', 'equipos.foto as foto_equipo', 'categorias.nombreCategoria as categoria_nombre')
            ->orderBy('categoria_nombre', 'desc')
            ->get();

        return view('layouts.goleadores', compact('ediciones', 'EdicionSeleccionada', 'goleadores'));
    }

    public function vallas(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        $vallas = VallaMenosVencida::where('vallas_menos_vencidas.idEdicion', $idEdicion)
            ->join('equipos', 'vallas_menos_vencidas.idEquipo', '=', 'equipos.id')
            ->join('categorias', 'vallas_menos_vencidas.idCategoria', '=', 'categorias.id')
            ->leftJoin('equipo_ediciones', function ($join) {
                $join->on('vallas_menos_vencidas.idEquipo', '=', 'equipo_ediciones.idEquipo')
                    ->on('vallas_menos_vencidas.idEdicion', '=', 'equipo_ediciones.idEdicion')
                    ->on('vallas_menos_vencidas.idCategoria', '=', 'equipo_ediciones.idCategoria');
            })
            ->select(
                'vallas_menos_vencidas.nombre as nombre_jugador',
                'equipos.nombre as nombre_equipo',
                'equipos.foto as foto_equipo',
                'categorias.nombreCategoria as categoria_nombre',
                'equipo_ediciones.golesContra as goles_recibidos'
            )
            ->orderBy('categoria_nombre', 'desc')
            ->get();

        return view('layouts.vallas', compact('ediciones', 'EdicionSeleccionada', 'vallas'));
    }

    /**
     * Mostrar la tabla de vallas menos vencidas en tiempo real.
     *
     * Muestra los equipos ordenados por menor cantidad de goles recibidos,
     * considerando tanto partidos de fase de grupos como instancias finales.
     * Los datos se obtienen de `equipo_ediciones.golesContra` que se actualiza
     * automáticamente al cargar resultados.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function tablaVallas(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->query('idCategoria');

        // Parámetros de ordenamiento
        $sort = $request->query('sort');
        $dir = strtolower($request->query('dir', '')) === 'desc' ? 'desc' : 'asc';

        // Normalizar clave de orden
        $sortKey = in_array($sort, ['goles', 'equipo', 'categoria']) ? $sort : 'goles';

        // Columna real en base de datos para cada clave
        $sortColumnMap = [
            'goles' => 'equipo_ediciones.golesContra',
            'equipo' => 'e.nombre',
            'categoria' => 'cat.nombreCategoria',
        ];

        // Dirección por defecto: goles asc (menos goles = mejor), resto asc
        if (! $request->has('dir')) {
            $dir = 'asc';
        }

        // Query base con Eloquent
        $baseQuery = EquipoEdicion::query()
            ->join('ediciones as ed', 'equipo_ediciones.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'equipo_ediciones.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'equipo_ediciones.idCategoria', '=', 'cat.id')
            ->select([
                'equipo_ediciones.*',
                'e.nombre as nombreEquipo',
                'e.foto as fotoEquipo',
                'cat.nombreCategoria as nombreCategoria',
                'cat.id as idCategoriaEquipo',
            ])
            ->where('equipo_ediciones.idEdicion', $idEdicion);

        // Filtro por categoría
        if (! is_null($idCategoria)) {
            $baseQuery->where('equipo_ediciones.idCategoria', $idCategoria);
        }

        // Aplicar ordenamiento
        $baseQuery->orderBy($sortColumnMap[$sortKey], $dir)
            ->orderBy('cat.nombreCategoria', 'desc')
            ->orderBy('equipo_ediciones.golesContra', 'asc');

        // Paginación
        $equipos = $baseQuery->paginate(30);

        // Mantener parámetros en la paginación
        $appendParams = array_filter([
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'sort' => $sortKey,
            'dir' => $dir,
        ]);
        $equipos->appends($appendParams);

        // Obtener categorías para el filtro
        $categorias = collect();
        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->select('id', 'nombreCategoria')
                ->orderBy('nombreCategoria', 'desc')
                ->get();
        }

        return view('layouts.tabla-vallas', compact(
            'ediciones',
            'EdicionSeleccionada',
            'equipos',
            'categorias',
            'idCategoria'
        ));
    }

    public function fixture(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->idCategoria;
        $CategoriaSeleccionada = $idCategoria ? Categoria::find($idCategoria) : null;
        $idFecha = $request->idFecha;
        $estado = $request->query('estado', 'todos'); // todos | proximos | finalizados
        $ordenParam = $request->query('orden');
        // Si no viene 'orden', elegimos por defecto asc para proximos, desc para el resto
        $orden = in_array(strtolower((string) $ordenParam), ['asc', 'desc'])
            ? strtolower($ordenParam)
            : ($estado === 'proximos' ? 'asc' : 'desc');
        $equipo = trim((string) $request->query('equipo', ''));
        $fixtureExplorador = $this->fixtureExplorador;

        $categorias = collect();
        if (isset($EdicionSeleccionada)) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->select('id', 'nombreCategoria')
                ->orderBy('nombreCategoria', 'desc')
                ->get();
        }

        $fechas = null;
        $grupos = null;
        $nombreFecha = null;
        $nombreCategoria = null;
        $partidos = collect();

        if (isset($CategoriaSeleccionada)) {
            // Incluir las fechas que estén asociadas a la categoría seleccionada y también aquellas sin categoría asignada
            $fechas = $fixtureExplorador->ordenarFechas(
                Fecha::where('idEdicion', $idEdicion)
                    ->where(function ($query) use ($idCategoria) {
                        $query->where('idCategoria', $idCategoria) // Fechas asociadas a la categoría seleccionada
                            ->orWhereNull('idCategoria'); // O fechas sin categoría
                    })
                    ->select('id', 'nombre')
                    ->distinct('nombre')
                    ->get(),
                (int) $idEdicion,
                (int) $idCategoria,
            );

            $grupos = Grupo::where('idCategoria', $idCategoria)
                ->where('grupos.idEdicion', $idEdicion)
                ->leftJoin('categorias as c', 'grupos.idCategoria', '=', 'c.id')
                ->select('grupos.id', 'grupos.nombre', 'grupos.idCategoria', 'c.nombreCategoria as categoria_nombre')
                ->distinct('nombre')
                ->get();
        } elseif (isset($EdicionSeleccionada)) {
            // Sin categoría: listar grupos de toda la edición
            $grupos = Grupo::where('grupos.idEdicion', $idEdicion)
                ->leftJoin('categorias as c', 'grupos.idCategoria', '=', 'c.id')
                ->select('grupos.id', 'grupos.nombre', 'grupos.idCategoria', 'c.nombreCategoria as categoria_nombre')
                ->distinct('nombre')
                ->get();

            $fechas = $fixtureExplorador->ordenarFechas(
                Fecha::where('idEdicion', $idEdicion)
                    ->select('id', 'nombre')
                    ->get(),
                (int) $idEdicion,
            );
        }

        // Fecha relevante por defecto; 'todas' y la búsqueda de equipo la ignoran.
        $idFechaRelevante = isset($EdicionSeleccionada)
            ? $fixtureExplorador->resolverFechaRelevante((int) $idEdicion, isset($idCategoria) ? (int) $idCategoria : null)
            : null;

        // Solo un id numérico existente en la edición fija la fecha; cualquier
        // otro valor (inexistente o basura) cae a la fecha relevante sin explotar.
        $idFechaPedida = ctype_digit((string) $idFecha)
            ? Fecha::where('idEdicion', $idEdicion)->find((int) $idFecha)?->id
            : null;

        // Cargar partidos según filtros (con o sin categoría)
        if (isset($EdicionSeleccionada)) {
            if ($equipo !== '') {
                // Búsqueda multi-fecha: el término precede a idFecha y agrupa por fecha.
                $patronEquipo = '%'.addcslashes($equipo, '\\%_').'%';
                $partidos = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'f.nombre as nombre_fecha', 'cat.nombreCategoria as nombre_categoria')
                    ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
                    ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
                    ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
                    ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
                    ->where('partidos.idEdicion', $idEdicion)
                    ->when($idCategoria, fn ($q) => $q->where('partidos.idCategoria', $idCategoria))
                    ->when($estado === 'finalizados', function ($q) {
                        $q->where('partidos.jugado', 1);
                    })
                    ->when($estado === 'proximos', function ($q) {
                        $q->where(function ($sub) {
                            $sub->where('partidos.jugado', 0)
                                ->orWhereNull('partidos.jugado');
                        });
                    })
                    ->where(function ($query) use ($patronEquipo) {
                        $query->where('el.nombre', 'like', $patronEquipo)
                            ->orWhere('ev.nombre', 'like', $patronEquipo);
                    })
                    ->orderBy('partidos.horario_datetime', $orden)
                    ->with('fecha')
                    ->get();
            } elseif ($idFecha === 'todas') {
                $partidos = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'f.nombre as nombre_fecha', 'cat.nombreCategoria as nombre_categoria')
                    ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
                    ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
                    ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
                    ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
                    ->where('partidos.idEdicion', $idEdicion)
                    ->when($idCategoria, fn ($q) => $q->where('partidos.idCategoria', $idCategoria))
                    ->when($estado === 'finalizados', function ($q) {
                        $q->where('partidos.jugado', 1);
                    })
                    ->when($estado === 'proximos', function ($q) {
                        $q->where(function ($sub) {
                            $sub->where('partidos.jugado', 0)
                                ->orWhereNull('partidos.jugado');
                        });
                    })
                    ->orderBy('partidos.horario_datetime', $orden)
                    ->with('fecha')
                    ->get();
            } else {
                $idFechaEfectiva = $idFechaPedida ?? $idFechaRelevante;
                if ($idFechaEfectiva !== null) {
                    $partidos = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'cat.nombreCategoria as nombre_categoria')
                        ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
                        ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
                        ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
                        ->where('partidos.idFechas', $idFechaEfectiva)
                        ->where('partidos.idEdicion', $idEdicion)
                        ->when($idCategoria, fn ($q) => $q->where('partidos.idCategoria', $idCategoria))
                        ->when($estado === 'finalizados', function ($q) {
                            $q->where('partidos.jugado', 1);
                        })
                        ->when($estado === 'proximos', function ($q) {
                            $q->where(function ($sub) {
                                $sub->where('partidos.jugado', 0)
                                    ->orWhereNull('partidos.jugado');
                            });
                        })
                        ->orderBy('partidos.horario_datetime', $orden)
                        ->with('fecha')
                        ->get();
                    $nombreFecha = Fecha::find($idFechaEfectiva)?->nombre;
                }
            }
        }
        if (isset($idCategoria)) {
            $categoria = Categoria::find($idCategoria);
            $nombreCategoria = $categoria->nombreCategoria;
        } elseif (isset($EdicionSeleccionada)) {
            $nombreCategoria = 'Todas las categorías';
        }

        return view('layouts.fixture', compact('nombreFecha', 'nombreCategoria', 'grupos', 'ediciones', 'EdicionSeleccionada', 'categorias', 'idEdicion', 'idCategoria', 'CategoriaSeleccionada', 'idFecha', 'idFechaRelevante', 'estado', 'equipo', 'fechas', 'partidos'));
    }

    /**
     * Mostrar tabla de posiciones con orden de desempate consistente.
     *
     * Regla de negocio: a igual puntaje, los equipos que ya disputaron
     * al menos un partido deben ubicarse por encima de los que aun no jugaron.
     */
    public function tablaPosiciones(Request $request)
    {
        $ediciones = Edicion::all();
        $idCategoria = $request->query('idCategoria');
        $idGrupo = $request->query('idGrupo');
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        // Defaults determinísticos para la navegación pública: primera categoría
        // de la edición y su primer grupo, igual que elegirlos a mano.
        if ($EdicionSeleccionada && (! $idCategoria || ! $idGrupo)) {
            $categoriaDefault = $idCategoria
                ? Categoria::find($idCategoria)
                : Categoria::where('idEdicion', $EdicionSeleccionada->id)
                    ->orderBy('nombreCategoria', 'desc')
                    ->first();

            if ($categoriaDefault) {
                $idCategoria = $categoriaDefault->id;

                if (! $idGrupo) {
                    $idGrupo = Grupo::where('idCategoria', $categoriaDefault->id)
                        ->where('idEdicion', $EdicionSeleccionada->id)
                        ->orderBy('id')
                        ->first()
                        ?->id;
                }
            }
        }

        $categorias = collect();
        $grupos = collect();

        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $EdicionSeleccionada->id)
                ->orderBy('nombreCategoria', 'desc')
                ->get();

            if ($idCategoria) {
                $grupos = Grupo::where('idCategoria', $idCategoria)
                    ->where('idEdicion', $EdicionSeleccionada->id)
                    ->orderBy('id')
                    ->get();
            }
        }

        $GrupoSeleccionado = $idGrupo ? Grupo::find($idGrupo) : null;

        // Obtener el nombre de la categoría
        $categoria = Categoria::find($idCategoria);
        $nombreCategoria = $categoria ? $categoria->nombreCategoria : 'Categoría no encontrada';

        // Consulta utilizando Eloquent
        $tablaPosiciones = TablaPosicion::select('tabla_posiciones.*', 'equipos.nombre as nombreEquipo', 'equipos.foto as fotoEquipo', 'equipos.id as idEquipo')
            ->join('equipos', 'tabla_posiciones.idEquipo', '=', 'equipos.id')
            ->where('tabla_posiciones.idGrupo', $idGrupo)
            ->where(function ($query) use ($idEdicion) {
                $query->where('tabla_posiciones.idEdicion', $idEdicion)
                    ->orWhere('tabla_posiciones.idEdicion', 0);
            })
            ->orderBy('tabla_posiciones.puntos', 'DESC')
            ->orderByRaw('CASE WHEN tabla_posiciones.jugado > 0 THEN 1 ELSE 0 END DESC')
            ->orderBy('tabla_posiciones.diferenciaGoles', 'DESC')
            ->orderBy('tabla_posiciones.golesFavor', 'DESC')
            ->orderBy('equipos.nombre', 'ASC')
            ->get();

        return view('layouts.tabla-posiciones', compact('tablaPosiciones', 'EdicionSeleccionada', 'nombreCategoria', 'ediciones', 'idCategoria', 'idGrupo', 'GrupoSeleccionado', 'categorias', 'grupos'));
    }

    public function tablaGoleadores(Request $request)
    {
        // La URL es la única fuente de verdad: un enlace público debe poder
        // reproducirse sin sesión previa, igual que el resto de vistas públicas.
        $filtros = $request->query();

        $ediciones = Edicion::all();
        $idEdicion = $filtros['idEdicion'] ?? null;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = isset($filtros['idCategoria']) && $filtros['idCategoria'] !== '' ? $filtros['idCategoria'] : null;
        $searchValue = trim((string) ($filtros['search_value'] ?? ''));
        // Parámetros de ordenamiento (UX): sort = goles|nombre|equipo|categoria, dir = asc|desc
        $sort = $filtros['sort'] ?? null;
        $dir = strtolower($filtros['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($filtros['page'] ?? 1));

        // Normalizar clave de orden
        $sortKey = in_array($sort, ['goles', 'nombre', 'equipo', 'categoria']) ? $sort : 'goles';
        // Columna real en base de datos para cada clave
        // 'nombre' ahora se obtiene de la tabla jugadores (apellido + nombre)
        $sortColumnMap = [
            'goles' => 'tabla_goleadores.cantidadGoles',
            'nombre' => 'j.apellido',
            'equipo' => 'e.nombre',
            'categoria' => 'cat.nombreCategoria',
        ];
        // Dirección por defecto: goles desc, resto asc (más natural en listados)
        if (! array_key_exists('dir', $filtros)) {
            $dir = $sortKey === 'goles' ? 'desc' : 'asc';
        }

        $baseQuery = TablaGoleador::query()
            ->join('ediciones as ed', 'tabla_goleadores.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'tabla_goleadores.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'tabla_goleadores.idCategoria', '=', 'cat.id')
            ->join('jugadores as j', 'tabla_goleadores.dni_jugador', '=', 'j.dni')
            ->select('tabla_goleadores.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo', \Illuminate\Support\Facades\DB::raw("CONCAT(j.apellido, ' ', j.nombre) as nombreJugador"))
            ->where('tabla_goleadores.idEdicion', $idEdicion);

        if ($searchValue !== '') {
            $baseQuery->where(function ($query) use ($searchValue) {
                $likePattern = "%{$searchValue}%";
                $query->where('j.nombre', 'like', $likePattern)
                    ->orWhere('j.apellido', 'like', $likePattern)
                    ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(j.apellido, ' ', j.nombre)"), 'like', $likePattern);
            });
        }

        if (! is_null($idCategoria)) {
            $baseQuery->where('tabla_goleadores.idCategoria', $idCategoria);
        }

        // Aplicar ordenamiento solicitado por el usuario
        $baseQuery->orderBy($sortColumnMap[$sortKey], $dir)
            // Criterios secundarios de desempate
            ->orderBy('cat.nombreCategoria', 'desc')
            ->orderBy('tabla_goleadores.cantidadGoles', 'desc');

        $appendParams = array_filter([
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'search_value' => $searchValue,
            'sort' => $sortKey,
            'dir' => $dir,
        ]);

        $goleadores_t = $baseQuery->paginate(30, ['*'], 'page', $page)->appends($appendParams);

        $categorias = collect();
        if (isset($EdicionSeleccionada)) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->select('id', 'nombreCategoria')
                ->orderBy('nombreCategoria', 'desc')
                ->get();
        }

        // En AJAX solo se devuelve el parcial de resultados para reemplazar el
        // contenedor sin recargar la página ni perder el foco del buscador.
        if ($request->ajax()) {
            return view('partials.goleadores-resultados', compact(
                'goleadores_t',
                'sortKey',
                'dir',
                'idEdicion',
                'idCategoria',
                'searchValue'
            ));
        }

        return view('layouts.tabla-goleadores', compact(
            'ediciones',
            'EdicionSeleccionada',
            'goleadores_t',
            'categorias',
            'idEdicion',
            'idCategoria',
            'searchValue',
            'sortKey',
            'dir'
        ));
    }

    public function instanciasFinales(Request $request)
    {
        $ediciones = Edicion::all();
        $ultimaEdicion = Edicion::latest('id')->first();
        $idEdicion = $request->query('idEdicion', $ultimaEdicion->id);
        $EdicionSeleccionada = Edicion::find($idEdicion);

        // Obtener las categorías disponibles para el filtro
        $categorias = Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        $selectedCategoria = $request->query('categoria');

        // Obtener las copas disponibles para el filtro (del torneo actual)
        $copas = \App\Models\Copa::orderBy('nombre', 'asc')->get();
        $selectedCopa = $request->query('copa');

        // Obtener las fases disponibles para el filtro (del torneo actual)
        $fases = Fase::whereIn('id', function ($query) use ($idEdicion) {
            $query->select('idFase')
                ->from('instancias_finales')
                ->where('idEdicion', $idEdicion)
                ->distinct();
        })->get();
        $selectedFase = $request->query('fase');

        // Ordenar por secuencia de fases (menor = mayor prioridad: Final primero)
        $partidosQuery = InstanciaFinal::select(
            'instancias_finales.*',
            'fases.nombre as nombre_fase',
            'fases.id as id_fase',
            'fases.secuencia as orden_fase',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'el.foto as foto_local',
            'ev.foto as foto_visitante',
            'categorias.nombreCategoria as nombre_categoria',
            DB::raw("COALESCE(copas.nombre, '') as nombre_copa"),
            DB::raw('COALESCE(copas.secuencia, 99) as orden_copa')
        )
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftjoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->orderBy('copas.secuencia', 'asc')
            ->orderBy('fases.secuencia', 'asc');

        // Aplicar el filtro por categoría si se selecciona una
        if (! empty($selectedCategoria)) {
            $partidosQuery->where('categorias.id', (int) $selectedCategoria);
        }

        // Aplicar el filtro por copa si se selecciona una
        if (! empty($selectedCopa)) {
            $partidosQuery->where('copas.id', (int) $selectedCopa);
        }

        // Aplicar el filtro por fase si se selecciona una
        if (! empty($selectedFase)) {
            $partidosQuery->where('fases.id', (int) $selectedFase);
        }

        $partidos = $partidosQuery->get();

        return view('layouts.instancias-finales', compact(
            'ediciones',
            'ultimaEdicion',
            'EdicionSeleccionada',
            'partidos',
            'categorias',
            'selectedCategoria',
            'copas',
            'selectedCopa',
            'fases',
            'selectedFase'
        ));
    }

    /**
     * Imprimir tabla de posiciones de TODAS las categorías y grupos de una edición.
     */
    public function imprimirTablaPosiciones(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        // Impresión global por edición: una única fecha con resultados para
        // contextualizar las tablas; sin ella no se etiqueta con fecha alguna.
        $idFechaResultados = $this->fixtureExplorador->resolverUltimaFechaConResultados((int) $idEdicion);
        $fechaResultados = $idFechaResultados !== null
            ? Fecha::query()->select('id', 'nombre')->find($idFechaResultados)
            : null;

        // Obtener todas las categorías de la edición, ordenadas de mayor a menor
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        // Estructura para almacenar todas las tablas por categoría y grupo
        $tablasPorCategoria = [];

        foreach ($categorias as $categoria) {
            // Obtener todos los grupos de esta categoría
            $grupos = Grupo::where('idCategoria', $categoria->id)
                ->where('idEdicion', $idEdicion)
                ->orderBy('nombre')
                ->get();

            $gruposConTabla = [];

            foreach ($grupos as $grupo) {
                // Obtener la tabla de posiciones de cada grupo
                $tablaPosiciones = TablaPosicion::select(
                    'tabla_posiciones.*',
                    'equipos.nombre as nombreEquipo',
                    'equipos.foto as fotoEquipo',
                    'equipos.id as idEquipo'
                )
                    ->join('equipos', 'tabla_posiciones.idEquipo', '=', 'equipos.id')
                    ->where('tabla_posiciones.idGrupo', $grupo->id)
                    ->where(function ($query) use ($idEdicion) {
                        $query->where('tabla_posiciones.idEdicion', $idEdicion)
                            ->orWhere('tabla_posiciones.idEdicion', 0);
                    })
                    ->orderBy('tabla_posiciones.puntos', 'DESC')
                    ->orderByRaw('CASE WHEN tabla_posiciones.jugado > 0 THEN 1 ELSE 0 END DESC')
                    ->orderBy('tabla_posiciones.diferenciaGoles', 'DESC')
                    ->orderBy('tabla_posiciones.golesFavor', 'DESC')
                    ->orderBy('equipos.nombre', 'ASC')
                    ->get();

                if ($tablaPosiciones->isNotEmpty()) {
                    $gruposConTabla[] = [
                        'grupo' => $grupo,
                        'tabla' => $tablaPosiciones,
                    ];
                }
            }

            if (! empty($gruposConTabla)) {
                $tablasPorCategoria[] = [
                    'categoria' => $categoria,
                    'grupos' => $gruposConTabla,
                ];
            }
        }

        return view('Panel.imprimir.tabla-posiciones', compact('tablasPorCategoria', 'EdicionSeleccionada', 'fechaResultados'));
    }

    /**
     * Imprimir fixture de TODOS los partidos de una edición.
     */
    public function imprimirFixture(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $idFecha = $request->query('idFecha');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        $FechaSeleccionada = null;
        if ($idFecha) {
            $FechaSeleccionada = Fecha::where('idEdicion', $idEdicion)->find($idFecha);

            if (! $FechaSeleccionada) {
                return redirect()->back()->with('error', 'La fecha seleccionada no pertenece a la edición');
            }
        }

        // Obtener todas las categorías de la edición, ordenadas de mayor a menor
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        // Obtener todos los grupos para referencia
        $grupos = Grupo::where('idEdicion', $idEdicion)->get()->keyBy('id');

        // Obtener todos los partidos de la edición
        $partidos = Partido::select(
            'partidos.*',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'el.foto as foto_local',
            'ev.foto as foto_visitante',
            'f.nombre as nombre_fecha',
            'f.id as idFecha',
            'cat.nombreCategoria as nombre_categoria',
            'cat.id as idCategoriaPartido'
        )
            ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
            ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
            ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->when($FechaSeleccionada, function ($query) use ($FechaSeleccionada) {
                return $query->where('partidos.idFechas', $FechaSeleccionada->id);
            })
            ->orderBy('cat.nombreCategoria', 'desc')
            ->orderBy('f.id', 'asc')
            ->orderBy('partidos.horario_datetime', 'asc')
            ->get();

        // Agrupar partidos por categoría
        $partidosPorCategoria = $partidos->groupBy('idCategoriaPartido');

        return view('Panel.imprimir.fixture', compact('partidosPorCategoria', 'categorias', 'grupos', 'EdicionSeleccionada', 'FechaSeleccionada'));
    }

    /**
     * Imprimir fixture de instancias finales de una edición.
     */
    public function imprimirFixtureInstanciasFinales(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        // Obtener todos los partidos de instancias finales
        $partidos = InstanciaFinal::select(
            'instancias_finales.*',
            'fases.nombre as nombre_fase',
            'fases.secuencia as orden_fase',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'el.foto as foto_local',
            'ev.foto as foto_visitante',
            'categorias.nombreCategoria as nombre_categoria',
            DB::raw("COALESCE(copas.nombre, '') as nombre_copa"),
            DB::raw('COALESCE(copas.secuencia, 99) as orden_copa')
        )
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftjoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->orderBy('copas.secuencia', 'asc')
            ->orderBy('fases.secuencia', 'asc')
            ->orderBy('instancias_finales.horario', 'asc')
            ->get();

        return view('Panel.imprimir.fixture-instancias-finales', compact('partidos', 'EdicionSeleccionada'));
    }

    /**
     * Imprimir fixture para árbitros (instancias finales) con espacios para resultado y goleadores.
     * Solo muestra partidos pendientes (sin resultado cargado).
     * Soporta filtro opcional por horario.
     */
    public function imprimirFixtureArbitros(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $horarioFiltro = $request->query('horario'); // Formato: HH:mm
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        // Obtener partidos de instancias finales PENDIENTES (sin resultado)
        $query = InstanciaFinal::select(
            'instancias_finales.*',
            'fases.nombre as nombre_fase',
            'fases.secuencia as orden_fase',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'el.foto as foto_local',
            'ev.foto as foto_visitante',
            'categorias.nombreCategoria as nombre_categoria',
            DB::raw("COALESCE(copas.nombre, '') as nombre_copa"),
            DB::raw('COALESCE(copas.secuencia, 99) as orden_copa')
        )
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftjoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            // Solo partidos pendientes (sin resultado cargado)
            ->whereNull('instancias_finales.golesEquipoLocal')
            ->whereNull('instancias_finales.golesEquipoVisitante');

        // Filtro por horario si se proporciona
        if ($horarioFiltro) {
            $query->whereRaw('TIME(instancias_finales.horario) = ?', [$horarioFiltro.':00']);
        }

        $partidos = $query
            ->orderBy('instancias_finales.horario', 'asc')
            ->orderBy('instancias_finales.cancha', 'asc')
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->orderBy('copas.secuencia', 'asc')
            ->orderBy('fases.secuencia', 'asc')
            ->get();

        return view('Panel.imprimir.fixture-arbitros', compact('partidos', 'EdicionSeleccionada', 'horarioFiltro'));
    }

    /**
     * Imprimir planilla para árbitros de partidos de fase regular.
     *
     * Regla operativa: se listan solo encuentros pendientes para facilitar
     * carga en cancha y evitar que se impriman cruces ya cerrados.
     */
    public function imprimirFixtureArbitrosPartidos(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $horarioFiltro = $request->query('horario'); // Formato: HH:mm
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        $ultimaFechaDisponibleId = Partido::query()
            ->where('partidos.idEdicion', $idEdicion)
            ->whereNull('partidos.golesEquipoLocal')
            ->whereNull('partidos.golesEquipoVisitante')
            ->max('idFechas');

        if (! $ultimaFechaDisponibleId) {
            return view('Panel.imprimir.fixture-arbitros-partidos', [
                'partidos' => collect(),
                'EdicionSeleccionada' => $EdicionSeleccionada,
                'horarioFiltro' => $horarioFiltro,
            ]);
        }

        $query = Partido::select(
            'partidos.*',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'f.nombre as nombre_fecha',
            'cat.nombreCategoria as nombre_categoria',
            DB::raw("COALESCE(g.nombre, '') as nombre_grupo")
        )
            ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
            ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
            ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
            ->leftJoin('grupos as g', 'partidos.idGrupo', '=', 'g.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->whereNull('partidos.golesEquipoLocal')
            ->whereNull('partidos.golesEquipoVisitante')
            ->where('partidos.idFechas', $ultimaFechaDisponibleId);

        if ($horarioFiltro) {
            $query->whereRaw('TIME(partidos.horario_datetime) = ?', [$horarioFiltro.':00']);
        }

        $partidos = $query
            ->orderBy('partidos.horario_datetime', 'asc')
            ->orderBy('partidos.cancha', 'asc')
            ->orderBy('cat.nombreCategoria', 'desc')
            ->orderBy('f.id', 'asc')
            ->get();

        return view('Panel.imprimir.fixture-arbitros-partidos', compact('partidos', 'EdicionSeleccionada', 'horarioFiltro'));
    }

    /**
     * API: Obtener grupos de una categoría para el dropdown de impresión.
     */
    public function gruposPorCategoria($idCategoria)
    {
        $grupos = Grupo::where('idCategoria', $idCategoria)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        return response()->json($grupos);
    }

    /**
     * Planilla de cobro de planillas de equipos por partido, ordenados por horario ascendente.
     */
    public function planillaCobro(Request $request)
    {
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

        if (! $EdicionSeleccionada) {
            return redirect()->back()->with('error', 'Debe seleccionar una edición');
        }

        // Última fecha calendario con partidos en esta edición
        $ultimaFechaPartidos = Partido::where('idEdicion', $idEdicion)
            ->whereNotNull('horario_datetime')
            ->max(DB::raw('DATE(horario_datetime)'));

        $ultimaFechaInstanciasFinales = InstanciaFinal::where('idEdicion', $idEdicion)
            ->whereNotNull('horario')
            ->max(DB::raw('DATE(horario)'));

        $ultimaFechaCalendario = collect([$ultimaFechaPartidos, $ultimaFechaInstanciasFinales])
            ->filter()
            ->max();

        if (! $ultimaFechaCalendario) {
            return view('Panel.imprimir.planilla-cobro', [
                'agrupado' => [],
                'EdicionSeleccionada' => $EdicionSeleccionada,
                'fechaJornada' => null,
            ]);
        }

        $partidos = Partido::select(
            'partidos.*',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'cat.nombreCategoria as nombre_categoria'
        )
            ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
            ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
            ->where('partidos.idEdicion', $idEdicion)
            ->whereRaw('DATE(partidos.horario_datetime) = ?', [$ultimaFechaCalendario])
            ->orderByRaw('TIME(partidos.horario_datetime) asc')
            ->orderBy('cat.nombreCategoria', 'desc')
            ->get();

        $instanciasFinales = InstanciaFinal::select(
            'instancias_finales.*',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'cat.nombreCategoria as nombre_categoria'
        )
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->leftJoin('categorias as cat', 'instancias_finales.idCategoria', '=', 'cat.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->whereRaw('DATE(instancias_finales.horario) = ?', [$ultimaFechaCalendario])
            ->orderByRaw('TIME(instancias_finales.horario) asc')
            ->orderBy('cat.nombreCategoria', 'desc')
            ->get();

        $entradas = collect();

        foreach ($partidos as $partido) {
            $horario = $partido->horario_datetime
                ? \Carbon\Carbon::parse($partido->horario_datetime)->format('H:i')
                : 'Por definir';
            $categoria = $partido->nombre_categoria ?? 'Sin categoría';

            $entradas->push(['horario' => $horario, 'categoria' => $categoria, 'equipo' => $partido->nombre_local]);
            $entradas->push(['horario' => $horario, 'categoria' => $categoria, 'equipo' => $partido->nombre_visitante]);
        }

        foreach ($instanciasFinales as $instanciaFinal) {
            $horario = $instanciaFinal->horario
                ? \Carbon\Carbon::parse($instanciaFinal->horario)->format('H:i')
                : 'Por definir';
            $categoria = $instanciaFinal->nombre_categoria ?? 'Sin categoría';

            $entradas->push(['horario' => $horario, 'categoria' => $categoria, 'equipo' => $instanciaFinal->nombre_local]);
            $entradas->push(['horario' => $horario, 'categoria' => $categoria, 'equipo' => $instanciaFinal->nombre_visitante]);
        }

        // Estructura agrupada: horario → categoria → [equipos]
        $agrupado = [];
        foreach ($entradas->sortBy('horario') as $entrada) {
            $agrupado[$entrada['horario']][$entrada['categoria']][] = $entrada['equipo'];
        }

        $fechaJornada = \Carbon\Carbon::parse($ultimaFechaCalendario)->format('d/m/Y');

        return view('Panel.imprimir.planilla-cobro', compact('agrupado', 'EdicionSeleccionada', 'fechaJornada'));
    }

    /**
     * API: Obtener fechas de una categoría para el dropdown de impresión.
     */
    public function fechasPorCategoria($idCategoria)
    {
        $categoria = Categoria::find($idCategoria);
        if (! $categoria) {
            return response()->json([]);
        }

        $fechas = Fecha::where('idEdicion', $categoria->idEdicion)
            ->where(function ($query) use ($idCategoria) {
                $query->where('idCategoria', $idCategoria)
                    ->orWhereNull('idCategoria');
            })
            ->select('id', 'nombre')
            ->orderBy('id')
            ->get();

        return response()->json($fechas);
    }
}
