<?php

namespace App\Services\Panel;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\TablaGoleador;
use App\Models\TablaPosicion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el detalle de un equipo en una edición: partidos jugados,
 * estadísticas y goleadores.
 *
 * @phpstan-type Totales array{pj: int, g: int, e: int, p: int, gf: int, gc: int, dg: int}
 * @phpstan-type FilaPartido array{
 *     tipo: string,
 *     id: int,
 *     fecha: ?Carbon,
 *     etapa: string,
 *     detalle: ?string,
 *     condicion: string,
 *     rival: ?Equipo,
 *     golesFavor: int,
 *     golesContra: int,
 *     penalesFavor: ?int,
 *     penalesContra: ?int,
 *     resultado: string
 * }
 * @phpstan-type Goleador array{nombre: string, goles: int}
 */
class DetalleEquipo
{
    private const TOP_RANKING = 5;

    /**
     * Resuelve la edición: la pedida, la propia del equipo o la más reciente.
     */
    public function resolverEdicion(Equipo $equipo, ?int $idEdicion): ?int
    {
        if ($idEdicion) {
            return $idEdicion;
        }

        return $equipo->idEdicion ? (int) $equipo->idEdicion : Edicion::query()->max('id');
    }

    /**
     * @return array{
     *     idEdicion: ?int,
     *     categoria: ?Categoria,
     *     partidos: list<FilaPartido>,
     *     estadisticas: array{
     *         faseGrupos: list<array{grupo: ?string, puntos: int, pj: int, g: int, e: int, p: int, gf: int, gc: int, dg: int}>,
     *         instanciasFinales: Totales,
     *         total: Totales
     *     },
     *     goleadores: array{maximos: list<Goleador>, ranking: list<Goleador>}
     * }
     */
    public function obtener(Equipo $equipo, ?int $idEdicion = null): array
    {
        $idEdicion = $this->resolverEdicion($equipo, $idEdicion);
        $categoria = $this->categoriaEnEdicion($equipo, $idEdicion);

        $partidos = $this->partidosJugados($equipo, $idEdicion);
        $finales = $partidos->where('tipo', 'instanciaFinal');

        return [
            'idEdicion' => $idEdicion,
            'categoria' => $categoria,
            'partidos' => $partidos->values()->all(),
            'estadisticas' => [
                'faseGrupos' => $this->tablaPosiciones($equipo, $idEdicion),
                'instanciasFinales' => $this->totales($finales),
                'total' => $this->totales($partidos),
            ],
            'goleadores' => $this->goleadores($equipo, $idEdicion, $categoria?->id ?? $equipo->idCategoria),
        ];
    }

    /**
     * Categoría del equipo en esa edición: un equipo traído de otra edición
     * guarda en equipos.idCategoria la categoría de origen, no la actual.
     */
    private function categoriaEnEdicion(Equipo $equipo, ?int $idEdicion): ?Categoria
    {
        $idCategoria = EquipoEdicion::query()
            ->where('idEquipo', $equipo->id)
            ->where('idEdicion', $idEdicion)
            ->value('idCategoria');

        return Categoria::find($idCategoria ?? $equipo->idCategoria);
    }

    /**
     * @return Collection<int, FilaPartido>
     */
    private function partidosJugados(Equipo $equipo, ?int $idEdicion): Collection
    {
        $partidos = Partido::query()
            ->with(['equipoLocal', 'equipoVisitante', 'fecha', 'grupo'])
            ->where('idEdicion', $idEdicion)
            ->where(fn ($q) => $q->where('idEquipoLocal', $equipo->id)->orWhere('idEquipoVisitante', $equipo->id))
            ->where('jugado', '>', 0)
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante')
            ->get()
            ->map(fn (Partido $p): array => $this->filaPartido($equipo, $p));

        $finales = InstanciaFinal::query()
            ->with(['equipoLocal', 'equipoVisitante', 'fase', 'copa'])
            ->where('idEdicion', $idEdicion)
            ->where(fn ($q) => $q->where('idEquipoLocal', $equipo->id)->orWhere('idEquipoVisitante', $equipo->id))
            ->whereNotNull('golesEquipoLocal')
            ->whereNotNull('golesEquipoVisitante')
            ->get()
            ->map(fn (InstanciaFinal $f): array => $this->filaFinal($equipo, $f));

        return $partidos->concat($finales)
            ->sortBy(fn (array $fila): int => $fila['fecha']?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return FilaPartido
     */
    private function filaPartido(Equipo $equipo, Partido $p): array
    {
        $esLocal = (int) $p->idEquipoLocal === $equipo->id;
        $favor = (int) ($esLocal ? $p->golesEquipoLocal : $p->golesEquipoVisitante);
        $contra = (int) ($esLocal ? $p->golesEquipoVisitante : $p->golesEquipoLocal);

        return [
            'tipo' => 'partido',
            'id' => $p->id,
            'fecha' => $p->horario_datetime ? Carbon::parse($p->horario_datetime) : null,
            'etapa' => (string) $p->fecha?->nombre,
            'detalle' => $p->grupo?->nombre,
            'condicion' => $esLocal ? 'Local' : 'Visitante',
            'rival' => $esLocal ? $p->equipoVisitante : $p->equipoLocal,
            'golesFavor' => $favor,
            'golesContra' => $contra,
            'penalesFavor' => null,
            'penalesContra' => null,
            'resultado' => $favor <=> $contra ? ($favor > $contra ? 'G' : 'P') : 'E',
        ];
    }

    /**
     * En finales, un empate en goles se define por resultado global ("a-b",
     * local-visitante) cuando es interpretable, y si no por penales.
     *
     * @return FilaPartido
     */
    private function filaFinal(Equipo $equipo, InstanciaFinal $f): array
    {
        $esLocal = (int) $f->idEquipoLocal === $equipo->id;
        $favor = (int) ($esLocal ? $f->golesEquipoLocal : $f->golesEquipoVisitante);
        $contra = (int) ($esLocal ? $f->golesEquipoVisitante : $f->golesEquipoLocal);
        $penalesLocal = $f->penalesEquipoLocal;
        $penalesVisitante = $f->penalesEquipoVisitante;
        $tienePenales = $penalesLocal !== null && $penalesVisitante !== null;

        $resultado = 'E';
        if ($favor !== $contra) {
            $resultado = $favor > $contra ? 'G' : 'P';
        } elseif (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', (string) $f->resultadoGlobal, $m) && $m[1] !== $m[2]) {
            $ganaLocal = (int) $m[1] > (int) $m[2];
            $resultado = $ganaLocal === $esLocal ? 'G' : 'P';
        } elseif ($tienePenales && $penalesLocal !== $penalesVisitante) {
            $ganaLocal = $penalesLocal > $penalesVisitante;
            $resultado = $ganaLocal === $esLocal ? 'G' : 'P';
        }

        return [
            'tipo' => 'instanciaFinal',
            'id' => $f->id,
            'fecha' => $f->horario ? Carbon::parse($f->horario) : null,
            'etapa' => (string) $f->fase?->nombre,
            'detalle' => $f->copa?->nombre,
            'condicion' => $esLocal ? 'Local' : 'Visitante',
            'rival' => $esLocal ? $f->equipoVisitante : $f->equipoLocal,
            'golesFavor' => $favor,
            'golesContra' => $contra,
            'penalesFavor' => $tienePenales && $favor === $contra ? (int) ($esLocal ? $penalesLocal : $penalesVisitante) : null,
            'penalesContra' => $tienePenales && $favor === $contra ? (int) ($esLocal ? $penalesVisitante : $penalesLocal) : null,
            'resultado' => $resultado,
        ];
    }

    /**
     * @param  Collection<int, FilaPartido>  $filas
     * @return Totales
     */
    private function totales(Collection $filas): array
    {
        $gf = (int) $filas->sum('golesFavor');
        $gc = (int) $filas->sum('golesContra');
        $cuenta = $filas->countBy('resultado');

        return [
            'pj' => $filas->count(),
            'g' => (int) $cuenta->get('G', 0),
            'e' => (int) $cuenta->get('E', 0),
            'p' => (int) $cuenta->get('P', 0),
            'gf' => $gf,
            'gc' => $gc,
            'dg' => $gf - $gc,
        ];
    }

    /**
     * @return list<array{grupo: ?string, puntos: int, pj: int, g: int, e: int, p: int, gf: int, gc: int, dg: int}>
     */
    private function tablaPosiciones(Equipo $equipo, ?int $idEdicion): array
    {
        return TablaPosicion::query()
            ->with('grupo')
            ->where('idEquipo', $equipo->id)
            ->where('idEdicion', $idEdicion)
            ->get()
            ->map(fn (TablaPosicion $t): array => [
                'grupo' => $t->grupo?->nombre,
                'puntos' => (int) $t->puntos,
                'pj' => (int) $t->jugado,
                'g' => (int) $t->ganado,
                'e' => (int) $t->empatado,
                'p' => (int) $t->perdido,
                'gf' => (int) $t->golesFavor,
                'gc' => (int) $t->golesContra,
                'dg' => (int) $t->diferenciaGoles,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{maximos: list<Goleador>, ranking: list<Goleador>}
     */
    private function goleadores(Equipo $equipo, ?int $idEdicion, ?int $idCategoria): array
    {
        $ordenados = TablaGoleador::query()
            ->with('jugador')
            ->where('idEquipo', $equipo->id)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('cantidadGoles', '>', 0)
            ->get()
            ->map(fn (TablaGoleador $g): array => [
                'nombre' => (string) $g->nombre_completo,
                'goles' => (int) $g->cantidadGoles,
            ])
            ->sort(fn (array $a, array $b): int => [$b['goles'], $a['nombre']] <=> [$a['goles'], $b['nombre']])
            ->values();

        $maximo = $ordenados->first()['goles'] ?? null;

        return [
            'maximos' => $ordenados->where('goles', $maximo)->values()->all(),
            'ranking' => $ordenados->take(self::TOP_RANKING)->values()->all(),
        ];
    }
}
