<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PlanillaPartidoAnteriorResolver
{
    /**
     * @return array{status: string, message: string, partido_anterior_id: int|null, partido: Partido|InstanciaFinal|null, candidatos: array<int, array<string, mixed>>}
     */
    public function resolver(string $tipoPartido, int $partidoId, int $idEdicion, int $idCategoria, int $idEquipo): array
    {
        $partidoModel = PartidoConstants::getModelClass($tipoPartido);

        /** @var Collection<int, Partido|InstanciaFinal> $candidatos */
        $candidatos = $partidoModel::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->whereKeyNot($partidoId)
            ->where(static function ($query) use ($idEquipo): void {
                $query->where('idEquipoLocal', $idEquipo)
                    ->orWhere('idEquipoVisitante', $idEquipo);
            })
            ->get();

        if ($candidatos->isEmpty()) {
            return [
                'status' => 'no_previous_match',
                'message' => 'El equipo no tiene un partido anterior válido.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => [],
            ];
        }

        $clavesRepetidas = $candidatos
            ->groupBy(fn (Model $partido): string => $this->claveComparacion($partido))
            ->first(static fn (Collection $grupo): bool => $grupo->count() > 1);

        if ($clavesRepetidas !== null) {
            return [
                'status' => 'ambiguous_previous_match',
                'message' => 'No se pudo determinar un único antecedente válido.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => $this->formatearCandidatos($candidatos),
            ];
        }

        $partidoAnterior = $candidatos
            ->sort(fn (Model $a, Model $b): int => $this->compararPartidos($a, $b))
            ->values()
            ->first();

        return [
            'status' => 'ok',
            'message' => 'Partido anterior resuelto correctamente.',
            'partido_anterior_id' => $partidoAnterior?->id,
            'partido' => $partidoAnterior,
            'candidatos' => $this->formatearCandidatos($candidatos),
        ];
    }

    /**
     * @return array{status: string, message: string, partido_anterior_id: int|null, partido: Partido|InstanciaFinal|null, candidatos: array<int, array<string, mixed>>}
     */
    public function resolverMismoDiaConAsistencia(string $tipoPartido, int $partidoId, int $idEdicion, int $idCategoria, int $idEquipo): array
    {
        $partidoModel = PartidoConstants::getModelClass($tipoPartido);

        /** @var Partido|InstanciaFinal|null $partidoActual */
        $partidoActual = $partidoModel::query()->find($partidoId);

        if (! $partidoActual instanceof Model) {
            return [
                'status' => 'no_previous_match',
                'message' => 'No se encontró el partido actual para resolver el antecedente.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => [],
            ];
        }

        $momentoActual = $this->normalizarMomentoPartido($partidoActual);

        if ($momentoActual === null) {
            return [
                'status' => 'no_previous_match',
                'message' => 'El partido actual no tiene horario válido para buscar antecedente.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => [],
            ];
        }

        /** @var Collection<int, Partido|InstanciaFinal> $candidatos */
        $candidatos = $partidoModel::query()
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('jugado', 1)
            ->whereKeyNot($partidoId)
            ->where(static function ($query) use ($idEquipo): void {
                $query->where('idEquipoLocal', $idEquipo)
                    ->orWhere('idEquipoVisitante', $idEquipo);
            })
            ->get()
            ->filter(function (Model $partido) use ($partidoActual, $momentoActual): bool {
                $momentoCandidato = $this->normalizarMomentoPartido($partido);

                if ($momentoCandidato === null || ! $momentoCandidato->isSameDay($momentoActual)) {
                    return false;
                }

                return $this->esPartidoAnterior($partido, $partidoActual);
            })
            ->filter(function (Model $partido) use ($idEdicion, $idEquipo, $partidoModel): bool {
                return PlanillaJugador::query()
                    ->where('partido_id', (int) $partido->id)
                    ->where('partido_type', $partidoModel)
                    ->where('idEdicion', $idEdicion)
                    ->where('idEquipo', $idEquipo)
                    ->where('asistio', true)
                    ->exists();
            })
            ->values();

        if ($candidatos->isEmpty()) {
            return [
                'status' => 'no_previous_match',
                'message' => 'El equipo no tiene un partido anterior del mismo día con asistencias marcadas.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => [],
            ];
        }

        $clavesRepetidas = $candidatos
            ->groupBy(fn (Model $partido): string => $this->claveComparacion($partido))
            ->first(static fn (Collection $grupo): bool => $grupo->count() > 1);

        if ($clavesRepetidas !== null) {
            return [
                'status' => 'ambiguous_previous_match',
                'message' => 'No se pudo determinar un único antecedente válido del mismo día.',
                'partido_anterior_id' => null,
                'partido' => null,
                'candidatos' => $this->formatearCandidatos($candidatos),
            ];
        }

        $partidoAnterior = $candidatos
            ->sort(fn (Model $a, Model $b): int => $this->compararPartidos($a, $b))
            ->values()
            ->first();

        return [
            'status' => 'ok',
            'message' => 'Partido anterior del mismo día resuelto correctamente.',
            'partido_anterior_id' => $partidoAnterior?->id,
            'partido' => $partidoAnterior,
            'candidatos' => $this->formatearCandidatos($candidatos),
        ];
    }

    private function compararPartidos(Model $a, Model $b): int
    {
        return [$this->claveFechas($a), $this->claveHorario($a), -((int) $a->id)] <=> [$this->claveFechas($b), $this->claveHorario($b), -((int) $b->id)];
    }

    private function claveComparacion(Model $partido): string
    {
        return $this->claveFechas($partido).'|'.$this->claveHorario($partido);
    }

    private function claveFechas(Model $partido): int
    {
        return (int) ($partido->idFechas ?? PHP_INT_MAX);
    }

    private function claveHorario(Model $partido): string
    {
        $horarioDatetime = $partido->getAttribute('horario_datetime');

        if ($horarioDatetime !== null && $horarioDatetime !== '') {
            return Carbon::parse((string) $horarioDatetime)->format('Y-m-d H:i:s');
        }

        $horario = $partido->getAttribute('horario');

        if ($horario === null || $horario === '') {
            return '9999-12-31 23:59:59';
        }

        $horarioNormalizado = trim((string) $horario);

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $horarioNormalizado) === 1) {
            return str_pad($horarioNormalizado, 8, '0', STR_PAD_LEFT);
        }

        if (str_contains($horarioNormalizado, '-')) {
            try {
                return Carbon::parse($horarioNormalizado)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return $horarioNormalizado;
            }
        }

        return $horarioNormalizado;
    }

    private function normalizarMomentoPartido(Model $partido): ?Carbon
    {
        $horarioDatetime = $partido->getAttribute('horario_datetime');

        if ($horarioDatetime !== null && $horarioDatetime !== '') {
            try {
                return Carbon::parse((string) $horarioDatetime);
            } catch (\Throwable) {
                return null;
            }
        }

        $horario = $partido->getAttribute('horario');

        if ($horario === null || $horario === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $horario);
        } catch (\Throwable) {
            return null;
        }
    }

    private function esPartidoAnterior(Model $candidato, Model $actual): bool
    {
        $momentoCandidato = $this->normalizarMomentoPartido($candidato);
        $momentoActual = $this->normalizarMomentoPartido($actual);

        if ($momentoCandidato !== null && $momentoActual !== null) {
            if ($momentoCandidato->eq($momentoActual)) {
                return (int) $candidato->id < (int) $actual->id;
            }

            return $momentoCandidato->lt($momentoActual);
        }

        return (int) $candidato->id < (int) $actual->id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatearCandidatos(Collection $candidatos): array
    {
        return $candidatos->map(static function (Model $partido): array {
            return [
                'id' => (int) $partido->id,
                'idFechas' => (int) ($partido->idFechas ?? 0),
                'horario' => $partido->getAttribute('horario_datetime') ?? $partido->getAttribute('horario'),
            ];
        })->values()->all();
    }
}
