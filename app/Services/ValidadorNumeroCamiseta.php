<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanillaJugador;
use Illuminate\Database\Eloquent\Builder;

final class ValidadorNumeroCamiseta
{
    /**
     * Determina si el número de camiseta ya lo usa otro jugador con
     * asistencia marcada del mismo equipo en el mismo partido.
     *
     * Solo cuentan los jugadores presentes: un número cargado en una fila
     * sin asistencia no ocupa lugar. El número 0 representa "sin número
     * asignado" y puede repetirse.
     */
    public function numeroOcupado(
        string $partidoType,
        int $partidoId,
        int $idEquipo,
        int $numeroCamiseta,
        ?int $excluirPlanillaId = null,
    ): bool {
        if ($numeroCamiseta <= 0) {
            return false;
        }

        return PlanillaJugador::query()
            ->where('partido_id', $partidoId)
            ->where('partido_type', $partidoType)
            ->where('idEquipo', $idEquipo)
            ->where('numero_camiseta', $numeroCamiseta)
            ->where('asistio', true)
            ->when(
                $excluirPlanillaId !== null,
                static fn (Builder $query): Builder => $query->whereKeyNot($excluirPlanillaId),
            )
            ->exists();
    }
}
