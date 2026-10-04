<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\ReemplazoEquipo;
use App\Models\TablaPosicion;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as ColeccionSimple;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Un equipo toma el lugar de otro a mitad de torneo, en la misma edición,
 * categoría y grupo.
 *
 * El reemplazante hereda la fila de la tabla de posiciones, el lugar en el
 * grupo y los partidos pendientes. Los partidos con actividad, la lista de
 * buena fe, las planillas y los premios quedan con el reemplazado.
 */
class ReemplazadorEquipo
{
    public function __construct(private readonly PartidoService $partidoService) {}

    /**
     * Exactamente un origen: equipo inscripto, equipo nuevo o equipo de otra edición.
     * Este último se inscribe en la edición y categoría dentro de la misma transacción.
     *
     * @param  array{nombre?: string|null, foto?: string|null}|null  $datosEquipoNuevo  nombre y nombre del archivo de escudo ya almacenado
     *
     * @throws DomainException cuando el reemplazo no cumple las reglas de negocio
     */
    public function reemplazar(
        int $idEdicion,
        int $idCategoria,
        int $idEquipoReemplazado,
        ?int $idEquipoExistente,
        ?array $datosEquipoNuevo,
        ?int $idEquipoOtraEdicion = null,
    ): ReemplazoEquipo {
        try {
            return DB::transaction(function () use ($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo, $idEquipoOtraEdicion): ReemplazoEquipo {
                return $this->ejecutar($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo, $idEquipoOtraEdicion);
            }, 3);
        } catch (DomainException $e) {
            throw $e;
        } catch (QueryException $e) {
            if ($this->esViolacionDeUnicidad($e)) {
                throw new DomainException('Este equipo ya fue reemplazado en la edición y no puede reemplazarse de nuevo.', 0, $e);
            }

            $this->registrarFallo($e, $idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente);

            throw $e;
        } catch (Throwable $e) {
            $this->registrarFallo($e, $idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente);

            throw $e;
        }
    }

    private function registrarFallo(Throwable $e, int $idEdicion, int $idCategoria, int $idEquipoReemplazado, ?int $idEquipoExistente): void
    {
        Log::error('No se pudo reemplazar el equipo', [
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idEquipoReemplazado' => $idEquipoReemplazado,
            'idEquipoExistente' => $idEquipoExistente,
            'exception' => $e,
        ]);
    }

    private function esViolacionDeUnicidad(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            && str_contains($e->getMessage(), 'reemplazos_equipos_reemplazado_unique');
    }

    /**
     * Equipos inscriptos en la edición y categoría que todavía no tienen grupo.
     *
     * @return Collection<int, Equipo>
     */
    public function equiposElegibles(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): Collection
    {
        $idsInscriptos = EquipoEdicion::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('idEquipo', '!=', $idEquipoReemplazado)
            ->pluck('idEquipo');

        $idsConGrupo = EquipoGrupo::query()
            ->whereIn('idGrupo', $this->gruposDeCategoria($idEdicion, $idCategoria)->pluck('id'))
            ->pluck('idEquipo');

        return Equipo::query()
            ->whereIn('id', $idsInscriptos)
            ->whereNotIn('id', $idsConGrupo)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Resumen de lo que implica el reemplazo, para mostrar antes de confirmar.
     *
     * @return array{grupo: Grupo|null, filaPosicion: TablaPosicion|null, partidosPendientes: int, finalesPendientes: int, resultadosBloqueados: int, yaReemplazado: bool}
     */
    public function vistaPrevia(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): array
    {
        $grupo = $this->grupoDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria);
        $partidos = $this->partidosDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)->get();
        $finales = $this->finalesDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)->get();

        $partidosPendientes = $partidos->filter(fn (Partido $partido): bool => $this->partidoService->partidoPendiente($partido))->count();
        $finalesPendientes = $finales->filter(fn (InstanciaFinal $final): bool => $this->partidoService->instanciaFinalPendiente($final))->count();

        return [
            'grupo' => $grupo,
            'filaPosicion' => $grupo
                ? TablaPosicion::query()->where('idGrupo', $grupo->id)->where('idEquipo', $idEquipoReemplazado)->first()
                : null,
            'partidosPendientes' => $partidosPendientes,
            'finalesPendientes' => $finalesPendientes,
            'resultadosBloqueados' => ($partidos->count() - $partidosPendientes) + ($finales->count() - $finalesPendientes),
            'yaReemplazado' => $this->partidoService->equipoFueReemplazado($idEquipoReemplazado, $idEdicion, $idCategoria),
        ];
    }

    private function ejecutar(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, ?int $idEquipoExistente, ?array $datosEquipoNuevo, ?int $idEquipoOtraEdicion): ReemplazoEquipo
    {
        $this->bloquearFilasDelReemplazado($idEdicion, $idCategoria, $idEquipoReemplazado);
        $this->validarReemplazado($idEdicion, $idCategoria, $idEquipoReemplazado);

        $grupo = $this->grupoDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria);
        if (! $grupo) {
            throw new DomainException('El equipo a reemplazar no pertenece a ningún grupo de esta categoría.');
        }

        $this->validarSinPartidosAMedioCargar($idEdicion, $idCategoria, $idEquipoReemplazado);

        $idEquipoReemplazante = $this->resolverReemplazante($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo, $idEquipoOtraEdicion);

        $this->heredarFilaDePosiciones($grupo, $idEquipoReemplazado, $idEquipoReemplazante);
        $this->intercambiarLugarEnGrupo($grupo, $idEquipoReemplazado, $idEquipoReemplazante);
        $this->reasignarPendientes($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoReemplazante);

        $this->partidoService->actualizarGolesContraEquipoEdicion($idEquipoReemplazante, $idEdicion, $grupo->id);
        $this->partidoService->actualizarGolesContraEquipoEdicion($idEquipoReemplazado, $idEdicion, $grupo->id);

        return ReemplazoEquipo::create([
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $grupo->id,
            'idEquipoReemplazado' => $idEquipoReemplazado,
            'idEquipoReemplazante' => $idEquipoReemplazante,
        ]);
    }

    private function validarReemplazado(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): void
    {
        $inscripto = EquipoEdicion::query()
            ->where('idEquipo', $idEquipoReemplazado)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->exists();

        if (! $inscripto) {
            throw new DomainException('El equipo a reemplazar no está inscripto en esta edición y categoría.');
        }

        $yaReemplazado = ReemplazoEquipo::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('idEquipoReemplazado', $idEquipoReemplazado)
            ->lockForUpdate()
            ->exists();

        if ($yaReemplazado) {
            throw new DomainException('Este equipo ya fue reemplazado en la edición y no puede reemplazarse de nuevo.');
        }
    }

    /**
     * Serializa reemplazos simultáneos del mismo equipo: toma los bloqueos antes de validar.
     */
    private function bloquearFilasDelReemplazado(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): void
    {
        $idsGrupos = $this->gruposDeCategoria($idEdicion, $idCategoria)->pluck('id');

        EquipoGrupo::query()
            ->whereIn('idGrupo', $idsGrupos)
            ->where('idEquipo', $idEquipoReemplazado)
            ->lockForUpdate()
            ->get();

        TablaPosicion::query()
            ->whereIn('idGrupo', $idsGrupos)
            ->where('idEquipo', $idEquipoReemplazado)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Los partidos o finales con planillas o goles pero sin resultado aplicado
     * quedarían trabados con el reemplazado: se pide terminarlos o limpiarlos antes.
     */
    private function validarSinPartidosAMedioCargar(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): void
    {
        $detalles = [];

        $partidos = $this->partidosDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)->with(['fecha', 'equipoLocal', 'equipoVisitante'])->get();
        foreach ($partidos as $partido) {
            if (! $this->partidoService->partidoPendiente($partido) && ! $this->partidoService->resultadoAplicado($partido)) {
                $detalles[] = ($partido->fecha?->nombre ?? 'Fecha sin nombre').' '.$this->descripcionEnfrentamiento($partido);
            }
        }

        $finales = $this->finalesDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)->with(['equipoLocal', 'equipoVisitante'])->get();
        foreach ($finales as $final) {
            if (! $this->partidoService->instanciaFinalPendiente($final) && ! $this->partidoService->resultadoAplicado($final)) {
                $detalles[] = 'Instancia final '.$this->descripcionEnfrentamiento($final);
            }
        }

        if ($detalles !== []) {
            throw new DomainException('Tiene partidos a medio cargar: '.implode('; ', $detalles).'. Terminá de cargarlos o limpialos antes de reemplazar al equipo.');
        }
    }

    private function descripcionEnfrentamiento(Partido|InstanciaFinal $partido): string
    {
        return ($partido->equipoLocal?->nombre ?? '?').' vs '.($partido->equipoVisitante?->nombre ?? '?');
    }

    /**
     * Partidos y finales a medio cargar del equipo, para mostrar el motivo antes del formulario.
     */
    public function motivoImposibilidad(int $idEdicion, int $idCategoria, int $idEquipoReemplazado): ?string
    {
        try {
            if ($this->partidoService->equipoFueReemplazado($idEquipoReemplazado, $idEdicion, $idCategoria)) {
                return 'Este equipo ya fue reemplazado en la edición y no puede reemplazarse de nuevo.';
            }

            if (! $this->grupoDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)) {
                return 'El equipo a reemplazar no pertenece a ningún grupo de esta categoría.';
            }

            $this->validarSinPartidosAMedioCargar($idEdicion, $idCategoria, $idEquipoReemplazado);
        } catch (DomainException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Devuelve el id del reemplazante, creándolo si corresponde.
     */
    private function resolverReemplazante(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, ?int $idEquipoExistente, ?array $datosEquipoNuevo, ?int $idEquipoOtraEdicion = null): int
    {
        if ($idEquipoOtraEdicion !== null) {
            if ($idEquipoExistente !== null || $datosEquipoNuevo !== null) {
                throw new DomainException('Elegí un solo origen para el reemplazante.');
            }

            $this->validarReemplazanteDeOtraEdicion($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoOtraEdicion);

            EquipoEdicion::create([
                'idEquipo' => $idEquipoOtraEdicion,
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'golesContra' => 0,
            ]);

            return $idEquipoOtraEdicion;
        }

        if ($idEquipoExistente !== null && $datosEquipoNuevo !== null) {
            throw new DomainException('Indicá un equipo existente o los datos de un equipo nuevo, pero no ambos.');
        }

        if ($idEquipoExistente === null && $datosEquipoNuevo === null) {
            throw new DomainException('Indicá un equipo existente o los datos de un equipo nuevo.');
        }

        if ($idEquipoExistente !== null) {
            $this->validarReemplazanteExistente($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente);

            return $idEquipoExistente;
        }

        $nombre = trim((string) ($datosEquipoNuevo['nombre'] ?? ''));
        if ($nombre === '') {
            throw new DomainException('El equipo nuevo necesita un nombre.');
        }

        $equipo = Equipo::create([
            'nombre' => $nombre,
            'foto' => $datosEquipoNuevo['foto'] ?? null,
            'idCategoria' => $idCategoria,
            'idEdicion' => $idEdicion,
        ]);

        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'golesContra' => 0,
        ]);

        return $equipo->id;
    }

    private function validarReemplazanteDeOtraEdicion(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, int $idEquipo): void
    {
        if ($idEquipo === $idEquipoReemplazado) {
            throw new DomainException('El reemplazante no puede ser el mismo equipo que se reemplaza.');
        }

        if (! Equipo::query()->whereKey($idEquipo)->exists()) {
            throw new DomainException('El equipo elegido no existe.');
        }

        if (EquipoEdicion::query()->where('idEquipo', $idEquipo)->where('idEdicion', $idEdicion)->exists()) {
            throw new DomainException('Ese equipo ya está inscripto en esta edición. Usá la opción «Equipo ya inscripto» para elegirlo.');
        }

        $origenes = EquipoEdicion::query()
            ->with('categoria')
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', '!=', $idEdicion)
            ->get();

        if ($origenes->isEmpty()) {
            throw new DomainException('El equipo elegido no pertenece a otra edición.');
        }

        $nombreCategoria = Categoria::query()->findOrFail($idCategoria)->nombreCategoria;
        $normalizado = $this->normalizarNombre($nombreCategoria);

        $coincide = $origenes->contains(fn (EquipoEdicion $origen): bool => $origen->categoria !== null
            && $this->normalizarNombre($origen->categoria->nombreCategoria) === $normalizado);

        if (! $coincide) {
            throw new DomainException("La categoría del equipo en su edición de origen no coincide con «{$nombreCategoria}».");
        }
    }

    /**
     * Candidatos para traer de otra edición: equipos que jugaron en una categoría de igual
     * nombre normalizado y todavía no están inscriptos en esta edición, de la edición más reciente a la más antigua.
     *
     * @return ColeccionSimple<int, array{id: int, nombre: string, foto: string|null, edicion: string, categoria: string}>
     */
    public function equiposDeOtrasEdiciones(int $idEdicion, int $idCategoria, ?string $busqueda, int $limite = 20): ColeccionSimple
    {
        $categoria = Categoria::query()->find($idCategoria);

        if ($limite < 1 || ! $categoria) {
            return collect();
        }

        $normalizado = $this->normalizarNombre($categoria->nombreCategoria);
        $idsCategorias = Categoria::query()
            ->where('idEdicion', '!=', $idEdicion)
            ->get(['id', 'nombreCategoria'])
            ->filter(fn (Categoria $otra): bool => $this->normalizarNombre($otra->nombreCategoria) === $normalizado)
            ->pluck('id');

        $consulta = EquipoEdicion::query()
            ->with(['equipo', 'edicion', 'categoria'])
            ->where('idEdicion', '!=', $idEdicion)
            ->whereIn('idCategoria', $idsCategorias)
            ->whereNotIn('idEquipo', EquipoEdicion::query()->where('idEdicion', $idEdicion)->select('idEquipo'))
            ->whereHas('equipo', function (Builder $equipos) use ($busqueda): void {
                $busqueda = trim((string) $busqueda);
                if ($busqueda !== '') {
                    $equipos->where('nombre', 'like', '%'.addcslashes($busqueda, '\\%_').'%');
                }
            })
            ->orderByDesc('idEdicion')
            ->orderByDesc('id')
            ->limit($limite * 4);

        return $consulta->get()
            ->unique('idEquipo')
            ->take($limite)
            ->map(fn (EquipoEdicion $inscripcion): array => [
                'id' => (int) $inscripcion->idEquipo,
                'nombre' => $inscripcion->equipo->nombre,
                'foto' => $inscripcion->equipo->foto,
                'edicion' => (string) $inscripcion->edicion?->nombre,
                'categoria' => (string) $inscripcion->categoria?->nombreCategoria,
            ])
            ->values();
    }

    /**
     * Misma normalización que ControladorEquiposEdiciones::store: espacios, mayúsculas y acentos.
     */
    private function normalizarNombre(string $texto): string
    {
        $texto = preg_replace('/\s+/', ' ', trim($texto));

        return strtr(mb_strtolower($texto, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function validarReemplazanteExistente(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, int $idEquipoExistente): void
    {
        if ($idEquipoExistente === $idEquipoReemplazado) {
            throw new DomainException('El reemplazante no puede ser el mismo equipo que se reemplaza.');
        }

        $inscripto = EquipoEdicion::query()
            ->where('idEquipo', $idEquipoExistente)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->exists();

        if (! $inscripto) {
            throw new DomainException('El equipo elegido no está inscripto en esta edición y categoría.');
        }

        if ($this->grupoDelEquipo($idEquipoExistente, $idEdicion, $idCategoria)) {
            throw new DomainException('El equipo elegido ya pertenece a un grupo de esta categoría.');
        }
    }

    private function heredarFilaDePosiciones(Grupo $grupo, int $idEquipoReemplazado, int $idEquipoReemplazante): void
    {
        $filaExistente = TablaPosicion::query()
            ->where('idGrupo', $grupo->id)
            ->where('idEquipo', $idEquipoReemplazante)
            ->lockForUpdate()
            ->exists();

        if ($filaExistente) {
            throw new DomainException('El equipo elegido ya tiene una fila en la tabla de posiciones de este grupo.');
        }

        TablaPosicion::query()
            ->where('idGrupo', $grupo->id)
            ->where('idEquipo', $idEquipoReemplazado)
            ->update(['idEquipo' => $idEquipoReemplazante]);
    }

    private function intercambiarLugarEnGrupo(Grupo $grupo, int $idEquipoReemplazado, int $idEquipoReemplazante): void
    {
        EquipoGrupo::query()
            ->where('idGrupo', $grupo->id)
            ->where('idEquipo', $idEquipoReemplazado)
            ->delete();

        $yaEstaEnElGrupo = EquipoGrupo::query()
            ->where('idGrupo', $grupo->id)
            ->where('idEquipo', $idEquipoReemplazante)
            ->exists();

        if (! $yaEstaEnElGrupo) {
            EquipoGrupo::create(['idEquipo' => $idEquipoReemplazante, 'idGrupo' => $grupo->id]);
        }
    }

    private function reasignarPendientes(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, int $idEquipoReemplazante): void
    {
        $partidos = $this->partidosDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)
            ->lockForUpdate()
            ->get()
            ->filter(fn (Partido $partido): bool => $this->partidoService->partidoPendiente($partido));

        $finales = $this->finalesDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria)
            ->lockForUpdate()
            ->get()
            ->filter(fn (InstanciaFinal $final): bool => $this->partidoService->instanciaFinalPendiente($final));

        foreach ($partidos->concat($finales) as $partido) {
            $contraSiMismo = in_array($idEquipoReemplazante, [(int) $partido->idEquipoLocal, (int) $partido->idEquipoVisitante], true);

            if ($contraSiMismo) {
                throw new DomainException('El reemplazante tiene un partido pendiente contra el equipo reemplazado y quedaría enfrentado a sí mismo.');
            }
        }

        foreach ($partidos->concat($finales) as $partido) {
            $campo = (int) $partido->idEquipoLocal === $idEquipoReemplazado ? 'idEquipoLocal' : 'idEquipoVisitante';
            $partido->update([$campo => $idEquipoReemplazante]);
        }
    }

    /**
     * @return Builder<Partido>
     */
    private function partidosDelEquipo(int $idEquipo, int $idEdicion, int $idCategoria)
    {
        return Partido::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where(function ($consulta) use ($idEquipo): void {
                $consulta->where('idEquipoLocal', $idEquipo)->orWhere('idEquipoVisitante', $idEquipo);
            });
    }

    /**
     * @return Builder<InstanciaFinal>
     */
    private function finalesDelEquipo(int $idEquipo, int $idEdicion, int $idCategoria)
    {
        return InstanciaFinal::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where(function ($consulta) use ($idEquipo): void {
                $consulta->where('idEquipoLocal', $idEquipo)->orWhere('idEquipoVisitante', $idEquipo);
            });
    }

    /**
     * @return Collection<int, Grupo>
     */
    private function gruposDeCategoria(int $idEdicion, int $idCategoria): Collection
    {
        return Grupo::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->get(['id']);
    }

    private function grupoDelEquipo(int $idEquipo, int $idEdicion, int $idCategoria): ?Grupo
    {
        return Grupo::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->whereIn('id', EquipoGrupo::query()->where('idEquipo', $idEquipo)->select('idGrupo'))
            ->first();
    }
}
