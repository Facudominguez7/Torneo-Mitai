<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Edicion;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Models\Partido;
use App\Models\InstanciaFinal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorConsultaJugador extends Controller
{
    /**
     * Mostrar el formulario de búsqueda
     */
    public function index(Request $request)
    {
        $edicionActual = Edicion::orderBy('id', 'desc')->first();
        $ediciones = Edicion::all(); // Para el layout admin.blade.php
        $EdicionSeleccionada = $edicionActual; // Para el selector de edición en el header
        
        // Mantener el DNI si viene en la URL
        $dni = $request->input('dni', '');
        
        return view('Panel.jugador.consulta', compact('edicionActual', 'ediciones', 'EdicionSeleccionada', 'dni'));
    }

    /**
     * Buscar jugador por DNI (siempre en la edición más reciente)
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'dni' => 'required|integer'
        ]);

        $dni = $request->dni;
        
        // Usar siempre la edición más reciente
        $edicionActual = Edicion::orderBy('id', 'desc')->first();
        $idEdicion = $edicionActual->id;

        // Buscar el jugador
        $jugador = Jugador::where('dni', $dni)->first();

        if (!$jugador) {
            return redirect()->route('jugador.consulta', ['dni' => $dni])->with('error', 'No se encontró ningún jugador con el DNI: ' . $dni);
        }

        // Obtener todas las planillas del jugador en la edición más reciente
        $planillas = PlanillaJugador::where('dni_jugador', $dni)
            ->where('idEdicion', $idEdicion)
            ->with(['equipo', 'categoria'])
            ->get();

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
            // Obtener el equipo y categoría actual (último registro)
            $planillaReciente = $planillas->sortByDesc('partido_id')->first();
            $equipoActual = \App\Models\Equipo::find($planillaReciente->idEquipo);
            $categoriaActual = \App\Models\Categoria::find($planillaReciente->idCategoria);

            // Calcular estadísticas totales en esta edición
            $totalGoles = $planillas->sum('goles');
            $totalPartidos = $planillas->where('asistio', 1)->count();
            $totalTarjetasRojas = $planillas->where('tarjeta_roja', 1)->count();

            // Obtener todos los partidos jugados con detalles
            $partidosJugados = $planillas->map(function ($planilla) {
                // Obtener el partido (puede ser Partido o InstanciaFinal)
                if ($planilla->partido_type === Partido::class) {
                    $partido = Partido::with(['equipoLocal', 'equipoVisitante', 'fecha'])
                        ->find($planilla->partido_id);
                    
                    if ($partido) {
                        return [
                            'tipo' => 'Partido Regular',
                            'fecha' => $partido->fecha->nombre ?? 'Fecha no disponible',
                            'equipoLocal' => $partido->equipoLocal->nombre,
                            'equipoVisitante' => $partido->equipoVisitante->nombre,
                            'golesLocal' => $partido->golesLocal,
                            'golesVisitante' => $partido->golesVisitante,
                            'goles_jugador' => $planilla->goles,
                            'asistio' => $planilla->asistio,
                            'tarjeta_roja' => $planilla->tarjeta_roja,
                            'numero_camiseta' => $planilla->numero_camiseta,
                        ];
                    }
                } else if ($planilla->partido_type === InstanciaFinal::class) {
                    $partido = InstanciaFinal::with(['equipoLocal', 'equipoVisitante', 'fase'])
                        ->find($planilla->partido_id);
                    
                    if ($partido) {
                        return [
                            'tipo' => 'Instancia Final',
                            'fecha' => $partido->fase->nombreFase ?? 'Fase no disponible',
                            'equipoLocal' => $partido->equipoLocal->nombre,
                            'equipoVisitante' => $partido->equipoVisitante->nombre,
                            'golesLocal' => $partido->golesLocal,
                            'golesVisitante' => $partido->golesVisitante,
                            'goles_jugador' => $planilla->goles,
                            'asistio' => $planilla->asistio,
                            'tarjeta_roja' => $planilla->tarjeta_roja,
                            'numero_camiseta' => $planilla->numero_camiseta,
                        ];
                    }
                }
                
                return null;
            })->filter(); // Eliminar nulos

            // Obtener los partidos donde hizo goles
            $partidosConGoles = $partidosJugados->filter(function ($partido) {
                return $partido['goles_jugador'] > 0;
            });
        }

        $ediciones = Edicion::all(); // Para el layout admin.blade.php
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
            'sinRegistrosDePartidos'
        ));
    }
}
