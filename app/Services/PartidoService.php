<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaPosicion;
use Illuminate\Support\Facades\DB;

class PartidoService
{
    public function __construct(private readonly EstadisticasJugadorService $estadisticasJugadorService) {}

    public function actualizarResultado(Partido $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        $partido->loadMissing(['equipoLocal', 'equipoVisitante']);

        DB::transaction(function () use ($partido, $golesEquipoLocal, $golesEquipoVisitante) {
            // Bloquear el partido para evitar condiciones de carrera
            $partido = Partido::where('id', $partido->id)->lockForUpdate()->first();

            $resultadoPrevioDisponible = $partido->jugado && $partido->golesEquipoLocal !== null && $partido->golesEquipoVisitante !== null;

            if ($resultadoPrevioDisponible) {
                $this->revertirTablaPosiciones($partido, $partido->golesEquipoLocal, $partido->golesEquipoVisitante);
                $this->revertirHistorialEquipos($partido, $partido->golesEquipoLocal, $partido->golesEquipoVisitante);
            }

            $partido->update([
                'golesEquipoLocal' => $golesEquipoLocal,
                'golesEquipoVisitante' => $golesEquipoVisitante,
                'jugado' => 1,
            ]);

            $this->actualizarTablaPosiciones($partido, $golesEquipoLocal, $golesEquipoVisitante);
            $this->actualizarHistorialEquipos($partido, $golesEquipoLocal, $golesEquipoVisitante);
        });
    }

    protected function actualizarTablaPosiciones(Partido $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        // Lógica para actualizar la tabla de posiciones
        $equipoLocal = $partido->equipoLocal;
        $equipoVisitante = $partido->equipoVisitante;

        // Para partidos interzonales, obtener el grupo real de cada equipo
        if ($partido->esInterzonal()) {
            $grupoLocal = $this->obtenerGrupoEquipo($equipoLocal->id, $partido->idCategoria, $partido->idEdicion);
            $grupoVisitante = $this->obtenerGrupoEquipo($equipoVisitante->id, $partido->idCategoria, $partido->idEdicion);
            $idGrupoLocal = $grupoLocal ? $grupoLocal->id : $partido->idGrupo;
            $idGrupoVisitante = $grupoVisitante ? $grupoVisitante->id : $partido->idGrupo;
        } else {
            $idGrupoLocal = $partido->idGrupo;
            $idGrupoVisitante = $partido->idGrupo;
        }

        // Verificar si la combinación ya existe para el equipo local (con bloqueo)
        $tablaLocal = TablaPosicion::where([
            ['idGrupo', '=', $idGrupoLocal],
            ['idEquipo', '=', $equipoLocal->id],
            ['idEdicion', '=', $partido->idEdicion],
        ])->lockForUpdate()->first();

        if (! $tablaLocal) {
            // Si no existe, se crea un nuevo registro
            $tablaLocal = TablaPosicion::create([
                'idGrupo' => $idGrupoLocal,
                'idEquipo' => $equipoLocal->id,
                'idEdicion' => $partido->idEdicion,
                'golesFavor' => 0,
                'golesContra' => 0,
                'diferenciaGoles' => 0,
                'jugado' => 0,
                'ganado' => 0,
                'empatado' => 0,
                'perdido' => 0,
                'puntos' => 0,
            ]);
        }

        // Verificar si la combinación ya existe para el equipo visitante (con bloqueo)
        $tablaVisitante = TablaPosicion::where([
            ['idGrupo', '=', $idGrupoVisitante],
            ['idEquipo', '=', $equipoVisitante->id],
            ['idEdicion', '=', $partido->idEdicion],
        ])->lockForUpdate()->first();

        if (! $tablaVisitante) {
            // Si no existe, se crea un nuevo registro
            $tablaVisitante = TablaPosicion::create([
                'idGrupo' => $idGrupoVisitante,
                'idEquipo' => $equipoVisitante->id,
                'idEdicion' => $partido->idEdicion,
                'golesFavor' => 0,
                'golesContra' => 0,
                'diferenciaGoles' => 0,
                'jugado' => 0,
                'ganado' => 0,
                'empatado' => 0,
                'perdido' => 0,
                'puntos' => 0,
            ]);
        }

        // Actualizar estadísticas de cada equipo
        $this->actualizarEstadisticasEquipo($tablaLocal, $golesEquipoLocal, $golesEquipoVisitante);
        $this->actualizarEstadisticasEquipo($tablaVisitante, $golesEquipoVisitante, $golesEquipoLocal);
    }

    protected function revertirTablaPosiciones(Partido $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        // Para partidos interzonales, obtener el grupo real de cada equipo
        if ($partido->esInterzonal()) {
            $grupoLocal = $this->obtenerGrupoEquipo($partido->idEquipoLocal, $partido->idCategoria, $partido->idEdicion);
            $grupoVisitante = $this->obtenerGrupoEquipo($partido->idEquipoVisitante, $partido->idCategoria, $partido->idEdicion);
            $idGrupoLocal = $grupoLocal ? $grupoLocal->id : $partido->idGrupo;
            $idGrupoVisitante = $grupoVisitante ? $grupoVisitante->id : $partido->idGrupo;
        } else {
            $idGrupoLocal = $partido->idGrupo;
            $idGrupoVisitante = $partido->idGrupo;
        }

        $tablaLocal = TablaPosicion::where([
            ['idGrupo', '=', $idGrupoLocal],
            ['idEquipo', '=', $partido->idEquipoLocal],
            ['idEdicion', '=', $partido->idEdicion],
        ])->lockForUpdate()->first();

        if ($tablaLocal) {
            $this->actualizarEstadisticasEquipo($tablaLocal, $golesEquipoLocal, $golesEquipoVisitante, -1);
        }

        $tablaVisitante = TablaPosicion::where([
            ['idGrupo', '=', $idGrupoVisitante],
            ['idEquipo', '=', $partido->idEquipoVisitante],
            ['idEdicion', '=', $partido->idEdicion],
        ])->lockForUpdate()->first();

        if ($tablaVisitante) {
            $this->actualizarEstadisticasEquipo($tablaVisitante, $golesEquipoVisitante, $golesEquipoLocal, -1);
        }
    }

    /**
     * Obtener el grupo al que pertenece un equipo en una categoría y edición
     */
    protected function obtenerGrupoEquipo($equipoId, $categoriaId, $edicionId)
    {
        return Grupo::where('idCategoria', $categoriaId)
            ->where('idEdicion', $edicionId)
            ->whereHas('equipos', function ($q) use ($equipoId) {
                $q->where('equipos.id', $equipoId);
            })
            ->first();
    }

    protected function actualizarEstadisticasEquipo($tablaPosicion, $golesAFavor, $golesEnContra, $factor = 1)
    {
        $tablaPosicion->golesFavor += $factor * $golesAFavor;
        $tablaPosicion->golesContra += $factor * $golesEnContra;
        $tablaPosicion->diferenciaGoles = $tablaPosicion->golesFavor - $tablaPosicion->golesContra;
        $tablaPosicion->jugado += $factor;

        if ($golesAFavor > $golesEnContra) {
            $tablaPosicion->ganado += $factor;
            $tablaPosicion->puntos += 3 * $factor;
        } elseif ($golesAFavor == $golesEnContra) {
            $tablaPosicion->empatado += $factor;
            $tablaPosicion->puntos += 1 * $factor;
        } else {
            $tablaPosicion->perdido += $factor;
        }

        $tablaPosicion->jugado = max(0, $tablaPosicion->jugado);
        $tablaPosicion->ganado = max(0, $tablaPosicion->ganado);
        $tablaPosicion->empatado = max(0, $tablaPosicion->empatado);
        $tablaPosicion->perdido = max(0, $tablaPosicion->perdido);
        $tablaPosicion->puntos = max(0, $tablaPosicion->puntos);
        $tablaPosicion->golesFavor = max(0, $tablaPosicion->golesFavor);
        $tablaPosicion->golesContra = max(0, $tablaPosicion->golesContra);
        $tablaPosicion->diferenciaGoles = $tablaPosicion->golesFavor - $tablaPosicion->golesContra;

        $tablaPosicion->save();
    }

    public function actualizarHistorialEquipos(Partido $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        $equipoLocal = $partido->equipoLocal;
        $equipoVisitante = $partido->equipoVisitante;

        $this->ajustarHistorialEquipo($equipoLocal, $golesEquipoLocal, $golesEquipoVisitante, 1);
        $this->ajustarHistorialEquipo($equipoVisitante, $golesEquipoVisitante, $golesEquipoLocal, 1);
    }

    protected function revertirHistorialEquipos(Partido $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        $equipoLocal = $partido->equipoLocal;
        $equipoVisitante = $partido->equipoVisitante;

        $this->ajustarHistorialEquipo($equipoLocal, $golesEquipoLocal, $golesEquipoVisitante, -1);
        $this->ajustarHistorialEquipo($equipoVisitante, $golesEquipoVisitante, $golesEquipoLocal, -1);
    }

    protected function ajustarHistorialEquipo(Equipo $equipo, int $golesAFavor, int $golesEnContra, int $factor)
    {
        // Recargar equipo con bloqueo para evitar condiciones de carrera
        $equipo = Equipo::where('id', $equipo->id)->lockForUpdate()->first();

        $equipo->partidos_jugados += $factor;

        if ($golesAFavor > $golesEnContra) {
            $equipo->victorias += $factor;
            $equipo->puntos += 3 * $factor;
        } elseif ($golesAFavor == $golesEnContra) {
            $equipo->empates += $factor;
            $equipo->puntos += 1 * $factor;
        } else {
            $equipo->derrotas += $factor;
        }

        $equipo->golesFavor += $factor * $golesAFavor;
        $equipo->golesContra += $factor * $golesEnContra;
        $equipo->diferenciaGoles = $equipo->golesFavor - $equipo->golesContra;

        $equipo->partidos_jugados = max(0, $equipo->partidos_jugados);
        $equipo->victorias = max(0, $equipo->victorias);
        $equipo->empates = max(0, $equipo->empates);
        $equipo->derrotas = max(0, $equipo->derrotas);
        $equipo->golesFavor = max(0, $equipo->golesFavor);
        $equipo->golesContra = max(0, $equipo->golesContra);
        $equipo->puntos = max(0, $equipo->puntos);
        $equipo->diferenciaGoles = $equipo->golesFavor - $equipo->golesContra;

        $equipo->save();
    }

    /**
     * Motivos por los que no se pueden cambiar los equipos de un partido regular.
     *
     * @return list<string>
     */
    public function razonesBloqueoCambioEquipos(Partido $partido): array
    {
        $razones = [];

        if ($partido->jugado || $partido->golesEquipoLocal !== null || $partido->golesEquipoVisitante !== null) {
            $razones[] = 'el partido ya tiene resultado cargado';
        }

        if ($partido->goles_confirmados_at !== null) {
            $razones[] = 'el partido ya tiene goles confirmados';
        }

        $tienePlanillas = PlanillaJugador::query()
            ->where('partido_type', Partido::class)
            ->where('partido_id', $partido->id)
            ->exists();

        if ($tienePlanillas) {
            $razones[] = 'el partido ya tiene planillas cargadas';
        }

        return $razones;
    }

    /**
     * Elimina un partido regular revirtiendo en una misma transacción:
     * tabla de posiciones, historial de equipos, estadísticas de jugadores
     * y goleadores (por delta, preservando datos manuales), reportes
     * arbitrales y planillas; recalcula golesContra de equipo_ediciones.
     */
    public function eliminarPartido(Partido $partido): void
    {
        DB::transaction(function () use ($partido): void {
            $partido = Partido::query()
                ->whereKey($partido->id)
                ->lockForUpdate()
                ->firstOrFail();

            $resultadoAplicado = (bool) $partido->jugado
                && $partido->golesEquipoLocal !== null
                && $partido->golesEquipoVisitante !== null;

            if ($resultadoAplicado) {
                $this->revertirTablaPosiciones($partido, (int) $partido->golesEquipoLocal, (int) $partido->golesEquipoVisitante);
                $this->revertirHistorialEquipos($partido, (int) $partido->golesEquipoLocal, (int) $partido->golesEquipoVisitante);
            }

            $planillas = PlanillaJugador::query()
                ->where('partido_type', Partido::class)
                ->where('partido_id', $partido->id)
                ->lockForUpdate()
                ->get();

            foreach ($planillas as $planilla) {
                $this->estadisticasJugadorService->actualizarDeltaGoles(
                    (string) $planilla->dni_jugador,
                    (int) $planilla->idEdicion,
                    (int) $planilla->idCategoria,
                    (int) $planilla->idEquipo,
                    -1 * (int) $planilla->goles,
                );
            }

            GolArbitroReportado::query()
                ->where('partido_type', Partido::class)
                ->where('partido_id', $partido->id)
                ->delete();

            PlanillaJugador::query()
                ->where('partido_type', Partido::class)
                ->where('partido_id', $partido->id)
                ->delete();

            $partido->delete();

            foreach ([$partido->idEquipoLocal, $partido->idEquipoVisitante] as $idEquipo) {
                $existeEquipoEdicion = EquipoEdicion::query()
                    ->where('idEquipo', $idEquipo)
                    ->where('idEdicion', $partido->idEdicion)
                    ->exists();

                if ($existeEquipoEdicion) {
                    $idGrupoEquipo = $partido->idGrupo;

                    if ($partido->esInterzonal()) {
                        $grupoEquipo = $this->obtenerGrupoEquipo($idEquipo, $partido->idCategoria, $partido->idEdicion);
                        $idGrupoEquipo = $grupoEquipo ? $grupoEquipo->id : $partido->idGrupo;
                    }

                    $this->actualizarGolesContraEquipoEdicion($idEquipo, $partido->idEdicion, $idGrupoEquipo);
                }
            }
        }, 3);
    }

    /**
     * Actualizar historial de equipos para instancias finales
     * Ahora usa la misma lógica unificada que partidos normales
     */
    public function actualizarHistorialInstanciasFinales(InstanciaFinal $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        $equipoLocal = $partido->equipoLocal;
        $equipoVisitante = $partido->equipoVisitante;

        // Usar el mismo método unificado que partidos normales
        $this->ajustarHistorialEquipo($equipoLocal, $golesEquipoLocal, $golesEquipoVisitante, 1);
        $this->ajustarHistorialEquipo($equipoVisitante, $golesEquipoVisitante, $golesEquipoLocal, 1);
    }

    /**
     * Revertir historial de equipos para instancias finales
     * Método faltante para mantener consistencia con partidos normales
     */
    public function revertirHistorialInstanciasFinales(InstanciaFinal $partido, $golesEquipoLocal, $golesEquipoVisitante)
    {
        $equipoLocal = $partido->equipoLocal;
        $equipoVisitante = $partido->equipoVisitante;

        // Usar el mismo método unificado con factor negativo
        $this->ajustarHistorialEquipo($equipoLocal, $golesEquipoLocal, $golesEquipoVisitante, -1);
        $this->ajustarHistorialEquipo($equipoVisitante, $golesEquipoVisitante, $golesEquipoLocal, -1);
    }

    public function actualizarGolesContraEquipoEdicion($equipoId, $idEdicion, $idGrupo)
    {
        // Obtener la instancia de equipo_edicion correspondiente
        $equipoEdicion = EquipoEdicion::where('idEquipo', $equipoId)
            ->where('idEdicion', $idEdicion)
            ->first();

        if (! $equipoEdicion) {
            // Manejar el caso en que no se encuentre el equipo_edicion
            throw new \Exception("EquipoEdicion no encontrado para el equipo $equipoId en la edición $idEdicion");
        }

        // Obtener goles en contra de la tabla de posiciones
        $originalIdEdicion = $idEdicion;
        if (in_array($idEdicion, [1, 2, 3])) {
            $idEdicion = 0;
        }

        $golesContraFaseGruposQuery = TablaPosicion::where('idEquipo', $equipoId)
            ->where('idEdicion', $idEdicion);

        if ($idGrupo != 0) {
            $golesContraFaseGruposQuery->where('idGrupo', $idGrupo);
        }

        $golesContraFaseGrupos = $golesContraFaseGruposQuery->sum('golesContra');

        // Restaurar el valor original de idEdicion
        $idEdicion = $originalIdEdicion;

        // Obtener goles en contra de las instancias finales
        $partidosInstanciasFinales = InstanciaFinal::where('idEdicion', $idEdicion)
            ->where(function ($query) use ($equipoId) {
                $query->where('idEquipoLocal', $equipoId)
                    ->orWhere('idEquipoVisitante', $equipoId);
            })
            ->get();

        $golesContraInstanciasFinales = 0;
        if ($partidosInstanciasFinales->isNotEmpty()) {
            $golesContraInstanciasFinales = $partidosInstanciasFinales->sum(function ($partido) use ($equipoId) {
                return $partido->idEquipoLocal == $equipoId ? $partido->golesEquipoVisitante : $partido->golesEquipoLocal;
            });
        }

        // Sumar ambos valores y actualizar en equipo_ediciones
        $equipoEdicion->golesContra = $golesContraFaseGrupos + $golesContraInstanciasFinales;
        $equipoEdicion->save();
    }
}
