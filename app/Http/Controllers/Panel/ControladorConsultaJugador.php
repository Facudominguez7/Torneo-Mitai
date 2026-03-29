<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Edicion;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Models\Partido;
use App\Models\InstanciaFinal;
use App\Models\TrazabilidadBajaJugador;
use Illuminate\Http\Request;

class ControladorConsultaJugador extends Controller
{
    /**
     * Mostrar el formulario de búsqueda con la edición de contexto del panel.
     *
     * Prioriza la edición solicitada por query (`idEdicion`) para que el módulo
     * sea consistente con el selector global del admin.
     */
    public function index(Request $request)
    {
        $ediciones = Edicion::orderBy('id', 'desc')->get(); // Para el layout admin.blade.php
        $idEdicionSolicitada = $request->filled('idEdicion')
            ? (int) $request->input('idEdicion')
            : null;
        $EdicionSeleccionada = $this->resolverEdicionSeleccionada($idEdicionSolicitada);
        $edicionActual = $EdicionSeleccionada;

        // Mantener el DNI si viene en la URL
        $dni = $request->input('dni', '');
        
        return view('Panel.jugador.consulta', compact('edicionActual', 'ediciones', 'EdicionSeleccionada', 'dni'));
    }

    /**
     * Buscar jugador por DNI respetando la edición seleccionada.
     *
     * Todas las estadísticas e historial se calculan para la edición elegida;
     * si allí no hay registros, se informa la última inscripción histórica en
     * Lista de Buena Fe para dar contexto sin mezclar métricas.
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'dni' => 'required|integer',
            'idEdicion' => 'nullable|integer|exists:ediciones,id',
        ]);

        $dni = (int) $request->input('dni');
        $idEdicionSolicitada = $request->filled('idEdicion')
            ? (int) $request->input('idEdicion')
            : null;
        $edicionActual = $this->resolverEdicionSeleccionada($idEdicionSolicitada);

        if (! $edicionActual) {
            return redirect()->route('jugador.consulta')
                ->with('error', 'No hay ediciones disponibles para realizar la consulta.');
        }

        $idEdicion = $edicionActual->id;
        $bajasArchivadas = collect();

        // Buscar el jugador
        $jugador = Jugador::where('dni', $dni)->first();

        if (! $jugador) {
            return redirect()->route('jugador.consulta', ['dni' => $dni, 'idEdicion' => $idEdicion])
                ->with('error', 'No se encontró ningún jugador con el DNI: '.$dni);
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
            $listaBuenaFe = \App\Models\ListaBuenaFe::where('dni_jugador', $dni)
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
                $listaBuenaFe = \App\Models\ListaBuenaFe::where('dni_jugador', $dni)
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
            $equipoActual = \App\Models\Equipo::find($planillaReciente->idEquipo);
            $categoriaActual = \App\Models\Categoria::find($planillaReciente->idCategoria);

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

        $ediciones = Edicion::orderBy('id', 'desc')->get(); // Para el layout admin.blade.php
        $EdicionSeleccionada = $edicionActual; // Para el selector de edición en el header

        return view('Panel.jugador.consulta', compact(
            'jugador',
            'equipoActual',
            'categoriaActual',
            'totalGoles',
            'totalPartidos',
            'totalTarjetasRojas',
            'partidosJugados',
            'partidosConGoles',
            'edicionActual',
            'ediciones',
            'EdicionSeleccionada',
            'listaBuenaFe',
            'bajasArchivadas',
            'sinRegistrosDePartidos'
        ));
    }

    /**
     * Resolver edición de trabajo priorizando el contexto solicitado por el usuario.
     *
     * Mantiene compatibilidad: si no llega `idEdicion`, cae en la última edición.
     */
    private function resolverEdicionSeleccionada(?int $idEdicionSolicitada): ?Edicion
    {
        if ($idEdicionSolicitada) {
            return Edicion::find($idEdicionSolicitada) ?? Edicion::orderBy('id', 'desc')->first();
        }

        return Edicion::orderBy('id', 'desc')->first();
    }
}
