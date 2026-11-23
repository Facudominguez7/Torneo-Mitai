<?php

namespace App\Services;

use App\Models\AusenciaCobroPlanilla;
use App\Models\Campeon;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\Subcampeon;
use App\Models\User;
use App\Services\Cobros\AnulacionCargosService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Corrige un partido cargado por error como instancia final: lo convierte en
 * partido de una fecha conservando planillas, reportes arbitrales, cargos y
 * pagos. El historial de equipos ya contiene el resultado y no se vuelve a sumar.
 */
class MoverInstanciaFinalAFechaService
{
    /** Estas ediciones guardan sus posiciones bajo idEdicion 0 y calculan los goles en contra aparte. */
    private const EDICIONES_LEGACY = [1, 2, 3];

    public function __construct(
        private readonly PartidoService $partidoService,
        private readonly AnulacionCargosService $anulacionCargosService,
    ) {}

    /**
     * @throws DomainException cuando el partido no se puede mover sin perder o duplicar datos
     */
    public function mover(User $actor, InstanciaFinal $instanciaFinal, int $idFecha, bool $confirmarInterzonal = false): Partido
    {
        return DB::transaction(function () use ($actor, $instanciaFinal, $idFecha, $confirmarInterzonal): Partido {
            $actorVigente = $this->anulacionCargosService->bloquearAntesDelPartido($actor);
            $final = InstanciaFinal::query()->whereKey($instanciaFinal->id)->lockForUpdate()->firstOrFail();
            $cargos = $this->bloquearCargos($final);

            $this->validarEdicion($final);
            $this->partidoService->asegurarResultadoEditable($final);
            $this->validarResultado($final);
            $this->validarCancha($final);
            $this->validarFecha($final, $idFecha);
            $this->validarSinDuplicado($final, $idFecha);
            $this->validarPodio($final);
            $this->validarAusencias($final, $cargos);
            $idGrupo = $this->resolverGrupo($final, $confirmarInterzonal);

            $conResultado = $final->golesEquipoLocal !== null;
            $partido = new Partido;
            $partido->fill([
                'idFechas' => $idFecha,
                'idEquipoLocal' => $final->idEquipoLocal,
                'idEquipoVisitante' => $final->idEquipoVisitante,
                'idGrupo' => $idGrupo,
                'idEdicion' => $final->idEdicion,
                'idCategoria' => $final->idCategoria,
                'golesEquipoLocal' => $final->golesEquipoLocal,
                'golesEquipoVisitante' => $final->golesEquipoVisitante,
                'horario_datetime' => $final->horario,
                'cancha' => (int) $final->cancha,
                'jugado' => $conResultado ? 1 : 0,
                'es_interzonal' => $idGrupo === null,
                'goles_confirmados_at' => $final->goles_confirmados_at,
                'public_token' => $final->public_token,
            ]);

            if (! $partido->save()) {
                throw new RuntimeException('No se pudo crear el partido de la fecha.');
            }

            $this->repuntarPlanillas($final, $partido);
            $this->repuntarCargos($cargos, $partido);
            $this->registrarHistorial($actorVigente, $final, $partido, $cargos);

            $final->delete();

            if ($conResultado) {
                $this->partidoService->aplicarResultadoEnPosiciones($partido, (int) $partido->golesEquipoLocal, (int) $partido->golesEquipoVisitante);
            }
            $this->partidoService->recalcularGolesContraDePartido($partido);

            return $partido->refresh();
        }, 3);
    }

    /**
     * @return Collection<int, CargoPlanilla>
     */
    private function bloquearCargos(InstanciaFinal $final): Collection
    {
        $consulta = fn () => CargoPlanilla::query()
            ->where('parent_type', InstanciaFinal::class)
            ->where('parent_id', $final->id)
            ->orderBy('id');

        foreach ($consulta()->get(['idEquipo', 'idEdicion'])->unique(fn (CargoPlanilla $cargo): string => $cargo->idEquipo.'-'.$cargo->idEdicion) as $cargo) {
            CuentaCobroPlanilla::query()->where('idEquipo', $cargo->idEquipo)->where('idEdicion', $cargo->idEdicion)->lockForUpdate()->first();
        }

        return $consulta()->lockForUpdate()->get();
    }

    private function validarEdicion(InstanciaFinal $final): void
    {
        if (in_array((int) $final->idEdicion, self::EDICIONES_LEGACY, true)) {
            throw new DomainException('No se puede mover: esta edición histórica calcula sus estadísticas de otra forma.');
        }
    }

    /**
     * Incluye las columnas legacy, que solo existen en algunas bases.
     */
    public function tieneResultadoGlobal(InstanciaFinal $final): bool
    {
        return filled($final->getAttribute('resultadoGlobal'))
            || filled($final->getAttribute('resultadoGlobalLocal'))
            || filled($final->getAttribute('resultadoGlobalVisitante'));
    }

    private function validarResultado(InstanciaFinal $final): void
    {
        if ($final->penalesEquipoLocal !== null || $final->penalesEquipoVisitante !== null || $this->tieneResultadoGlobal($final)) {
            throw new DomainException('No se puede mover: el partido tiene penales o resultado global, y una fecha común no los guarda. Quitalos desde Editar resultado.');
        }

        if (($final->golesEquipoLocal === null) !== ($final->golesEquipoVisitante === null)) {
            throw new DomainException('No se puede mover: el partido tiene un resultado incompleto.');
        }
    }

    private function validarCancha(InstanciaFinal $final): void
    {
        if (! ctype_digit((string) $final->cancha)) {
            throw new DomainException('No se puede mover: la cancha debe ser un número. Corregila desde Editar.');
        }
    }

    private function validarFecha(InstanciaFinal $final, int $idFecha): void
    {
        $fecha = Fecha::query()->whereKey($idFecha)->lockForUpdate()->first();
        $categoriaValida = $fecha?->idCategoria === null || (int) $fecha->idCategoria === (int) $final->idCategoria;

        if ($fecha === null || (int) $fecha->idEdicion !== (int) $final->idEdicion || ! $categoriaValida) {
            throw new DomainException('La fecha elegida no pertenece a la edición y categoría del partido.');
        }
    }

    private function validarSinDuplicado(InstanciaFinal $final, int $idFecha): void
    {
        $existe = Partido::query()
            ->where('idFechas', $idFecha)
            ->where(function ($consulta) use ($final): void {
                $consulta->where(fn ($cruce) => $cruce->where('idEquipoLocal', $final->idEquipoLocal)->where('idEquipoVisitante', $final->idEquipoVisitante))
                    ->orWhere(fn ($cruce) => $cruce->where('idEquipoLocal', $final->idEquipoVisitante)->where('idEquipoVisitante', $final->idEquipoLocal));
            })
            ->lockForUpdate()
            ->exists();

        if ($existe) {
            throw new DomainException('La fecha elegida ya tiene un partido entre estos equipos. Revisalo antes de mover este.');
        }
    }

    private function validarPodio(InstanciaFinal $final): void
    {
        if ($final->idCopa === null) {
            return;
        }

        foreach ([Campeon::class, Subcampeon::class] as $modelo) {
            $confirmado = $modelo::query()
                ->where('idCopa', $final->idCopa)
                ->where('idCategoria', $final->idCategoria)
                ->where('idEdicion', $final->idEdicion)
                ->whereIn('idEquipo', [$final->idEquipoLocal, $final->idEquipoVisitante])
                ->exists();

            if ($confirmado) {
                throw new DomainException('No se puede mover: uno de los equipos figura en el podio de esta copa. Corregí el podio primero.');
            }
        }
    }

    /**
     * @param  Collection<int, CargoPlanilla>  $cargos
     */
    private function validarAusencias(InstanciaFinal $final, Collection $cargos): void
    {
        if ($final->golesEquipoLocal !== null || $cargos->isEmpty()) {
            return;
        }

        if (AusenciaCobroPlanilla::query()->vigentes()->whereIn('cargo_id', $cargos->modelKeys())->lockForUpdate()->exists()) {
            throw new DomainException('No se puede mover: hay un equipo marcado como no vino y el partido no tiene resultado. Cargá el resultado primero.');
        }
    }

    private function resolverGrupo(InstanciaFinal $final, bool $confirmarInterzonal): ?int
    {
        $grupoLocal = $this->partidoService->obtenerGrupoEquipo($final->idEquipoLocal, $final->idCategoria, $final->idEdicion);
        $grupoVisitante = $this->partidoService->obtenerGrupoEquipo($final->idEquipoVisitante, $final->idCategoria, $final->idEdicion);

        if ($grupoLocal === null || $grupoVisitante === null) {
            throw new DomainException('No se puede mover: los dos equipos deben tener grupo en esta categoría.');
        }

        if ((int) $grupoLocal->id === (int) $grupoVisitante->id) {
            return (int) $grupoLocal->id;
        }

        if (! $confirmarInterzonal) {
            throw new DomainException('Los equipos son de grupos distintos: confirmá que es un partido interzonal.');
        }

        return null;
    }

    private function repuntarPlanillas(InstanciaFinal $final, Partido $partido): void
    {
        foreach ([PlanillaJugador::class, GolArbitroReportado::class] as $modelo) {
            $modelo::query()
                ->where('partido_type', InstanciaFinal::class)
                ->where('partido_id', $final->id)
                ->toBase()
                ->update(['partido_type' => Partido::class, 'partido_id' => $partido->id]);
        }
    }

    /**
     * Los cargos conservan su id para mantener pagos, exenciones y ausencias.
     * Se actualizan por consulta porque el modelo los protege de ediciones comunes.
     *
     * @param  Collection<int, CargoPlanilla>  $cargos
     */
    private function repuntarCargos(Collection $cargos, Partido $partido): void
    {
        foreach ($cargos as $cargo) {
            $snapshot = $cargo->snapshot;
            $snapshot['fecha_partido'] = $partido->horario_datetime;
            $snapshot['partido'] = [
                'id' => (int) $partido->id,
                'public_token' => $partido->public_token,
                'es_interzonal' => (bool) $partido->es_interzonal,
                'id_fecha' => (int) $partido->idFechas,
            ];

            CargoPlanilla::query()->whereKey($cargo->id)->toBase()->update([
                'tipo' => CargoPlanilla::TIPO_PARTIDO,
                'parent_type' => Partido::class,
                'parent_id' => $partido->id,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @param  Collection<int, CargoPlanilla>  $cargos
     */
    private function registrarHistorial(User $actor, InstanciaFinal $final, Partido $partido, Collection $cargos): void
    {
        $evento = new HistorialCobroPlanilla([
            'accion' => 'mover_final_a_fecha',
            'usuario_id' => (int) $actor->id,
            'actor_nombre' => $actor->name,
            'valores_antes' => [
                'parent' => ['tipo' => InstanciaFinal::class, 'id' => (int) $final->id],
                'instancia_final' => $final->only(['idCopa', 'idFase', 'horario', 'cancha', 'golesEquipoLocal', 'golesEquipoVisitante']),
                'snapshots' => $cargos->mapWithKeys(fn (CargoPlanilla $cargo): array => [$cargo->id => $cargo->snapshot])->all(),
            ],
            'valores_despues' => [
                'parent' => ['tipo' => Partido::class, 'id' => (int) $partido->id],
                'id_fecha' => (int) $partido->idFechas,
                'id_grupo' => $partido->idGrupo === null ? null : (int) $partido->idGrupo,
                'cargos' => $cargos->modelKeys(),
            ],
        ]);

        if (! $evento->save()) {
            throw new RuntimeException('No se pudo registrar el historial del movimiento.');
        }
    }
}
