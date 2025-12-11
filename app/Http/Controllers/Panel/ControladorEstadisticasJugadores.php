<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la vista de estadísticas globales de jugadores.
 * 
 * Permite visualizar todos los jugadores del torneo con sus equipos
 * y estadísticas tanto globales como por edición.
 * Considera que un jugador puede participar en múltiples categorías.
 * 
 * @package App\Http\Controllers\Panel
 */
class ControladorEstadisticasJugadores extends Controller
{
    /**
     * Mostrar la vista de estadísticas de jugadores.
     * 
     * Obtiene todos los jugadores registrados, ordenados por cantidad de
     * partidos jugados (descendente). Muestra contadores de jugadores
     * totales, con ≤5 partidos y con >5 partidos.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        // Obtener todas las ediciones para filtros y selector
        $ediciones = Edicion::orderBy('id', 'desc')->get();
        
        // Edición seleccionada (opcional)
        $idEdicion = $request->input('idEdicion');
        $EdicionSeleccionada = $idEdicion ? Edicion::find($idEdicion) : null;
        
        // Valor de búsqueda
        $busqueda = $request->input('busqueda');
        
        // Obtener jugadores únicos con estadísticas agregadas
        $jugadoresData = $this->obtenerJugadoresConEstadisticas($idEdicion, $busqueda);
        
        // Obtener contadores de partidos
        $contadores = $this->obtenerContadoresPartidos($idEdicion, $busqueda);
        
        // Obtener conteo de jugadores por edición
        $conteoPorEdicion = $this->obtenerConteoPorEdicion();
        
        // Obtener categorías para la edición seleccionada (si aplica)
        $categorias = $idEdicion 
            ? Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get() 
            : collect();
        
        // Filtro de categoría
        $idCategoria = $request->input('idCategoria');
        
        return view('Panel.estadisticas-jugadores.index', compact(
            'ediciones',
            'EdicionSeleccionada',
            'jugadoresData',
            'contadores',
            'conteoPorEdicion',
            'busqueda',
            'categorias',
            'idCategoria'
        ));
    }

    /**
     * Obtener jugadores únicos con sus estadísticas agregadas.
     * 
     * Agrupa jugadores por DNI para manejar casos donde un jugador
     * participa en múltiples categorías. Ordena por partidos jugados DESC.
     *
     * @param int|null $idEdicion ID de edición para filtrar
     * @param string|null $busqueda Término de búsqueda
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    private function obtenerJugadoresConEstadisticas(?int $idEdicion, ?string $busqueda)
    {
        // Subquery para contar partidos por jugador
        $subqueryPartidos = PlanillaJugador::query()
            ->select('dni_jugador', DB::raw('COUNT(CASE WHEN asistio = 1 THEN 1 END) as total_partidos'))
            ->when($idEdicion, fn($q) => $q->where('idEdicion', $idEdicion))
            ->groupBy('dni_jugador');

        // Query principal: obtener jugadores únicos
        $query = Jugador::query()
            ->select([
                'jugadores.dni',
                'jugadores.nombre',
                'jugadores.apellido',
                DB::raw('COALESCE(partidos_sub.total_partidos, 0) as partidos_calculados'),
            ])
            ->leftJoinSub($subqueryPartidos, 'partidos_sub', function ($join) {
                $join->on('jugadores.dni', '=', 'partidos_sub.dni_jugador');
            })
            ->whereExists(function ($q) use ($idEdicion) {
                $q->select(DB::raw(1))
                  ->from('lista_buena_fe')
                  ->whereColumn('lista_buena_fe.dni_jugador', 'jugadores.dni')
                  ->where('lista_buena_fe.activo', true);
                
                if ($idEdicion) {
                    $q->where('lista_buena_fe.idEdicion', $idEdicion);
                }
            });

        // Aplicar filtro de búsqueda
        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('jugadores.nombre', 'LIKE', "%{$busqueda}%")
                  ->orWhere('jugadores.apellido', 'LIKE', "%{$busqueda}%")
                  ->orWhere('jugadores.dni', 'LIKE', "%{$busqueda}%");
            });
        }

        // Ordenar por partidos jugados (descendente), luego por apellido
        $query->orderBy('partidos_calculados', 'desc')
              ->orderBy('jugadores.apellido')
              ->orderBy('jugadores.nombre');

        // Paginar resultados
        $jugadoresPaginados = $query->paginate(20);

        // Enriquecer cada jugador con estadísticas completas
        $jugadoresPaginados->getCollection()->transform(function ($jugador) use ($idEdicion) {
            return $this->enriquecerJugadorCompleto($jugador, $idEdicion);
        });

        return $jugadoresPaginados;
    }

    /**
     * Enriquecer un jugador con todas sus estadísticas y equipos.
     * 
     * Obtiene los equipos donde juega, estadísticas por edición, 
     * y totales globales.
     *
     * @param Jugador $jugador
     * @param int|null $idEdicion
     * @return Jugador
     */
    private function enriquecerJugadorCompleto($jugador, ?int $idEdicion)
    {
        // Obtener equipos/categorías donde está registrado
        $equiposQuery = ListaBuenaFe::query()
            ->select([
                'equipos.nombre as nombre_equipo',
                'categorias.nombreCategoria as nombre_categoria',
                'ediciones.nombre as nombre_edicion',
                'lista_buena_fe.idEdicion',
            ])
            ->join('equipos', 'lista_buena_fe.idEquipo', '=', 'equipos.id')
            ->join('categorias', 'lista_buena_fe.idCategoria', '=', 'categorias.id')
            ->join('ediciones', 'lista_buena_fe.idEdicion', '=', 'ediciones.id')
            ->where('lista_buena_fe.dni_jugador', $jugador->dni)
            ->where('lista_buena_fe.activo', true);

        if ($idEdicion) {
            $equiposQuery->where('lista_buena_fe.idEdicion', $idEdicion);
        }

        $jugador->equipos = $equiposQuery->orderBy('ediciones.id', 'desc')->get();

        // Obtener estadísticas por edición
        $statsQuery = PlanillaJugador::query()
            ->select([
                'planilla_jugadores.idEdicion',
                'ediciones.nombre as nombre_edicion',
                DB::raw('SUM(planilla_jugadores.goles) as goles'),
                DB::raw('COUNT(CASE WHEN planilla_jugadores.asistio = 1 THEN 1 END) as partidos'),
                DB::raw('COUNT(CASE WHEN planilla_jugadores.tarjeta_roja = 1 THEN 1 END) as tarjetas_rojas'),
            ])
            ->join('ediciones', 'planilla_jugadores.idEdicion', '=', 'ediciones.id')
            ->where('planilla_jugadores.dni_jugador', $jugador->dni)
            ->groupBy('planilla_jugadores.idEdicion', 'ediciones.nombre')
            ->orderBy('planilla_jugadores.idEdicion', 'desc');

        if ($idEdicion) {
            $statsQuery->where('planilla_jugadores.idEdicion', $idEdicion);
        }

        $jugador->estadisticas_por_edicion = $statsQuery->get();

        // Calcular totales
        $jugador->goles_calculados = $jugador->estadisticas_por_edicion->sum('goles');
        $jugador->tarjetas_rojas_calculadas = $jugador->estadisticas_por_edicion->sum('tarjetas_rojas');
        
        // partidos_calculados ya viene de la query principal

        return $jugador;
    }

    /**
     * Obtener contadores de jugadores por cantidad de partidos.
     * 
     * Retorna total de jugadores, cantidad con ≤5 partidos y >5 partidos.
     *
     * @param int|null $idEdicion
     * @param string|null $busqueda
     * @return array
     */
    private function obtenerContadoresPartidos(?int $idEdicion, ?string $busqueda): array
    {
        // Subquery para contar partidos por jugador
        $subquery = PlanillaJugador::query()
            ->select('dni_jugador', DB::raw('COUNT(CASE WHEN asistio = 1 THEN 1 END) as total_partidos'))
            ->when($idEdicion, fn($q) => $q->where('idEdicion', $idEdicion))
            ->groupBy('dni_jugador');

        // Query para obtener jugadores con sus partidos
        $query = Jugador::query()
            ->select([
                'jugadores.dni',
                DB::raw('COALESCE(partidos_sub.total_partidos, 0) as total_partidos'),
            ])
            ->leftJoinSub($subquery, 'partidos_sub', function ($join) {
                $join->on('jugadores.dni', '=', 'partidos_sub.dni_jugador');
            })
            ->whereExists(function ($q) use ($idEdicion) {
                $q->select(DB::raw(1))
                  ->from('lista_buena_fe')
                  ->whereColumn('lista_buena_fe.dni_jugador', 'jugadores.dni')
                  ->where('lista_buena_fe.activo', true);
                
                if ($idEdicion) {
                    $q->where('lista_buena_fe.idEdicion', $idEdicion);
                }
            });

        // Aplicar filtro de búsqueda
        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('jugadores.nombre', 'LIKE', "%{$busqueda}%")
                  ->orWhere('jugadores.apellido', 'LIKE', "%{$busqueda}%")
                  ->orWhere('jugadores.dni', 'LIKE', "%{$busqueda}%");
            });
        }

        $resultados = $query->get();

        return [
            'total' => $resultados->count(),
            'cinco_o_menos' => $resultados->where('total_partidos', '<=', 5)->count(),
            'mas_de_cinco' => $resultados->where('total_partidos', '>', 5)->count(),
        ];
    }

    /**
     * Obtener el conteo de jugadores por edición.
     * 
     * Usa PlanillaJugador para obtener datos históricos de todas las ediciones
     * basándose en los registros de partidos jugados.
     *
     * @return \Illuminate\Support\Collection
     */
    private function obtenerConteoPorEdicion()
    {
        return PlanillaJugador::query()
            ->select([
                'planilla_jugadores.idEdicion',
                'ediciones.nombre as nombre_edicion',
                DB::raw('COUNT(DISTINCT planilla_jugadores.dni_jugador) as total_jugadores'),
            ])
            ->join('ediciones', 'planilla_jugadores.idEdicion', '=', 'ediciones.id')
            ->groupBy('planilla_jugadores.idEdicion', 'ediciones.nombre')
            ->orderBy('planilla_jugadores.idEdicion', 'desc')
            ->get();
    }
}
