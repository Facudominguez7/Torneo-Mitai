<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Jugador;
use App\Models\TablaGoleador;

final class EstadisticasJugadorService
{
    public function actualizarDeltaGoles(string $dniJugador, int $idEdicion, int $idCategoria, int $idEquipo, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $jugador = Jugador::query()
            ->where('dni', $dniJugador)
            ->lockForUpdate()
            ->first();

        if ($jugador === null) {
            return;
        }

        $jugador->goles_totales = max(0, ((int) $jugador->goles_totales) + $delta);
        $jugador->save();

        $goleador = TablaGoleador::query()
            ->where('dni_jugador', $dniJugador)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->lockForUpdate()
            ->first();

        if ($goleador !== null) {
            $goleador->cantidadGoles = max(0, ((int) $goleador->cantidadGoles) + $delta);

            if ((int) $goleador->cantidadGoles === 0) {
                $goleador->delete();

                return;
            }

            $goleador->save();

            return;
        }

        if ($delta > 0) {
            TablaGoleador::create([
                'dni_jugador' => $dniJugador,
                'cantidadGoles' => $delta,
                'idEquipo' => $idEquipo,
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
            ]);
        }
    }
}
