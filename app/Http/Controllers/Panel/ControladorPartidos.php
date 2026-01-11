<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partido\StoreRequest;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoGrupo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;
use App\Notifications\ResultadoCargado;
use App\Services\PartidoService;
use App\Traits\SeleccionarCategoriaTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ControladorPartidos extends Controller
{
    protected $partidoService;
    use SeleccionarCategoriaTrait;

    public function __construct(PartidoService $partidoService)
    {
        $this->partidoService = $partidoService;
    }

    public function index(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idFecha = $request->idFecha;
        $idGrupo = $request->idGrupo;
        $idCategoria = $request->idCategoria;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;

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

        $partidos = $query
            ->with([
                'equipoLocal:id,nombre,foto',
                'equipoVisitante:id,nombre,foto',
                'fecha:id,nombre',
            ])
            ->select('partidos.*')
            ->orderByDesc('partidos.id')
            ->paginate(7);

        $partidos->appends([
            'idEdicion' => $idEdicion,
            'idFecha' => $idFecha,
            'idGrupo' => $idGrupo,
            'idCategoria' => $idCategoria
        ]);

        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->select('id', 'nombreCategoria')
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        $fechas = Fecha::where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->select('id', 'nombre')
            ->orderByDesc('id')
            ->distinct('nombre')
            ->get();

        $grupos = Grupo::where('idEdicion', $idEdicion)
            ->select('id', 'nombre')
            ->where('idCategoria', $idCategoria)
            ->get();

        $fechas = $fechas->unique('nombre');

        if (isset($idFecha) || isset($idGrupo) || isset($idCategoria)) {
            $fecha = $fechas->firstWhere('id', $idFecha);
            $nombreFecha = $fecha ? $fecha->nombre : null;
            $grupo = $grupos->firstWhere('id', $idGrupo);
            $nombreGrupo = $grupo ? $grupo->nombre : null;
            $categoria = $categorias->firstWhere('id', $idCategoria);
            $nombreCategoria = $categoria ? $categoria->nombreCategoria : null;

            return view('Panel.partido.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fechas', 'categorias', 'grupos', 'idCategoria', 'idGrupo', 'nombreFecha', 'nombreGrupo', 'nombreCategoria'));
        }

        return view('Panel.partido.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fechas', 'categorias', 'grupos', 'idCategoria', 'idGrupo'));
    }

    public function create(Request $request)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $idCategoria = $request->idCategoria;
        $idGrupo = $request->idGrupo;
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
        $partido = new Partido();
        return view('Panel.partido.create', compact('ediciones', 'partido', 'EdicionSeleccionada', 'GrupoSeleccionado', 'fechas', 'equipos',  'grupo', 'idGrupo', 'idCategoria', 'CategoriaSeleccionada'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        Partido::create($data);
        $idEdicion = $data['idEdicion'];
        return to_route('partido.index', ['idEdicion' => $idEdicion])->with('status', 'Partido creado con éxito.');
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
        
        $partido = new Partido();
        
        return view('Panel.partido.create-interzonal', compact(
            'ediciones', 
            'partido', 
            'EdicionSeleccionada', 
            'CategoriaSeleccionada',
            'fechas', 
            'equipos',
            'grupos',
            'idCategoria'
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
        
        return to_route('partido.index', ['idEdicion' => $data['idEdicion']])
            ->with('status', 'Partido interzonal creado con éxito.');
    }

    public function show(Partido $partido)
    {
        return view('partidos.show', compact('partido'));
    }

    public function edit(Partido $partido)
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

        if ($idEdicion > 3) {
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
            'idCategoria'
        ));
    }

    public function update(StoreRequest $request, Partido $partido)
    {
        $data = $request->validated();
        // No permitir actualizar resultados desde esta edición
        unset($data['golesEquipoLocal'], $data['golesEquipoVisitante']);
        // No permitir cambiar Edición, Grupo y Categoría desde editar
        unset($data['idEdicion'], $data['idGrupo'], $data['idCategoria']);
        $partido->update($data);
        return to_route('partido.index', ['idEdicion' => $partido->idEdicion])
            ->with('status', 'Partido actualizado con éxito.');
    }

    public function destroy(Partido $partido)
    {
        $partido->delete();
        return redirect()->route('partidos.index')->with('status', 'Partido eliminado con éxito.');
    }

    public function cargarResultado(Request $request, Partido $partido)
    {
        $ediciones = Edicion::all();
        $idEdicion = $request->idEdicion;
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        $idPartido = $request->idPartido;
        $partido = Partido::with(['equipoLocal', 'equipoVisitante', 'grupo', 'categoria'])->findOrFail($idPartido);

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

            // Ejecutar envío en segundo plano (Fire and Forget)
            try {
                $path = base_path();
                // Redirigir salida a /dev/null y usar & para segundo plano
                // Nota: Esto funciona en Linux/Unix. 
                $command = "php {$path}/artisan mitai:notificar-resultado {$partido->id} > /dev/null 2>&1 &";
                exec($command);
                Log::info("Lanzado proceso de fondo: $command");
            } catch (\Exception $e) {
                Log::error("No se pudo lanzar proceso de fondo: " . $e->getMessage());
            }

            // Si es AJAX o normal, devolvemos exito inmediatamente
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Resultado guardado. Notificaciones enviándose en segundo plano.']);
            }

            return to_route('partido.index', ['idEdicion' => $idEdicion])->with('status', 'Resultado guardado. Notificaciones enviándose en segundo plano.');
        }

        return view('Panel.cargar-resultado', compact('partido', 'ediciones', 'EdicionSeleccionada'));
    }

    /**
     * Enviar notificación push a todos los usuarios suscritos cuando se carga un resultado.
     */
    private function enviarNotificacionResultado(Partido $partido, int $golesLocal, int $golesVisitante): void
    {
        try {
            $datosPartido = (object) [
                'id' => $partido->id,
                'idEdicion' => $partido->idEdicion,
                'nombre_local' => $partido->equipoLocal->nombre ?? 'Equipo Local',
                'nombre_visitante' => $partido->equipoVisitante->nombre ?? 'Equipo Visitante',
                'golesEquipoLocal' => $golesLocal,
                'golesEquipoVisitante' => $golesVisitante,
                'nombre_categoria' => $partido->categoria->nombreCategoria ?? '',
            ];

            $usuariosSuscritos = User::where(function ($q) {
                $q->where(function($q2) {
                    $q2->where('email_subscribed', true)
                       ->whereNotNull('email_verified_at');
                })
                ->orWhereHas('pushSubscriptions');
            })->get();

            foreach ($usuariosSuscritos as $usuario) {
                $usuario->notify(new ResultadoCargado($datosPartido));
            }

            Log::info('Notificaciones push enviadas', [
                'partido_id' => $partido->id,
                'usuarios_notificados' => $usuariosSuscritos->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones push', [
                'partido_id' => $partido->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
