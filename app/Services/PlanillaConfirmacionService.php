<?php

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use Carbon\Carbon;

class PlanillaConfirmacionService
{
    public function resolverPartido(string $tipoPartido, int $partidoId, ?int $idEdicion = null): Partido|InstanciaFinal
    {
        if (PartidoConstants::isInstanciaFinal($tipoPartido)) {
            $consulta = InstanciaFinal::query();
        } else {
            $consulta = Partido::query();
        }

        $consulta->whereKey($partidoId);

        if ($idEdicion !== null) {
            $consulta->where('idEdicion', $idEdicion);
        }

        /** @var Partido|InstanciaFinal $partido */
        $partido = $consulta->firstOrFail();

        return $partido;
    }

    public function confirmarGoles(string $tipoPartido, int $partidoId, ?int $idEdicion = null): Partido|InstanciaFinal
    {
        $partido = $this->resolverPartido($tipoPartido, $partidoId, $idEdicion);
        $partido->goles_confirmados_at = Carbon::now();
        $partido->save();

        return $partido;
    }

    public function quitarConfirmacion(string $tipoPartido, int $partidoId, ?int $idEdicion = null): Partido|InstanciaFinal
    {
        $partido = $this->resolverPartido($tipoPartido, $partidoId, $idEdicion);
        $partido->goles_confirmados_at = null;
        $partido->save();

        return $partido;
    }

    public function invalidarConfirmacionPorEdicion(string $tipoPartido, int $partidoId, ?int $idEdicion = null): bool
    {
        $partido = $this->resolverPartido($tipoPartido, $partidoId, $idEdicion);

        if ($partido->goles_confirmados_at === null) {
            return false;
        }

        $partido->goles_confirmados_at = null;
        $partido->save();

        return true;
    }
}
