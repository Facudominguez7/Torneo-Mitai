<?php

namespace App\Services\Panel;

use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as ConsultaBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única de las participaciones reales de un jugador (#202).
 *
 * Un jugador participó de un partido cuando quedó marcado como asistente en
 * la planilla de un partido efectivamente disputado: los partidos necesitan
 * `jugado = 1` y no haber sido adjudicados por «No vino» (un 3-0
 * administrativo no es un partido jugado físicamente) y las instancias
 * finales necesitan los dos resultados cargados.
 *
 * Las planillas son polimórficas (`partido_type` + `partido_id`), así que
 * cada tipo de partido se resuelve contra su propia unión y los ids nunca
 * se mezclan entre tablas.
 */
class ParticipacionesJugadoresService
{
    /**
     * Rango aceptado por el filtro «Participaciones mínimas».
     */
    public const DESDE_MINIMO = 1;

    public const DESDE_MAXIMO = 999;

    /**
     * Umbral vigente para separar «menos de N» de «N o más». Sale de
     * config/estadisticas.php para que el KPI y el listado compartan la
     * misma cifra sin números escritos a mano (#202, L2).
     */
    public function participacionesMinimas(): int
    {
        $umbral = (int) config('estadisticas.participaciones_minimas');

        return min(self::DESDE_MAXIMO, max(self::DESDE_MINIMO, $umbral));
    }

    /**
     * Normaliza el filtro «Participaciones mínimas» del listado. Devuelve
     * null —sin filtro— cuando el valor viene vacío, no es un entero o cae
     * fuera del rango aceptado.
     */
    public function normalizarDesde(mixed $desde): ?int
    {
        $valor = filter_var($desde, FILTER_VALIDATE_INT);

        if ($valor === false || $valor < self::DESDE_MINIMO || $valor > self::DESDE_MAXIMO) {
            return null;
        }

        return $valor;
    }

    /**
     * Condición SQL de participación real, con dos marcadores que se
     * completan con {@see valoresCondicionParticipacion()}.
     */
    public function condicionParticipacionReal(): string
    {
        return '(planilla_jugadores.asistio = 1 AND ('
            .'(planilla_jugadores.partido_type = ? AND partidos.jugado = 1 AND partidos.ganador_por_no_vino IS NULL)'
            .' OR (planilla_jugadores.partido_type = ? AND instancias_finales.golesEquipoLocal IS NOT NULL AND instancias_finales.golesEquipoVisitante IS NOT NULL)'
            .'))';
    }

    /**
     * @return array<int, string>
     */
    public function valoresCondicionParticipacion(): array
    {
        return [Partido::class, InstanciaFinal::class];
    }

    /**
     * Consulta paginable del listado: un jugador por DNI con la cantidad de
     * participaciones reales, ordenada de mayor a menor. Con `$desde` solo
     * quedan los jugadores que alcanzan esa cantidad.
     *
     * @return Builder<int, Jugador>
     */
    public function consultaListado(
        ?int $idEdicion,
        ?int $idCategoria = null,
        ?string $busqueda = null,
        ?int $desde = null,
    ): Builder {
        $consulta = $this->consultaBase($idEdicion, $idCategoria, $busqueda);

        if ($desde !== null) {
            $consulta->whereRaw(
                'COALESCE(participaciones.total_participaciones, 0) >= ?',
                [$desde]
            );
        }

        return $consulta
            ->orderBy('partidos_calculados', 'desc')
            ->orderBy('jugadores.apellido')
            ->orderBy('jugadores.nombre');
    }

    /**
     * Contadores que acompañan al listado: total de jugadores activos y
     * cuántos quedan por debajo y desde el umbral pedido.
     *
     * @return array{total: int, umbral: int, menos_de_umbral: int, umbral_o_mas: int}
     */
    public function obtenerContadores(
        ?int $idEdicion,
        ?int $idCategoria = null,
        ?string $busqueda = null,
        ?int $umbral = null,
    ): array {
        $umbral ??= $this->participacionesMinimas();

        $fila = DB::query()
            ->fromSub($this->consultaBase($idEdicion, $idCategoria, $busqueda)->toBase(), 'listado')
            ->selectRaw(
                'COUNT(*) as total,'
                .' SUM(CASE WHEN listado.partidos_calculados < ? THEN 1 ELSE 0 END) as menos_de_umbral,'
                .' SUM(CASE WHEN listado.partidos_calculados >= ? THEN 1 ELSE 0 END) as umbral_o_mas',
                [$umbral, $umbral]
            )
            ->first();

        return [
            'total' => (int) ($fila->total ?? 0),
            'umbral' => $umbral,
            'menos_de_umbral' => (int) ($fila->menos_de_umbral ?? 0),
            'umbral_o_mas' => (int) ($fila->umbral_o_mas ?? 0),
        ];
    }

    /**
     * Estadísticas por edición de un jugador. Goles y tarjetas se leen tal
     * como están cargados en la planilla; «partidos» respeta la regla de
     * participación real para no contradecir el total del listado.
     *
     * @return Collection<int, object>
     */
    public function obtenerEstadisticasPorEdicion(string $dni, ?int $idEdicion = null): Collection
    {
        $consulta = DB::table('planilla_jugadores')
            ->join('ediciones', 'planilla_jugadores.idEdicion', '=', 'ediciones.id')
            ->where('planilla_jugadores.dni_jugador', $dni);

        $this->unirPartidos($consulta);

        return $consulta
            ->select([
                'planilla_jugadores.idEdicion',
                'ediciones.nombre as nombre_edicion',
            ])
            ->selectRaw('SUM(planilla_jugadores.goles) as goles')
            ->selectRaw(
                'SUM(CASE WHEN '.$this->condicionParticipacionReal().' THEN 1 ELSE 0 END) as partidos',
                $this->valoresCondicionParticipacion()
            )
            ->selectRaw('COUNT(CASE WHEN planilla_jugadores.tarjeta_roja = 1 THEN 1 END) as tarjetas_rojas')
            ->when($idEdicion !== null, fn (ConsultaBase $query) => $query->where('planilla_jugadores.idEdicion', $idEdicion))
            ->groupBy('planilla_jugadores.idEdicion', 'ediciones.nombre')
            ->orderByDesc('planilla_jugadores.idEdicion')
            ->get();
    }

    /**
     * Jugadores activos de la lista de buena fe con sus participaciones
     * reales, sin ordenar ni paginar: base común del listado y los contadores.
     *
     * @return Builder<int, Jugador>
     */
    private function consultaBase(?int $idEdicion, ?int $idCategoria = null, ?string $busqueda = null): Builder
    {
        $consulta = Jugador::query()
            ->select([
                'jugadores.dni',
                'jugadores.nombre',
                'jugadores.apellido',
                DB::raw('COALESCE(participaciones.total_participaciones, 0) as partidos_calculados'),
            ])
            ->leftJoinSub(
                $this->consultaParticipaciones($idEdicion, $idCategoria),
                'participaciones',
                fn ($join) => $join->on('jugadores.dni', '=', 'participaciones.dni_jugador')
            )
            ->whereExists(function (ConsultaBase $subconsulta) use ($idEdicion, $idCategoria): void {
                $subconsulta->select(DB::raw(1))
                    ->from('lista_buena_fe')
                    ->whereColumn('lista_buena_fe.dni_jugador', 'jugadores.dni')
                    ->where('lista_buena_fe.activo', true)
                    ->when($idEdicion !== null, fn (ConsultaBase $query) => $query->where('lista_buena_fe.idEdicion', $idEdicion))
                    ->when($idCategoria !== null, fn (ConsultaBase $query) => $query->where('lista_buena_fe.idCategoria', $idCategoria));
            });

        if (filled($busqueda)) {
            $consulta->where(function (Builder $query) use ($busqueda): void {
                $query->where('jugadores.nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('jugadores.apellido', 'LIKE', "%{$busqueda}%")
                    ->orWhere('jugadores.dni', 'LIKE', "%{$busqueda}%");
            });
        }

        return $consulta;
    }

    /**
     * Participaciones reales por DNI, acotadas a la edición y la categoría
     * pedidas.
     */
    private function consultaParticipaciones(?int $idEdicion, ?int $idCategoria): ConsultaBase
    {
        $consulta = DB::table('planilla_jugadores')
            ->select('planilla_jugadores.dni_jugador', DB::raw('COUNT(*) as total_participaciones'))
            ->groupBy('planilla_jugadores.dni_jugador');

        $this->unirParticipacionesReales($consulta);

        return $consulta
            ->when($idEdicion !== null, fn (ConsultaBase $query) => $query->where('planilla_jugadores.idEdicion', $idEdicion))
            ->when($idCategoria !== null, fn (ConsultaBase $query) => $query->where('planilla_jugadores.idCategoria', $idCategoria));
    }

    /**
     * Une los dos tipos de partido posible y deja solo las participaciones
     * reales.
     */
    private function unirParticipacionesReales(ConsultaBase $consulta): ConsultaBase
    {
        $this->unirPartidos($consulta);

        return $consulta->whereRaw(
            $this->condicionParticipacionReal(),
            $this->valoresCondicionParticipacion()
        );
    }

    /**
     * Une `partidos` e `instancias_finales` por el morfismo de la planilla,
     * cada uno con su propia condición de `partido_type`. Las dos uniones son
     * a lo sumo una fila por planilla, así que no duplican goles ni filas.
     */
    private function unirPartidos(ConsultaBase $consulta): void
    {
        $consulta
            ->leftJoin('partidos', function ($join): void {
                $join->on('partidos.id', '=', 'planilla_jugadores.partido_id')
                    ->where('planilla_jugadores.partido_type', '=', Partido::class);
            })
            ->leftJoin('instancias_finales', function ($join): void {
                $join->on('instancias_finales.id', '=', 'planilla_jugadores.partido_id')
                    ->where('planilla_jugadores.partido_type', '=', InstanciaFinal::class);
            });
    }
}
