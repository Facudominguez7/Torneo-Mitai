<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use App\Services\Panel\ParticipacionesJugadoresService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la vista de estadísticas globales de jugadores.
 *
 * Permite visualizar todos los jugadores del torneo con sus equipos
 * y estadísticas tanto globales como por edición.
 * Considera que un jugador puede participar en múltiples categorías.
 */
class ControladorEstadisticasJugadores extends Controller
{
    public function __construct(
        private readonly ParticipacionesJugadoresService $participaciones,
    ) {}

    /**
     * Mostrar la vista de estadísticas de jugadores.
     *
     * Obtiene los jugadores activos ordenados por participaciones reales
     * (descendente), con contadores de totales y de ambos lados del umbral de
     * 5 partidos. La cifra y el listado salen del mismo servicio (#202).
     */
    public function index(Request $request): View
    {
        // Obtener todas las ediciones para filtros y selector
        $ediciones = Edicion::orderBy('id', 'desc')->get();

        // Edición seleccionada (opcional)
        $idEdicion = $request->filled('idEdicion') ? (int) $request->input('idEdicion') : null;
        $EdicionSeleccionada = $idEdicion !== null ? Edicion::find($idEdicion) : null;

        // Valor de búsqueda
        $busqueda = $request->input('busqueda');

        // Filtro de categoría (solo tiene sentido dentro de una edición)
        $idCategoria = $request->filled('idCategoria') ? (int) $request->input('idCategoria') : null;

        // Umbral de participaciones del listado: todos o 5 o más
        $umbral = $request->input('umbral') === ParticipacionesJugadoresService::UMBRAL_CINCO
            ? ParticipacionesJugadoresService::UMBRAL_CINCO
            : ParticipacionesJugadoresService::UMBRAL_TODOS;

        // Listado paginado de jugadores activos con sus participaciones reales
        $jugadoresData = $this->obtenerJugadoresConEstadisticas($idEdicion, $idCategoria, $busqueda, $umbral);

        // Contadores de jugadores por cantidad de participaciones
        $contadores = $this->participaciones->obtenerContadores($idEdicion, $idCategoria, $busqueda);

        // Obtener conteo de jugadores por edición
        $conteoPorEdicion = $this->obtenerConteoPorEdicion();

        // Obtener categorías para la edición seleccionada (si aplica)
        $categorias = $idEdicion !== null
            ? Categoria::where('idEdicion', $idEdicion)->orderBy('nombreCategoria', 'desc')->get()
            : collect();

        return view('Panel.estadisticas-jugadores.index', compact(
            'ediciones',
            'EdicionSeleccionada',
            'jugadoresData',
            'contadores',
            'conteoPorEdicion',
            'busqueda',
            'categorias',
            'idCategoria',
            'umbral'
        ));
    }

    /**
     * Obtener jugadores únicos con sus estadísticas agregadas.
     *
     * Agrupa jugadores por DNI para manejar casos donde un jugador
     * participa en múltiples categorías. Ordena por participaciones DESC.
     *
     * @param  int|null  $idEdicion  ID de edición para filtrar
     * @param  int|null  $idCategoria  ID de categoría para filtrar
     * @param  string|null  $busqueda  Término de búsqueda
     * @param  string  $umbral  Umbral de participaciones del listado
     * @return LengthAwarePaginator
     */
    private function obtenerJugadoresConEstadisticas(
        ?int $idEdicion,
        ?int $idCategoria,
        ?string $busqueda,
        string $umbral
    ) {
        $jugadoresPaginados = $this->participaciones
            ->consultaListado($idEdicion, $idCategoria, $busqueda, $umbral)
            ->paginate(20);

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
     * @param  Jugador  $jugador
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

        // Estadísticas por edición, con los partidos contados como
        // participaciones reales igual que el listado (#202).
        $jugador->estadisticas_por_edicion = $this->participaciones
            ->obtenerEstadisticasPorEdicion((string) $jugador->dni, $idEdicion);

        // Calcular totales
        $jugador->goles_calculados = $jugador->estadisticas_por_edicion->sum('goles');
        $jugador->tarjetas_rojas_calculadas = $jugador->estadisticas_por_edicion->sum('tarjetas_rojas');

        // partidos_calculados ya viene de la query principal

        return $jugador;
    }

    /**
     * Obtener el conteo de jugadores por edición.
     *
     * Usa PlanillaJugador para obtener datos históricos de todas las ediciones
     * basándose en los registros de partidos jugados.
     *
     * @return Collection
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
