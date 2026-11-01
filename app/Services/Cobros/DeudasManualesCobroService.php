<?php

namespace App\Services\Cobros;

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Equipo;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Deudas de planilla cargadas a mano: un cargo por equipo en un partido ya
 * jugado que no lo tiene (por ejemplo, anterior a activar los cobros). Queda
 * igual que un cargo automático: se paga, se exime o se marca "no vino".
 */
class DeudasManualesCobroService
{
    private const ID_GLOBAL = 1;

    private const IMPORTE_MAXIMO = 2147483647;

    public function cargarDeuda(User $actor, string $tipo, int $partidoId, int $equipoId, mixed $importeArs, ?string $motivo, ?int $edicionSolicitada = null): CargoPlanilla
    {
        try {
            return DB::transaction(function () use ($actor, $tipo, $partidoId, $equipoId, $importeArs, $motivo, $edicionSolicitada): CargoPlanilla {
                $actorVigente = $this->resolverActorAdmin($actor);
                $this->exigirConfiguracionActiva();

                if (! is_int($importeArs) || $importeArs < 1 || $importeArs > self::IMPORTE_MAXIMO) {
                    throw ValidationException::withMessages(['importe_ars' => ['El importe debe ser un entero en ARS mayor a 0.']]);
                }

                $partido = $this->partidoJugado($tipo, $partidoId);
                if ($edicionSolicitada !== null && (int) $partido->idEdicion !== $edicionSolicitada) {
                    throw ValidationException::withMessages(['partido' => ['El partido no pertenece a la edición seleccionada.']]);
                }
                $idOponente = $this->oponente($partido, $equipoId);
                $idEdicion = (int) $partido->idEdicion;

                if (PoliticaExencionCobroPlanilla::query()->where('idEquipo', $equipoId)->where('idEdicion', $idEdicion)->exists()) {
                    throw ValidationException::withMessages(['idEquipo' => ['El equipo está exento de planillas en esta edición.']]);
                }

                if (CargoPlanilla::query()->where('tipo', $tipo)->where('parent_id', $partidoId)->where('idEquipo', $equipoId)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['partido' => ['El equipo ya tiene un cargo de planilla en este partido.']]);
                }

                $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);
                $cargo = CargoPlanilla::query()->make([
                    'tipo' => $tipo,
                    'idEquipo' => $equipoId,
                    'idEdicion' => $idEdicion,
                    'idCategoria' => (int) $partido->idCategoria,
                    'precio_original' => $importeArs,
                    'snapshot' => $this->snapshot($partido, $equipoId, $idOponente, $motivo),
                ]);
                $cargo->parent()->associate($partido);
                if (! $cargo->save() || ! $cargo->exists) {
                    throw new RuntimeException('No se pudo registrar la deuda manual.');
                }

                $evento = new HistorialCobroPlanilla([
                    'accion' => 'cargar_deuda_manual', 'usuario_id' => (int) $actorVigente->id, 'actor_nombre' => $actorVigente->name,
                    'valores_antes' => ['idEquipo' => $equipoId, 'idEdicion' => $idEdicion],
                    'valores_despues' => [
                        'cargo_id' => (int) $cargo->id, 'tipo' => $tipo, 'partido_id' => $partidoId,
                        'importe_ars' => $importeArs, 'motivo' => $motivo,
                    ],
                ]);
                if (! $evento->save() || ! $evento->exists) {
                    throw new RuntimeException('No se pudo registrar el historial de la deuda manual.');
                }

                return $cargo;
            });
        } catch (Throwable $excepcion) {
            Log::error('No se pudo cargar la deuda manual de planilla.', [
                'tipo' => $tipo, 'partido_id' => $partidoId, 'equipo_id' => $equipoId,
                'actor_id' => $actor->exists ? $actor->getKey() : null, 'excepcion' => $excepcion::class,
            ]);

            throw $excepcion;
        }
    }

    /**
     * Partidos ya jugados del equipo en la edición, del más reciente al más
     * viejo, marcando los que ya tienen cargo para ese equipo.
     *
     * @return Collection<int, array{valor: string, tipo: string, id: int, etapa: string, fecha: ?Carbon, rival: string, cancha: mixed, tiene_cargo: bool}>
     */
    public function partidosJugados(int $edicionId, int $equipoId): Collection
    {
        $delEquipo = fn ($query) => $query->where('idEdicion', $edicionId)
            ->where(fn ($q) => $q->where('idEquipoLocal', $equipoId)->orWhere('idEquipoVisitante', $equipoId));

        $regulares = $delEquipo(Partido::query())->whereNotNull('horario_datetime')->where('horario_datetime', '<=', now())
            ->where('jugado', '>', 0)->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante')
            ->with(['fecha', 'equipoLocal', 'equipoVisitante'])->get()
            ->map(fn (Partido $partido): array => [
                'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $partido, 'fecha' => $partido->horario_datetime,
                'etapa' => $partido->fecha?->nombre ?? 'Fecha sin nombre',
            ]);
        $finales = $delEquipo(InstanciaFinal::query())->whereNotNull('horario')->where('horario', '<=', now())
            ->whereNotNull('golesEquipoLocal')->whereNotNull('golesEquipoVisitante')
            ->with(['fase', 'equipoLocal', 'equipoVisitante'])->get()
            ->map(fn (InstanciaFinal $final): array => [
                'tipo' => CargoPlanilla::TIPO_INSTANCIA_FINAL, 'partido' => $final, 'fecha' => $final->horario,
                'etapa' => $final->fase?->nombre ?? 'Instancia final',
            ]);

        $conCargo = CargoPlanilla::query()->where('idEquipo', $equipoId)->get(['tipo', 'parent_id'])
            ->map(fn (CargoPlanilla $cargo): string => $cargo->tipo.'-'.$cargo->parent_id)->flip();

        return $regulares->concat($finales)
            ->map(function (array $item) use ($equipoId, $conCargo): array {
                $partido = $item['partido'];
                $rival = (int) $partido->idEquipoLocal === $equipoId ? $partido->equipoVisitante : $partido->equipoLocal;

                return [
                    'valor' => $item['tipo'].'-'.$partido->id, 'tipo' => $item['tipo'], 'id' => (int) $partido->id,
                    'etapa' => $item['etapa'], 'fecha' => Carbon::parse($item['fecha']),
                    'rival' => $rival?->nombre ?? 'Rival eliminado', 'cancha' => $partido->cancha,
                    'tiene_cargo' => $conCargo->has($item['tipo'].'-'.$partido->id),
                ];
            })
            ->sortByDesc(fn (array $item): int => $item['fecha']->getTimestamp())
            ->values();
    }

    private function resolverActorAdmin(User $actor): User
    {
        $mensaje = 'Solo un administrador puede cargar deudas de planillas.';

        if (! $actor->exists || $actor->isDirty($actor->getKeyName())) {
            throw new AuthorizationException($mensaje);
        }

        $actorVigente = User::query()->lockForUpdate()->find($actor->getKey(), ['id', 'name', 'rol']);
        if ($actorVigente === null || ! $actorVigente->isAdmin()) {
            throw new AuthorizationException($mensaje);
        }

        return $actorVigente;
    }

    private function exigirConfiguracionActiva(): void
    {
        $configuracion = ConfiguracionCobroPlanilla::query()->whereKey(self::ID_GLOBAL)->lockForUpdate()->first();
        if ($configuracion === null) {
            throw new RuntimeException('La configuración global de cobros no está inicializada (falta la fila id 1).');
        }

        if ($configuracion->activado_at === null) {
            throw ValidationException::withMessages(['cobros' => ['Los cobros de planillas no están activados.']]);
        }
    }

    private function partidoJugado(string $tipo, int $partidoId): Partido|InstanciaFinal
    {
        $clase = CargoPlanilla::TIPOS_FINANCIEROS[$tipo] ?? null;
        $partido = $clase === null ? null : $clase::query()->lockForUpdate()->find($partidoId);
        if ($partido === null) {
            throw ValidationException::withMessages(['partido' => ['El partido no existe.']]);
        }

        $fecha = $partido instanceof InstanciaFinal ? $partido->horario : $partido->horario_datetime;
        if ($fecha === null || Carbon::parse($fecha)->isFuture() || $partido->golesEquipoLocal === null || $partido->golesEquipoVisitante === null
            || ($partido instanceof Partido && (int) $partido->jugado <= 0)) {
            throw ValidationException::withMessages(['partido' => ['Solo se puede cargar la deuda de un partido ya jugado.']]);
        }

        return $partido;
    }

    private function oponente(Partido|InstanciaFinal $partido, int $equipoId): int
    {
        return match ($equipoId) {
            (int) $partido->idEquipoLocal => (int) $partido->idEquipoVisitante,
            (int) $partido->idEquipoVisitante => (int) $partido->idEquipoLocal,
            default => throw ValidationException::withMessages(['idEquipo' => ['El equipo no jugó ese partido.']]),
        };
    }

    /**
     * Mismo formato que los cargos automáticos, con el origen y el motivo de la carga.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Partido|InstanciaFinal $partido, int $equipoId, int $idOponente, ?string $motivo): array
    {
        $esFinal = $partido instanceof InstanciaFinal;
        $partido->loadMissing(['edicion', 'categoria']);
        $equipos = Equipo::query()->whereKey([$equipoId, $idOponente])->pluck('nombre', 'id');

        return [
            'origen' => 'manual',
            'motivo' => $motivo,
            'equipo' => ['id' => $equipoId, 'nombre' => $equipos[$equipoId] ?? null],
            'oponente' => ['id' => $idOponente, 'nombre' => $equipos[$idOponente] ?? null],
            'edicion' => ['id' => (int) $partido->idEdicion, 'nombre' => $partido->edicion?->nombre],
            'categoria' => ['id' => (int) $partido->idCategoria, 'nombre' => $partido->categoria?->nombreCategoria],
            'cancha' => $partido->cancha,
            'fecha_partido' => $esFinal ? $partido->horario : $partido->horario_datetime,
            'partido' => [
                'id' => (int) $partido->getKey(),
                'public_token' => $partido->public_token,
                'es_interzonal' => $esFinal ? null : (bool) $partido->es_interzonal,
                'id_fecha' => $esFinal ? null : (int) $partido->idFechas,
            ],
        ];
    }
}
