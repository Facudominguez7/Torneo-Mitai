<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TrazabilidadBajaJugador;

/**
 * Consulta de un jugador por DNI dentro de una edición. La usan la pantalla
 * Consulta de jugador y el buscador del header del panel, para que ambas
 * muestren exactamente la misma información.
 */
class ConsultaJugadorService
{
    /**
     * Todas las estadísticas e historial se calculan para la edición elegida;
     * si allí no hay registros, se informa la última inscripción histórica en
     * Lista de Buena Fe para dar contexto sin mezclar métricas.
     *
     * @return array<string, mixed>|null null cuando no existe un jugador con ese DNI.
     */
    public function buscar(int $dni, int $idEdicion): ?array
    {
        $bajasArchivadas = collect();

        // Buscar el jugador
        $jugador = Jugador::where('dni', $dni)->first();

        if (! $jugador) {
            return null;
        }

        $bajasArchivadas = TrazabilidadBajaJugador::where('dni_jugador', $dni)
            ->where('idEdicion', $idEdicion)
            ->orderByDesc('fecha_baja')
            ->get();

        // Obtener el historial desde planilla_jugadores por DNI + edición seleccionada.
        //
        // La planilla es la fuente de verdad para saber qué jugó el jugador y se
        // resuelve el detalle del partido según `partido_type` en cada fila.
        $planillas = PlanillaJugador::where('dni_jugador', $dni)
            ->where('idEdicion', $idEdicion)
            ->with(['equipo', 'categoria'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $idsPartidos = $planillas
            ->filter(function (PlanillaJugador $planilla) {
                return $planilla->partido_type === Partido::class || empty($planilla->partido_type);
            })
            ->pluck('partido_id')
            ->unique()
            ->values();

        $idsInstanciasFinales = $planillas
            ->pluck('partido_id')
            ->unique()
            ->values();

        $partidosPorId = $idsPartidos->isEmpty()
            ? collect()
            : Partido::with(['equipoLocal', 'equipoVisitante', 'fecha'])
                ->where('idEdicion', $idEdicion)
                ->whereIn('id', $idsPartidos)
                ->get()
                ->keyBy('id');

        $instanciasPorId = $idsInstanciasFinales->isEmpty()
            ? collect()
            : InstanciaFinal::with(['equipoLocal', 'equipoVisitante', 'fase'])
                ->where('idEdicion', $idEdicion)
                ->whereIn('id', $idsInstanciasFinales)
                ->get()
                ->keyBy('id');

        // Variables por defecto
        $equipoActual = null;
        $categoriaActual = null;
        $totalGoles = 0;
        $totalPartidos = 0;
        $totalTarjetasRojas = 0;
        $partidosJugados = collect();
        $partidosConGoles = collect();
        $listaBuenaFe = null;
        $sinRegistrosDePartidos = false;

        if ($planillas->isEmpty()) {
            // Buscar en Lista de Buena Fe si no hay registros de planilla
            $listaBuenaFe = ListaBuenaFe::where('dni_jugador', $dni)
                ->where('idEdicion', $idEdicion)
                ->where('activo', true)
                ->with(['equipo', 'categoria'])
                ->first();

            if ($listaBuenaFe) {
                $equipoActual = $listaBuenaFe->equipo;
                $categoriaActual = $listaBuenaFe->categoria;
                $sinRegistrosDePartidos = true;
            } else {
                // Buscar en cualquier edición si no está en la actual
                $listaBuenaFe = ListaBuenaFe::where('dni_jugador', $dni)
                    ->where('activo', true)
                    ->with(['equipo', 'categoria', 'edicion'])
                    ->orderBy('idEdicion', 'desc')
                    ->first();

                if ($listaBuenaFe) {
                    $equipoActual = $listaBuenaFe->equipo;
                    $categoriaActual = $listaBuenaFe->categoria;
                    $sinRegistrosDePartidos = true;
                } else {
                    // Sin registros en ningún lado, pero el jugador existe
                    $sinRegistrosDePartidos = true;
                }
            }
        } else {
            // Obtener el equipo y categoría actual (último registro cargado)
            $planillaReciente = $planillas->first();
            $equipoActual = Equipo::find($planillaReciente->idEquipo);
            $categoriaActual = Categoria::find($planillaReciente->idCategoria);

            // Calcular estadísticas totales en esta edición
            $totalGoles = $planillas->sum('goles');
            $totalPartidos = $planillas->where('asistio', 1)->count();
            $totalTarjetasRojas = $planillas->where('tarjeta_roja', 1)->count();

            // Obtener todos los partidos jugados con detalles
            $partidosJugados = $planillas->map(function (PlanillaJugador $planilla) use ($partidosPorId, $instanciasPorId) {
                $esInstanciaFinal = $planilla->partido_type === InstanciaFinal::class;

                if ($esInstanciaFinal) {
                    $partido = $instanciasPorId->get($planilla->partido_id);
                } else {
                    $partido = $partidosPorId->get($planilla->partido_id);

                    // Si viene mal tipado como Partido, pero existe instancia final
                    // en la misma edición, priorizar instancia para mostrar fase.
                    if (! $partido) {
                        $partido = $instanciasPorId->get($planilla->partido_id);
                        $esInstanciaFinal = $partido instanceof InstanciaFinal;
                    }

                    // Fallback legacy para registros sin tipo definido.
                    if (! $partido && empty($planilla->partido_type)) {
                        $partido = $instanciasPorId->get($planilla->partido_id);
                        $esInstanciaFinal = $partido instanceof InstanciaFinal;
                    }
                }

                if ($partido instanceof Partido) {
                    return [
                        'tipo' => 'Partido Regular',
                        'idReferencia' => $partido->id,
                        'origen' => 'partido',
                        'fecha' => optional($partido->fecha)->nombre ?? 'Fecha no disponible',
                        'equipoLocal' => optional($partido->equipoLocal)->nombre ?? 'Equipo no disponible',
                        'equipoVisitante' => optional($partido->equipoVisitante)->nombre ?? 'Equipo no disponible',
                        'golesLocal' => $partido->golesEquipoLocal,
                        'golesVisitante' => $partido->golesEquipoVisitante,
                        'goles_jugador' => $planilla->goles,
                        'asistio' => $planilla->asistio,
                        'tarjeta_roja' => $planilla->tarjeta_roja,
                        'numero_camiseta' => $planilla->numero_camiseta,
                    ];
                }

                if ($partido instanceof InstanciaFinal) {
                    return [
                        'tipo' => 'Instancia Final',
                        'idReferencia' => $partido->id,
                        'origen' => 'instancia_final',
                        'fecha' => optional($partido->fase)->nombre
                            ?? optional($partido->fase)->nombreFase
                            ?? 'Fase no disponible',
                        'equipoLocal' => optional($partido->equipoLocal)->nombre ?? 'Equipo no disponible',
                        'equipoVisitante' => optional($partido->equipoVisitante)->nombre ?? 'Equipo no disponible',
                        'golesLocal' => $partido->golesEquipoLocal,
                        'golesVisitante' => $partido->golesEquipoVisitante,
                        'goles_jugador' => $planilla->goles,
                        'asistio' => $planilla->asistio,
                        'tarjeta_roja' => $planilla->tarjeta_roja,
                        'numero_camiseta' => $planilla->numero_camiseta,
                    ];
                }

                return [
                    'tipo' => $esInstanciaFinal ? 'Instancia Final' : 'Partido Regular',
                    'idReferencia' => $planilla->partido_id,
                    'origen' => $esInstanciaFinal ? 'instancia_final' : 'partido',
                    'fecha' => 'Detalle no disponible',
                    'equipoLocal' => 'Equipo no disponible',
                    'equipoVisitante' => 'Equipo no disponible',
                    'golesLocal' => null,
                    'golesVisitante' => null,
                    'goles_jugador' => $planilla->goles,
                    'asistio' => $planilla->asistio,
                    'tarjeta_roja' => $planilla->tarjeta_roja,
                    'numero_camiseta' => $planilla->numero_camiseta,
                ];
            })->values();

            // Obtener los partidos donde hizo goles
            $partidosConGoles = $partidosJugados->filter(function ($partido) {
                return $partido['goles_jugador'] > 0;
            });
        }

        return compact(
            'jugador',
            'equipoActual',
            'categoriaActual',
            'totalGoles',
            'totalPartidos',
            'totalTarjetasRojas',
            'partidosJugados',
            'partidosConGoles',
            'listaBuenaFe',
            'bajasArchivadas',
            'sinRegistrosDePartidos'
        );
    }
}
