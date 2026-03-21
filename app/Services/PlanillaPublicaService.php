<?php

namespace App\Services;

use App\Constants\PartidoConstants;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PlanillaPublicaService
{
    public function obtenerPlanilla(string $tipoPartido, string $publicToken): array
    {
        $tipoNormalizado = $this->normalizarTipoPartido($tipoPartido);
        $partido = $this->resolverPartido($tipoNormalizado, $publicToken);
        $partidoType = PartidoConstants::getModelClass($tipoNormalizado);

        $idEdicion = $partido->idEdicion;

        $registros = PlanillaJugador::query()
            ->join('jugadores', 'planilla_jugadores.dni_jugador', '=', 'jugadores.dni')
            ->where('planilla_jugadores.partido_id', $partido->id)
            ->where('planilla_jugadores.partido_type', $partidoType)
            ->where('planilla_jugadores.idEdicion', $idEdicion)
            ->whereIn('planilla_jugadores.idEquipo', [
                $partido->idEquipoLocal,
                $partido->idEquipoVisitante,
            ])
            ->orderBy('planilla_jugadores.idEquipo')
            ->orderBy('jugadores.apellido')
            ->orderBy('jugadores.nombre')
            ->get([
                'planilla_jugadores.idEquipo',
                'jugadores.nombre',
                'jugadores.apellido',
                'planilla_jugadores.numero_camiseta',
                'planilla_jugadores.goles',
                'planilla_jugadores.asistio as asistencia',
                'planilla_jugadores.updated_at',
            ]);

        $local = [];
        $visitante = [];

        foreach ($registros as $registro) {
            $jugador = [
                'nombre' => $registro->nombre,
                'apellido' => $registro->apellido,
                'numero_camiseta' => $registro->numero_camiseta !== null
                    ? (int) $registro->numero_camiseta
                    : null,
                'goles' => (int) $registro->goles,
                'asistencia' => (bool) $registro->asistencia,
            ];

            if ((int) $registro->idEquipo === (int) $partido->idEquipoLocal) {
                $local[] = $jugador;

                continue;
            }

            if ((int) $registro->idEquipo === (int) $partido->idEquipoVisitante) {
                $visitante[] = $jugador;
            }
        }

        $ultimaActualizacion = $registros
            ->max('updated_at');

        return [
            'data' => [
                'partido' => [
                    'tipo' => $tipoNormalizado,
                    'categoria' => $partido->categoria?->nombreCategoria,
                    'equipo_local' => $partido->equipoLocal?->nombre,
                    'equipo_visitante' => $partido->equipoVisitante?->nombre,
                ],
                'goles_confirmados' => $partido->goles_confirmados_at !== null,
                'goles_confirmados_at' => $partido->goles_confirmados_at?->format('d/m/Y H:i'),
                'ultima_actualizacion' => $ultimaActualizacion
                    ? Carbon::parse($ultimaActualizacion)->format('d/m/Y H:i')
                    : null,
                'local' => $local,
                'visitante' => $visitante,
            ],
            'meta' => [
                'sin_planilla' => empty($local) && empty($visitante),
            ],
        ];
    }

    private function resolverPartido(string $tipoPartido, string $publicToken): Model
    {
        $modelo = PartidoConstants::isInstanciaFinal($tipoPartido)
            ? InstanciaFinal::query()
            : Partido::query();

        return $modelo
            ->with([
                'equipoLocal:id,nombre',
                'equipoVisitante:id,nombre',
                'categoria:id,nombreCategoria',
            ])
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    private function normalizarTipoPartido(string $tipoPartido): string
    {
        $tipoNormalizado = strtolower(str_replace(['-', ' '], '_', trim($tipoPartido)));

        if ($tipoNormalizado === PartidoConstants::TIPO_PARTIDO_NORMAL) {
            return PartidoConstants::TIPO_PARTIDO_NORMAL;
        }

        if (in_array($tipoNormalizado, [PartidoConstants::TIPO_INSTANCIA_FINAL, 'instanciafinal'], true)) {
            return PartidoConstants::TIPO_INSTANCIA_FINAL;
        }

        throw new InvalidArgumentException('Tipo de partido invalido.');
    }
}
