<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstanciaFinal\StoreRequest;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Dia;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Services\PartidoService;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;

class ControladorInstanciaFinal extends Controller
{
    use SeleccionarCategoriaTrait;
    protected $partidoService;

    public function __construct(PartidoService $partidoService)
    {
        $this->partidoService = $partidoService;
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

        // Filtro por estado (pendiente/jugado)
        if ($estado === 'pendiente') {
            $query->whereNull('golesEquipoLocal')->whereNull('golesEquipoVisitante');
        } elseif ($estado === 'jugado') {
            $query->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante');
        }

        // Búsqueda por nombre de equipo
        if ($busquedaEquipo) {
            $query->where(function($q) use ($busquedaEquipo) {
                $q->whereHas('equipoLocal', function($q) use ($busquedaEquipo) {
                    $q->where('nombre', 'like', '%' . $busquedaEquipo . '%');
                })->orWhereHas('equipoVisitante', function($q) use ($busquedaEquipo) {
                    $q->where('nombre', 'like', '%' . $busquedaEquipo . '%');
                });
            });
        }

        $partidos = $query
            ->with([
            'equipoLocal:id,nombre,foto',
            'equipoVisitante:id,nombre,foto',
            'fase:id,nombre',
            'categoria:id,nombreCategoria',
            'copa:id,nombre'
            ])
            ->select('instancias_finales.*')
            // Ordenar: pendientes primero (sin goles), luego por fecha
            ->orderByRaw('CASE WHEN golesEquipoLocal IS NULL AND golesEquipoVisitante IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByRaw('DATE(instancias_finales.horario) ASC')
            ->orderBy('instancias_finales.horario', 'asc')
            ->get();

        $categorias = Categoria::whereIn('id', function($query) use ($idEdicion) {
                $query->select('idCategoria')->from('instancias_finales')->where('idEdicion', $idEdicion)->distinct();
            })
            ->select('id', 'nombreCategoria')
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        $fases = Fase::whereIn('id', function($query) use ($idEdicion) {
                $query->select('idFase')->from('instancias_finales')->where('idEdicion', $idEdicion)->distinct();
            })
            ->select('id', 'nombre')
            ->orderBy('nombre', 'desc')
            ->get();

        // Obtener las copas que tienen partidos en esta edición
        $copas = Copa::whereIn('id', function($query) use ($idEdicion) {
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

        return view('Panel.instancia_final.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fases', 'categorias', 'idCategoria', 'nombreFase', 'nombreCategoria', 'copas', 'estado', 'busquedaEquipo', 'idCopa', 'fechaFiltro', 'fechasDisponibles'));
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
        $partido = new InstanciaFinal();
        return view('Panel.instancia_final.create', compact('ediciones', 'partido', 'EdicionSeleccionada', 'fases', 'equipos', 'idCategoria', 'CategoriaSeleccionada', 'copas'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        InstanciaFinal::create($data);
        $idEdicion = $data['idEdicion'];
        $fecha = $request->input('fecha');
        return to_route('instancia_final.index', ['idEdicion' => $idEdicion, 'fecha' => $fecha])->with('status', 'Partido creado con éxito.');
    }

    public function cargarResultadoInstancia(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idPartido = $request->idPartido;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        
        // Buscar manualmente para evitar problemas de Route Model Binding si los nombres de parámetros no coinciden
        $partido = InstanciaFinal::find($idPartido);
        
        if (!$partido) {
            return redirect()->back()->with('error', 'Partido no encontrado.');
        }

        $golesEquipoLocal = $request->input('golesEquipoLocal');
        $golesEquipoVisitante = $request->input('golesEquipoVisitante');
        $penalesEquipoLocal = $request->input('penalesEquipoLocal');
        $penalesEquipoVisitante = $request->input('penalesEquipoVisitante');
        $resultadoGlobal = $request->input('resultadoGlobal');

        if (!is_null($golesEquipoLocal) && !is_null($golesEquipoVisitante)) {
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
                    'partido_id' => $partido->id
                ]);
            }
            
            return to_route('instancia_final.index', ['idEdicion' => $idEdicion, 'fecha' => $request->input('fecha')])->with('status', 'Resultado cargado con éxito.');
        }

        $tipo = 'instancia_final';
        return view('Panel.cargar-resultado', compact('partido', 'ediciones', 'EdicionSeleccionada', 'tipo'));
    }

    public function edit(InstanciaFinal $instancia_final)
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

        return view('Panel.instancia_final.edit', compact('ediciones', 'partido', 'EdicionSeleccionada', 'fases', 'equipos', 'idCategoria', 'CategoriaSeleccionada', 'copas'));
    }

    public function update(StoreRequest $request, InstanciaFinal $instancia_final)
    {
        $data = $request->validated();
        $instancia_final->update($data);
        $idEdicion = $data['idEdicion'];
        $fecha = $request->input('fecha');
        return to_route('instancia_final.index', ['idEdicion' => $idEdicion, 'fecha' => $fecha])->with('status', 'Partido actualizado con éxito.');
    }

    public function destroy(InstanciaFinal $instancia_final, Request $request)
    {
        $instancia_final->delete();
        return to_route('instancia_final.index', ['idEdicion' => $request->idEdicion, 'fecha' => $request->fecha])->with('status', 'Partido eliminado con éxito.');
    }
}
