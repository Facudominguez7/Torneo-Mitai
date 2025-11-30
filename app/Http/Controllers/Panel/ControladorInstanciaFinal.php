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

        $partidos = $query
            ->with([
            'equipoLocal:id,nombre,foto',
            'equipoVisitante:id,nombre,foto',
            'fase:id,nombre',
            'categoria:id,nombreCategoria'
            ])
            ->select('instancias_finales.*')
            ->orderByRaw('DATE(instancias_finales.horario) desc')
            ->orderBy('instancias_finales.horario', 'asc')
            ->get();

        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->select('id', 'nombreCategoria')
            ->orderBy('nombreCategoria', 'desc')
            ->get();

        $fases = Fase::query()
            ->select('id', 'nombre')
            ->orderBy('nombre', 'desc')
            ->get();

        $nombreFase = $fases->firstWhere('id', $idFase)->nombre ?? null;
        $nombreCategoria = $categorias->firstWhere('id', $idCategoria)->nombreCategoria ?? null;

        return view('Panel.instancia_final.index', compact('partidos', 'ediciones', 'EdicionSeleccionada', 'fases', 'categorias', 'idCategoria', 'nombreFase', 'nombreCategoria'));
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
        return to_route('instancia_final.index', ['idEdicion' => $idEdicion])->with('status', 'Partido creado con éxito.');
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
            return to_route('instancia_final.index', ['idEdicion' => $idEdicion])->with('status', 'Resultado cargado con éxito.');
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
        return to_route('instancia_final.index', ['idEdicion' => $idEdicion])->with('status', 'Partido actualizado con éxito.');
    }

    public function destroy(InstanciaFinal $instancia_final, Request $request)
    {
        $instancia_final->delete();
        return to_route('instancia_final.index', ['idEdicion' => $request->idEdicion])->with('status', 'Partido eliminado con éxito.');
    }
}
