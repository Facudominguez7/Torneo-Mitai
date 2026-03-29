<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use App\Models\TrazabilidadBajaJugador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BajaJugadorService
{
    public function procesarBaja(ListaBuenaFe $listaBuenaFe, ?string $motivoBaja = null, ?string $usuarioBaja = null): TrazabilidadBajaJugador
    {
        return DB::transaction(function () use ($listaBuenaFe, $motivoBaja, $usuarioBaja) {
            $listaBuenaFe->loadMissing(['jugador', 'equipo', 'categoria', 'edicion']);

            $fechaBaja = $listaBuenaFe->fecha_baja ?? now();
            $motivoFinal = $motivoBaja ?? $listaBuenaFe->motivo_baja;
            $planillas = $this->obtenerPlanillasOperativas($listaBuenaFe);

            $trazabilidad = TrazabilidadBajaJugador::where('lista_buena_fe_id', $listaBuenaFe->id)
                ->where('fecha_baja', $fechaBaja)
                ->first();

            if (! $trazabilidad) {
                $trazabilidad = TrazabilidadBajaJugador::create([
                    'lista_buena_fe_id' => $listaBuenaFe->id,
                    'dni_jugador' => $listaBuenaFe->dni_jugador,
                    'idEquipo' => $listaBuenaFe->idEquipo,
                    'idEdicion' => $listaBuenaFe->idEdicion,
                    'idCategoria' => $listaBuenaFe->idCategoria,
                    'nombre_jugador' => $listaBuenaFe->jugador?->nombre,
                    'apellido_jugador' => $listaBuenaFe->jugador?->apellido,
                    'equipo_nombre' => $listaBuenaFe->equipo?->nombre,
                    'categoria_nombre' => $listaBuenaFe->categoria?->nombreCategoria,
                    'edicion_nombre' => $listaBuenaFe->edicion?->nombre,
                    'fecha_alta_lista' => $listaBuenaFe->fecha_alta,
                    'fecha_baja' => $fechaBaja,
                    'motivo_baja' => $motivoFinal,
                    'usuario_baja' => $usuarioBaja,
                    'total_goles' => (int) $planillas->sum('goles'),
                    'total_partidos' => (int) $planillas->where('asistio', true)->count(),
                    'total_tarjetas_rojas' => (int) $planillas->where('tarjeta_roja', true)->count(),
                    'cantidad_planillas_archivadas' => $planillas->count(),
                    'historial_planillas' => $this->serializarPlanillas($planillas),
                ]);
            }

            $this->marcarBajaAdministrativa($listaBuenaFe, $fechaBaja, $motivoFinal);
            $this->limpiarOperacion($listaBuenaFe, $planillas);

            Log::info('Jugador dado de baja con trazabilidad archivada', [
                'dni_jugador' => $listaBuenaFe->dni_jugador,
                'idEquipo' => $listaBuenaFe->idEquipo,
                'idEdicion' => $listaBuenaFe->idEdicion,
                'idCategoria' => $listaBuenaFe->idCategoria,
                'trazabilidad_id' => $trazabilidad->id,
            ]);

            return $trazabilidad;
        });
    }

    private function obtenerPlanillasOperativas(ListaBuenaFe $listaBuenaFe): Collection
    {
        return PlanillaJugador::where('dni_jugador', $listaBuenaFe->dni_jugador)
            ->where('idEquipo', $listaBuenaFe->idEquipo)
            ->where('idEdicion', $listaBuenaFe->idEdicion)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function serializarPlanillas(Collection $planillas): array
    {
        return $planillas->map(function (PlanillaJugador $planilla): array {
            return [
                'partido_id' => $planilla->partido_id,
                'partido_type' => $planilla->partido_type,
                'idEquipo' => $planilla->idEquipo,
                'idCategoria' => $planilla->idCategoria,
                'numero_camiseta' => $planilla->numero_camiseta,
                'goles' => $planilla->goles,
                'asistio' => (bool) $planilla->asistio,
                'tarjeta_roja' => (bool) $planilla->tarjeta_roja,
                'fecha_nacimiento' => $planilla->fecha_nacimiento,
                'creado_en' => optional($planilla->created_at)?->toDateTimeString(),
                'actualizado_en' => optional($planilla->updated_at)?->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function marcarBajaAdministrativa(ListaBuenaFe $listaBuenaFe, $fechaBaja, ?string $motivoBaja): void
    {
        $listaBuenaFe->forceFill([
            'activo' => false,
            'fecha_baja' => $fechaBaja,
            'motivo_baja' => $motivoBaja,
        ])->save();
    }

    private function limpiarOperacion(ListaBuenaFe $listaBuenaFe, Collection $planillas): void
    {
        if ($planillas->isNotEmpty()) {
            $this->revertirEstadisticasGlobales($listaBuenaFe->dni_jugador, $planillas);

            PlanillaJugador::where('dni_jugador', $listaBuenaFe->dni_jugador)
                ->where('idEquipo', $listaBuenaFe->idEquipo)
                ->where('idEdicion', $listaBuenaFe->idEdicion)
                ->delete();
        }

        TablaGoleador::where('dni_jugador', $listaBuenaFe->dni_jugador)
            ->where('idEdicion', $listaBuenaFe->idEdicion)
            ->where('idCategoria', $listaBuenaFe->idCategoria)
            ->delete();
    }

    private function revertirEstadisticasGlobales(string $dniJugador, Collection $planillas): void
    {
        $jugador = Jugador::where('dni', $dniJugador)->first();

        if (! $jugador) {
            Log::warning("Jugador con DNI {$dniJugador} no encontrado al revertir estadísticas por baja");

            return;
        }

        $jugador->goles_totales = max(0, ($jugador->goles_totales ?? 0) - $planillas->sum('goles'));
        $jugador->partidos_totales = max(0, ($jugador->partidos_totales ?? 0) - $planillas->where('asistio', true)->count());
        $jugador->tarjetas_rojas_totales = max(0, ($jugador->tarjetas_rojas_totales ?? 0) - $planillas->where('tarjeta_roja', true)->count());
        $jugador->save();
    }
}
