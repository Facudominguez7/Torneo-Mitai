<?php

namespace App\Services\Panel;

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\InstanciaFinal;
use App\Models\Subcampeon;
use Illuminate\Support\Collection;

/**
 * Arma el Podio (campeones y subcampeones fusionados) de una edición: por
 * categoría y copa, muestra lo ya guardado y, cuando la final ya se jugó y
 * todavía no hay nada cargado para esa copa, calcula una sugerencia a partir
 * del resultado. Nunca guarda nada — solo calcula lo que
 * ControladorPodio::confirmar puede confirmar, siempre recalculando server-side.
 *
 * Solo se consideran instancias finales con `idCopa` cargado: una copa es
 * obligatoria para Campeon/Subcampeon (StoreRequest la exige), así que una
 * final "Sin copa" no puede convertirse en sugerencia.
 */
class SugerenciaPodio
{
    /**
     * @return array{categorias: Collection<int, array<string, mixed>>, tieneContenido: bool}
     */
    public function obtenerPodio(int $idEdicion): array
    {
        $categorias = Categoria::where('idEdicion', $idEdicion)
            ->orderByDesc('nombreCategoria')
            ->get()
            ->map(fn (Categoria $categoria) => $this->construirCategoria($idEdicion, $categoria))
            ->filter(fn (array $categoria) => $categoria['copas']->isNotEmpty())
            ->values();

        return [
            'categorias' => $categorias,
            'tieneContenido' => $categorias->isNotEmpty(),
        ];
    }

    /**
     * Recalcula, server-side, la sugerencia de una copa puntual. Se usa desde
     * ControladorPodio::confirmar para no confiar nunca en equipos que
     * mande el cliente: si no hay sugerencia vigente, devuelve null.
     *
     * @return array<string, mixed>|null
     */
    public function sugerenciaParaCopa(int $idEdicion, int $idCategoria, int $idCopa): ?array
    {
        $categoria = Categoria::find($idCategoria);

        if (! $categoria || (int) $categoria->idEdicion !== $idEdicion) {
            return null;
        }

        $copa = $this->construirCategoria($idEdicion, $categoria)['copas']
            ->firstWhere('idCopa', $idCopa);

        return $copa['sugerencia'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function construirCategoria(int $idEdicion, Categoria $categoria): array
    {
        $idsCopasConInstancias = InstanciaFinal::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $categoria->id)
            ->whereNotNull('idCopa')
            ->distinct()
            ->pluck('idCopa')
            ->map(fn ($id) => (int) $id);

        $campeonesPorCopa = Campeon::with('equipo:id,nombre')
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $categoria->id)
            ->get()
            ->groupBy(fn (Campeon $campeon) => (int) $campeon->idCopa);

        $subcampeonesPorCopa = Subcampeon::with('equipo:id,nombre')
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $categoria->id)
            ->get()
            ->groupBy(fn (Subcampeon $subcampeon) => (int) $subcampeon->idCopa);

        $idsCopas = $idsCopasConInstancias
            ->merge($campeonesPorCopa->keys())
            ->merge($subcampeonesPorCopa->keys())
            ->unique()
            ->values();

        $copas = Copa::whereIn('id', $idsCopas)
            ->orderBy('secuencia')
            ->orderBy('nombre')
            ->get()
            ->map(function (Copa $copa) use ($idEdicion, $categoria, $campeonesPorCopa, $subcampeonesPorCopa) {
                $campeonesCopa = $campeonesPorCopa->get($copa->id, collect());
                $subcampeonesCopa = $subcampeonesPorCopa->get($copa->id, collect());
                $definido = $campeonesCopa->isNotEmpty() && $subcampeonesCopa->isNotEmpty();

                return [
                    'idCopa' => $copa->id,
                    'nombreCopa' => $copa->nombre,
                    'campeones' => $campeonesCopa->values(),
                    'subcampeones' => $subcampeonesCopa->values(),
                    'definido' => $definido,
                    'sugerencia' => $this->calcularSugerencia(
                        $idEdicion,
                        $categoria->id,
                        $copa->id,
                        $campeonesCopa,
                        $subcampeonesCopa,
                    ),
                ];
            })
            ->values();

        return [
            'idCategoria' => $categoria->id,
            'nombreCategoria' => $categoria->nombreCategoria,
            'copas' => $copas,
            'estado' => $this->resolverEstado($copas),
        ];
    }

    /**
     * @param  Collection<int, Campeon>  $campeonesCopa
     * @param  Collection<int, Subcampeon>  $subcampeonesCopa
     * @return array<string, mixed>|null
     */
    private function calcularSugerencia(
        int $idEdicion,
        int $idCategoria,
        int $idCopa,
        Collection $campeonesCopa,
        Collection $subcampeonesCopa,
    ): ?array {
        // Nunca se sugiere encima de algo ya cargado, aunque sea parcial
        // (solo campeón o solo subcampeón).
        if ($campeonesCopa->isNotEmpty() || $subcampeonesCopa->isNotEmpty()) {
            return null;
        }

        $legs = $this->resolverFinal($idEdicion, $idCategoria, $idCopa);

        if (! $legs || $legs->contains(fn (InstanciaFinal $leg) => $leg->golesEquipoLocal === null || $leg->golesEquipoVisitante === null)) {
            // Si hay ida y vuelta, alcanza con que una sola ida no esté
            // jugada para considerar que la final completa no se jugó.
            return null;
        }

        $ganador = $this->resolverGanador($legs);

        if ($ganador === null) {
            return null;
        }

        $ultima = $legs->last();
        $equipoCampeon = $ganador === 'local' ? $ultima->equipoLocal : $ultima->equipoVisitante;
        $equipoSubcampeon = $ganador === 'local' ? $ultima->equipoVisitante : $ultima->equipoLocal;

        if (! $equipoCampeon || ! $equipoSubcampeon) {
            return null;
        }

        return [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idCopa' => $idCopa,
            'idInstanciaFinal' => $ultima->id,
            'idEquipoCampeon' => $equipoCampeon->id,
            'idEquipoSubcampeon' => $equipoSubcampeon->id,
            'nombreCampeon' => $equipoCampeon->nombre,
            'nombreSubcampeon' => $equipoSubcampeon->nombre,
        ];
    }

    /**
     * "La final" de una copa: todas las instancias de esa edición/categoría/
     * copa cuya fase, recortada y sin importar mayúsculas, empieza con
     * "Final" (excluye "Semifinal" y "Cuartos de final", que no empiezan
     * así), ordenadas de más antigua a más reciente (a igual horario, la de
     * mayor id va última). Con ida y vuelta puede haber más de una, siempre
     * que sea el mismo par de equipos (con o sin localía invertida). Si hay
     * varias finales entre pares distintos, la final es ambigua y no se
     * sugiere nada.
     *
     * @return Collection<int, InstanciaFinal>|null
     */
    private function resolverFinal(int $idEdicion, int $idCategoria, int $idCopa): ?Collection
    {
        $finales = InstanciaFinal::with(['equipoLocal:id,nombre', 'equipoVisitante:id,nombre', 'fase:id,nombre'])
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('idCopa', $idCopa)
            ->get()
            ->filter(fn (InstanciaFinal $instancia) => $this->esFinal($instancia->fase?->nombre));

        if ($finales->isEmpty()) {
            return null;
        }

        $paresDeEquipos = $finales
            ->map(fn (InstanciaFinal $instancia) => collect([(int) $instancia->idEquipoLocal, (int) $instancia->idEquipoVisitante])->sort()->implode('-'))
            ->unique();

        if ($paresDeEquipos->count() > 1) {
            return null;
        }

        return $finales
            ->sortBy(fn (InstanciaFinal $instancia) => sprintf('%s|%010d', (string) $instancia->horario, $instancia->id))
            ->values();
    }

    private function esFinal(?string $nombreFase): bool
    {
        if ($nombreFase === null || trim($nombreFase) === '') {
            return false;
        }

        return (bool) preg_match('/^final/i', trim($nombreFase));
    }

    /**
     * Ganador de la final, en términos de local/visitante de la última ida:
     * `resultadoGlobal` ("a-b", local-visitante) de la última ida si está
     * cargado y es parseable; si no, los goles — de esa única ida si es a
     * partido único, o el agregado de todas las idas (goles a favor menos en
     * contra, sumados, invirtiendo cuando la localía se invierte entre
     * idas) si hay ida y vuelta. Empatado eso, decide los penales de la
     * última ida. Sin manera de determinarlo, no hay ganador (y por lo tanto
     * no hay sugerencia): el admin siempre confirma a mano.
     *
     * @param  Collection<int, InstanciaFinal>  $legs  ordenadas de más
     *                                                 antigua a más reciente
     */
    private function resolverGanador(Collection $legs): ?string
    {
        $ultima = $legs->last();
        $porGlobal = $this->compararPorGlobal($ultima->resultadoGlobal);

        if ($porGlobal !== null) {
            if ($porGlobal === 0) {
                return $this->desempatarPorPenales($ultima);
            }

            return $porGlobal > 0 ? 'local' : 'visitante';
        }

        $diferencia = $legs->count() === 1
            ? (int) $ultima->golesEquipoLocal - (int) $ultima->golesEquipoVisitante
            : $this->diferenciaAgregada($legs, $ultima);

        if ($diferencia !== 0) {
            return $diferencia > 0 ? 'local' : 'visitante';
        }

        return $this->desempatarPorPenales($ultima);
    }

    /**
     * Diferencia de goles agregada de todas las idas, en la perspectiva
     * local/visitante de la última ida (la localía puede invertirse entre
     * idas, así que cada ida se suma desde el punto de vista de quién es el
     * mismo equipo que el local de la última).
     *
     * @param  Collection<int, InstanciaFinal>  $legs
     */
    private function diferenciaAgregada(Collection $legs, InstanciaFinal $ultima): int
    {
        $idLocalUltima = (int) $ultima->idEquipoLocal;

        return (int) $legs->sum(function (InstanciaFinal $leg) use ($idLocalUltima) {
            $golesLocal = (int) $leg->golesEquipoLocal;
            $golesVisitante = (int) $leg->golesEquipoVisitante;

            return (int) $leg->idEquipoLocal === $idLocalUltima
                ? $golesLocal - $golesVisitante
                : $golesVisitante - $golesLocal;
        });
    }

    /**
     * Compara el resultado global "a-b" (con espacios opcionales). Si no
     * está cargado o no tiene ese formato, devuelve null para que
     * resolverGanador recurra a los goles.
     */
    private function compararPorGlobal(?string $resultadoGlobal): ?int
    {
        if ($resultadoGlobal === null) {
            return null;
        }

        if (! preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $resultadoGlobal, $coincidencias)) {
            return null;
        }

        return ((int) $coincidencias[1]) <=> ((int) $coincidencias[2]);
    }

    private function desempatarPorPenales(InstanciaFinal $final): ?string
    {
        if ($final->penalesEquipoLocal === null || $final->penalesEquipoVisitante === null) {
            return null;
        }

        if ((int) $final->penalesEquipoLocal === (int) $final->penalesEquipoVisitante) {
            return null;
        }

        return (int) $final->penalesEquipoLocal > (int) $final->penalesEquipoVisitante ? 'local' : 'visitante';
    }

    /**
     * Estado de la categoría para la tarjeta del Podio:
     * - "Sin instancias": no hay ninguna copa para listar (ni instancias, ni nada guardado).
     * - "Definido": todas las copas listadas tienen campeón y subcampeón guardados.
     * - "Final jugada": alguna copa tiene una sugerencia pendiente de confirmar.
     * - "En juego": catch-all para el resto (instancias sin definir, o datos
     *   parciales guardados sin instancias que los respalden) — no hay nada
     *   más para hacer todavía, pero la categoría ya tiene actividad.
     *
     * @param  Collection<int, array<string, mixed>>  $copas
     * @return array{tipo: string, label: string}
     */
    private function resolverEstado(Collection $copas): array
    {
        if ($copas->isEmpty()) {
            return ['tipo' => 'sin_instancias', 'label' => 'Sin instancias'];
        }

        if ($copas->every(fn (array $copa) => $copa['definido'])) {
            return ['tipo' => 'definido', 'label' => 'Definido'];
        }

        if ($copas->contains(fn (array $copa) => $copa['sugerencia'] !== null)) {
            return ['tipo' => 'final_jugada', 'label' => 'Final jugada'];
        }

        return ['tipo' => 'en_juego', 'label' => 'En juego'];
    }
}
