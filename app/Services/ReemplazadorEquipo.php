<?php

namespace App\Services;

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
    ): ReemplazoEquipo {
        try {
            return DB::transaction(function () use ($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo): ReemplazoEquipo {
                return $this->ejecutar($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo);
            }, 3);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('No se pudo reemplazar el equipo', [
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idEquipoReemplazado' => $idEquipoReemplazado,
                'idEquipoExistente' => $idEquipoExistente,
                'exception' => $e,
            ]);

            throw $e;
        }
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

    private function ejecutar(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, ?int $idEquipoExistente, ?array $datosEquipoNuevo): ReemplazoEquipo
    {
        $this->validarReemplazado($idEdicion, $idCategoria, $idEquipoReemplazado);

        $grupo = $this->grupoDelEquipo($idEquipoReemplazado, $idEdicion, $idCategoria);
        if (! $grupo) {
            throw new DomainException('El equipo a reemplazar no pertenece a ningún grupo de esta categoría.');
        }

        $idEquipoReemplazante = $this->resolverReemplazante($idEdicion, $idCategoria, $idEquipoReemplazado, $idEquipoExistente, $datosEquipoNuevo);

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

        if ($this->partidoService->equipoFueReemplazado($idEquipoReemplazado, $idEdicion, $idCategoria)) {
            throw new DomainException('Este equipo ya fue reemplazado en la edición y no puede reemplazarse de nuevo.');
        }
    }

    /**
     * Devuelve el id del reemplazante, creándolo si corresponde.
     */
    private function resolverReemplazante(int $idEdicion, int $idCategoria, int $idEquipoReemplazado, ?int $idEquipoExistente, ?array $datosEquipoNuevo): int
    {
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
