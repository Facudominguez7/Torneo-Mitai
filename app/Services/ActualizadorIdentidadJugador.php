<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ActualizadorIdentidadJugador
{
    /**
     * Crea o sincroniza los datos basicos del jugador usando el DNI como identidad.
     */
    public function sincronizarJugador(string $dni, string $nombre, string $apellido): Jugador
    {
        return Jugador::updateOrCreate(
            ['dni' => trim($dni)],
            [
                'nombre' => trim($nombre),
                'apellido' => trim($apellido),
            ]
        );
    }

    /**
     * Corrige la identidad del jugador cuando cambia el DNI.
     *
     * La operacion migra todas las referencias operativas al DNI final,
     * resguardando los casos ambiguos para no mezclar jugadores distintos.
     * Debe ejecutarse dentro de una transaccion externa.
     */
    public function actualizarIdentidad(string $dniActual, string $nuevoDni, string $nombre, string $apellido): Jugador
    {
        $dniActual = trim($dniActual);
        $nuevoDni = trim($nuevoDni);
        $nombre = trim($nombre);
        $apellido = trim($apellido);

        if ($dniActual === $nuevoDni) {
            return $this->sincronizarJugador($nuevoDni, $nombre, $apellido);
        }

        $jugadorActual = Jugador::where('dni', $dniActual)->first();
        $jugadorDestino = Jugador::where('dni', $nuevoDni)->first();

        if ($jugadorDestino) {
            $this->asegurarCompatibilidadDeIdentidad($jugadorActual, $jugadorDestino, $nombre, $apellido);
        }

        $this->asegurarSinConflictosDeMigracion($dniActual, $nuevoDni);

        if (! $jugadorDestino) {
            $jugadorDestino = Jugador::create([
                'dni' => $nuevoDni,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'goles_totales' => 0,
                'partidos_totales' => 0,
                'tarjetas_rojas_totales' => 0,
            ]);
        } else {
            $jugadorDestino->nombre = $nombre;
            $jugadorDestino->apellido = $apellido;
            $jugadorDestino->save();
        }

        $this->migrarRegistrosListaBuenaFe($dniActual, $nuevoDni);
        $this->migrarRegistrosPlanilla($dniActual, $nuevoDni);
        $this->migrarRegistrosTablaGoleadores($dniActual, $nuevoDni);
        $this->recalcularEstadisticas($jugadorDestino);
        $this->eliminarJugadorAnteriorSiCorresponde($dniActual, $nuevoDni);

        return $jugadorDestino->fresh();
    }

    private function asegurarCompatibilidadDeIdentidad(?Jugador $jugadorActual, Jugador $jugadorDestino, string $nombre, string $apellido): void
    {
        $referenciasCompatibles = array_filter([
            $this->normalizarNombre($apellido.' '.$nombre),
            $jugadorActual ? $this->normalizarNombre($jugadorActual->apellido.' '.$jugadorActual->nombre) : null,
        ]);

        $nombreDestino = $this->normalizarNombre($jugadorDestino->apellido.' '.$jugadorDestino->nombre);

        if (! in_array($nombreDestino, $referenciasCompatibles, true)) {
            throw new RuntimeException('El DNI destino ya pertenece a otro jugador y la corrección sería ambigua. Revise nombre, apellido y DNI antes de continuar.');
        }
    }

    private function asegurarSinConflictosDeMigracion(string $dniActual, string $nuevoDni): void
    {
        $conflictoEnListaBuenaFe = DB::table('lista_buena_fe as origen')
            ->join('lista_buena_fe as destino', function ($join) {
                $join->on('destino.idEquipo', '=', 'origen.idEquipo')
                    ->on('destino.idEdicion', '=', 'origen.idEdicion');
            })
            ->where('origen.dni_jugador', $dniActual)
            ->where('destino.dni_jugador', $nuevoDni)
            ->exists();

        if ($conflictoEnListaBuenaFe) {
            throw new RuntimeException('Ya existe un registro en Lista de Buena Fe para el DNI destino dentro del mismo equipo y edición. Corrija ese caso antes de cambiar el DNI.');
        }

        $conflictoEnPlanilla = DB::table('planilla_jugadores as origen')
            ->join('planilla_jugadores as destino', function ($join) {
                $join->on('destino.partido_id', '=', 'origen.partido_id')
                    ->on('destino.partido_type', '=', 'origen.partido_type');
            })
            ->where('origen.dni_jugador', $dniActual)
            ->where('destino.dni_jugador', $nuevoDni)
            ->exists();

        if ($conflictoEnPlanilla) {
            throw new RuntimeException('El DNI destino ya tiene registros en una o más planillas de los mismos partidos. La corrección debe resolverse manualmente para no duplicar al jugador.');
        }
    }

    private function migrarRegistrosListaBuenaFe(string $dniActual, string $nuevoDni): void
    {
        ListaBuenaFe::where('dni_jugador', $dniActual)->update([
            'dni_jugador' => $nuevoDni,
        ]);
    }

    private function migrarRegistrosPlanilla(string $dniActual, string $nuevoDni): void
    {
        PlanillaJugador::where('dni_jugador', $dniActual)->update([
            'dni_jugador' => $nuevoDni,
        ]);
    }

    private function migrarRegistrosTablaGoleadores(string $dniActual, string $nuevoDni): void
    {
        $registrosOrigen = TablaGoleador::where('dni_jugador', $dniActual)->get();

        foreach ($registrosOrigen as $registroOrigen) {
            $registroDestino = TablaGoleador::where('dni_jugador', $nuevoDni)
                ->where('idEquipo', $registroOrigen->idEquipo)
                ->where('idEdicion', $registroOrigen->idEdicion)
                ->where('idCategoria', $registroOrigen->idCategoria)
                ->first();

            if ($registroDestino) {
                $registroDestino->cantidadGoles += $registroOrigen->cantidadGoles;
                $registroDestino->save();
                $registroOrigen->delete();

                continue;
            }

            $registroOrigen->dni_jugador = $nuevoDni;
            $registroOrigen->save();
        }
    }

    private function recalcularEstadisticas(Jugador $jugador): void
    {
        $estadisticas = PlanillaJugador::where('dni_jugador', $jugador->dni)
            ->selectRaw('COALESCE(SUM(goles), 0) as goles_totales')
            ->selectRaw('COALESCE(SUM(CASE WHEN asistio = 1 THEN 1 ELSE 0 END), 0) as partidos_totales')
            ->selectRaw('COALESCE(SUM(CASE WHEN tarjeta_roja = 1 THEN 1 ELSE 0 END), 0) as tarjetas_rojas_totales')
            ->first();

        $jugador->nombre = trim($jugador->nombre);
        $jugador->apellido = trim($jugador->apellido);
        $jugador->goles_totales = (int) ($estadisticas->goles_totales ?? 0);
        $jugador->partidos_totales = (int) ($estadisticas->partidos_totales ?? 0);
        $jugador->tarjetas_rojas_totales = (int) ($estadisticas->tarjetas_rojas_totales ?? 0);
        $jugador->save();
    }

    private function eliminarJugadorAnteriorSiCorresponde(string $dniActual, string $nuevoDni): void
    {
        if ($dniActual === $nuevoDni) {
            return;
        }

        $jugadorAnterior = Jugador::where('dni', $dniActual)->first();

        if (! $jugadorAnterior) {
            return;
        }

        $tieneReferencias = ListaBuenaFe::where('dni_jugador', $dniActual)->exists()
            || PlanillaJugador::where('dni_jugador', $dniActual)->exists()
            || TablaGoleador::where('dni_jugador', $dniActual)->exists();

        if (! $tieneReferencias) {
            $jugadorAnterior->delete();
        }
    }

    private function normalizarNombre(string $valor): string
    {
        $valorNormalizado = Str::ascii($valor);
        $valorNormalizado = preg_replace('/[^A-Za-z0-9\s]/', ' ', $valorNormalizado) ?? $valorNormalizado;
        $valorNormalizado = preg_replace('/\s+/', ' ', trim($valorNormalizado)) ?? trim($valorNormalizado);

        return Str::upper($valorNormalizado);
    }
}
