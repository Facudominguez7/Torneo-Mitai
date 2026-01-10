<?php

namespace App\Http\Controllers;

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Goleador;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\Subcampeon;
use App\Models\TablaGoleador;
use App\Models\TablaPosicion;
use App\Models\User;
use App\Models\VallaMenosVencida;
use App\Models\EquipoEdicion;
use App\Services\EstadisticasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorHome extends Controller
{
    public function index(Request $request)
    {
        // Recuperar todas las ediciones de la base de datos
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
        $ultimaEdicion = null;
        if (is_null($idEdicion)) {
            $ultimaEdicion = Edicion::latest('id')->first();
        }
        $idCategoria = $request->query('idCategoria');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
    $categorias = collect();
    $roles = User::pluck('rol');
        if ($EdicionSeleccionada) {
            $categorias = Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        } elseif ($ultimaEdicion) {
            $categorias = Categoria::where('idEdicion', $ultimaEdicion->id)->orderBy('nombreCategoria', 'desc')->get();
        }

        return view('welcome', compact('ediciones', 'ultimaEdicion', 'EdicionSeleccionada', 'categorias', 'idEdicion', 'idCategoria', 'roles'));
    }
    public function admin(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->query('idEdicion');
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
            $partidosQuery->whereRaw('TIME(partidos.horario_datetime) = ?', [$horarioString . ':00']);
        }

        $partidos = $partidosQuery->orderByDesc('f.id')
            ->orderByDesc('c.nombreCategoria')
            ->when($partidosQuery->whereNotNull('partidos.horario_datetime'), function ($query) {
                return $query->orderBy('partidos.horario_datetime', 'asc');
            })
            ->get()
            ->groupBy('nombre_categoria');

        $categorias = Categoria::where('idEdicion', $idEdicion)->get();

        // Obtener estadísticas de jugadores de las últimas dos fechas
        $estadisticasJugadores = null;
        $jugadoresPorCategoria = null;
        if ($idEdicion) {
            $estadisticasService = new EstadisticasService();
            $estadisticasJugadores = $estadisticasService->obtenerJugadoresDosUltimasFechas($idEdicion);
            $jugadoresPorCategoria = $estadisticasService->obtenerJugadoresPorCategoriaDosUltimasFechas($idEdicion);
        }

        return view('Panel.admin', compact('ediciones', 'EdicionSeleccionada', 'partidos', 'categorias', 'horarioString', 'horariosDisponibles', 'estadisticasJugadores', 'jugadoresPorCategoria'));
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
     * @param Request $request
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
        if (!$request->has('dir')) {
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
        if (!is_null($idCategoria)) {
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
            $fechas = Fecha::where('idEdicion', $idEdicion)
                ->where(function ($query) use ($idCategoria) {
                    $query->where('idCategoria', $idCategoria) // Fechas asociadas a la categoría seleccionada
                        ->orWhereNull('idCategoria'); // O fechas sin categoría
                })
                ->select('id', 'nombre')
                ->orderByDesc('id')
                ->distinct('nombre')
                ->get();

            $grupos = Grupo::where('idCategoria', $idCategoria)
                ->where('grupos.idEdicion', $idEdicion)
                ->leftJoin('categorias as c', 'grupos.idCategoria', '=', 'c.id')
                ->select('grupos.id', 'grupos.nombre', 'grupos.idCategoria', 'c.nombreCategoria as categoria_nombre')
                ->distinct('nombre')
                ->get();
        } else if (isset($EdicionSeleccionada)) {
            // Sin categoría: listar grupos de toda la edición
            $grupos = Grupo::where('grupos.idEdicion', $idEdicion)
                ->leftJoin('categorias as c', 'grupos.idCategoria', '=', 'c.id')
                ->select('grupos.id', 'grupos.nombre', 'grupos.idCategoria', 'c.nombreCategoria as categoria_nombre')
                ->distinct('nombre')
                ->get();
        }

        // Cargar partidos según filtros (con o sin categoría)
        if (isset($EdicionSeleccionada)) {
            if (isset($idFecha)) {
                $partidos = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'cat.nombreCategoria as nombre_categoria')
                    ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
                    ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
                    ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
                    ->where('partidos.idFechas', $idFecha)
                    ->where('partidos.idEdicion', $idEdicion)
                    ->when($idCategoria, fn($q) => $q->where('partidos.idCategoria', $idCategoria))
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
                    ->get();
                $fecha = Fecha::find($idFecha);
                $nombreFecha = $fecha?->nombre;
            } else {
                $partidos = Partido::select('partidos.*', 'el.nombre as nombre_local', 'ev.nombre as nombre_visitante', 'el.foto as foto_local', 'ev.foto as foto_visitante', 'f.nombre as nombre_fecha', 'cat.nombreCategoria as nombre_categoria')
                    ->join('equipos as el', 'partidos.idEquipoLocal', '=', 'el.id')
                    ->join('equipos as ev', 'partidos.idEquipoVisitante', '=', 'ev.id')
                    ->join('fechas as f', 'partidos.idFechas', '=', 'f.id')
                    ->leftJoin('categorias as cat', 'partidos.idCategoria', '=', 'cat.id')
                    ->where('partidos.idEdicion', $idEdicion)
                    ->when($idCategoria, fn($q) => $q->where('partidos.idCategoria', $idCategoria))
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
                    ->get();
            }
        }
        if (isset($idCategoria)) {
            $categoria = Categoria::find($idCategoria);
            $nombreCategoria = $categoria->nombreCategoria;
        } else if (isset($EdicionSeleccionada)) {
            $nombreCategoria = 'Todas las categorías';
        }
        return view('layouts.fixture', compact('nombreFecha', 'nombreCategoria', 'grupos', 'ediciones', 'EdicionSeleccionada', 'categorias', 'idCategoria', 'CategoriaSeleccionada', 'fechas', 'partidos'));
    }

    public function tablaPosiciones(Request $request)
    {
        $ediciones = Edicion::all();
        $idCategoria = $request->query('idCategoria');
        $idGrupo = $request->query('idGrupo');
        $idEdicion = $request->query('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

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
            ->orderBy('tabla_posiciones.diferenciaGoles', 'DESC')
            ->get();

        return view('layouts.tabla-posiciones', compact('tablaPosiciones', 'EdicionSeleccionada', 'nombreCategoria', 'ediciones'));
    }

    public function tablaGoleadores(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idCategoria = $request->idCategoria;
        $search_value = $request->search_value;
        // Nuevos parámetros de ordenamiento (UX): sort = goles|nombre|equipo|categoria, dir = asc|desc
        $sort = $request->query('sort');
        $dir = strtolower($request->query('dir', '')) === 'asc' ? 'asc' : 'desc';

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
        if (!$request->has('dir')) {
            $dir = $sortKey === 'goles' ? 'desc' : 'asc';
        }

        $baseQuery = TablaGoleador::search($search_value)
            ->join('ediciones as ed', 'tabla_goleadores.idEdicion', '=', 'ed.id')
            ->join('equipos as e', 'tabla_goleadores.idEquipo', '=', 'e.id')
            ->join('categorias as cat', 'tabla_goleadores.idCategoria', '=', 'cat.id')
            ->join('jugadores as j', 'tabla_goleadores.dni_jugador', '=', 'j.dni')
            ->select('tabla_goleadores.*', 'e.nombre as nombreEquipo', 'cat.nombreCategoria as nombreCategoria', 'e.id as idEquipo', \Illuminate\Support\Facades\DB::raw("CONCAT(j.apellido, ' ', j.nombre) as nombreJugador"))
            ->where('tabla_goleadores.idEdicion', $idEdicion);

        if (!is_null($idCategoria)) {
            $baseQuery->where('tabla_goleadores.idCategoria', $idCategoria);
        }

        // Aplicar ordenamiento solicitado por el usuario
        $baseQuery->orderBy($sortColumnMap[$sortKey], $dir)
            // Criterios secundarios de desempate
            ->orderBy('cat.nombreCategoria', 'desc')
            ->orderBy('tabla_goleadores.cantidadGoles', 'desc');

        $goleadores_t = $baseQuery->paginate(30);
        // Mantener parámetros en la paginación
        $appendParams = ['idEdicion' => $idEdicion, 'search_value' => $search_value, 'sort' => $sortKey, 'dir' => $dir];
        if (!is_null($idCategoria)) {
            $appendParams['idCategoria'] = $idCategoria;
        }
        $goleadores_t->appends($appendParams);

        $categorias = collect();
        if (isset($EdicionSeleccionada)) {
            $categorias = Categoria::where('idEdicion', $idEdicion)
                ->select('id', 'nombreCategoria')
                ->orderBy('nombreCategoria', 'desc')
                ->get();
        }

        return view('layouts.tabla-goleadores', compact('ediciones', 'EdicionSeleccionada', 'goleadores_t', 'categorias'));
    }
    public function instanciasFinales(Request $request)
    {
        $ediciones = Edicion::all();
        $ultimaEdicion = Edicion::latest('id')->first();
        $idEdicion = $request->query('idEdicion', $ultimaEdicion->id); // Proporcionar un valor por defecto si no está presente en la solicitud
        $EdicionSeleccionada = Edicion::find($idEdicion);

        // Obtener las categorías disponibles para el filtro
        $categorias = Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get();
        $selectedCategoria = $request->query('categoria'); // Obtener la categoría seleccionada

        $partidosQuery = InstanciaFinal::select(
            'instancias_finales.*',
            'fases.nombre as nombre_fase',
            'el.nombre as nombre_local',
            'ev.nombre as nombre_visitante',
            'el.foto as foto_local',
            'ev.foto as foto_visitante',
            'categorias.nombreCategoria as nombre_categoria',
            DB::raw("COALESCE(copas.nombre, '') as nombre_copa")
        )
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftjoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->orderBy('categorias.nombreCategoria', 'desc')
            ->orderBy('copas.nombre', 'asc')
            ->orderByRaw("
            FIELD(fases.nombre, 'Final', 'Semifinal', 'Cuartos de Final') ASC
        ");

        // Aplicar el filtro por categoría si se selecciona una
        if (!empty($selectedCategoria)) {
            $partidosQuery->where('categorias.id', (int) $selectedCategoria);
        }

        $partidos = $partidosQuery->get();

        return view('layouts.instancias-finales', compact('ediciones', 'ultimaEdicion', 'EdicionSeleccionada', 'partidos', 'categorias', 'selectedCategoria'));
    }
}
