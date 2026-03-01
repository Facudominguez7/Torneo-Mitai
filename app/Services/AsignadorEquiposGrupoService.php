<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsignadorEquiposGrupoService
{
    /**
     * Asigna automáticamente equipos pendientes en grupos de una categoría.
     *
     * Reglas principales:
     * - Solo distribuye equipos que todavía no estén asignados.
     * - No modifica asignaciones existentes.
     * - Balancea por menor cantidad actual de equipos por grupo.
     * - Crea tabla de posiciones inicial si no existe para la combinación.
     *
     * @return array<string, mixed>
     */
    public function asignarPendientes(int $idEdicion, int $idCategoria): array
    {
        return DB::transaction(function () use ($idEdicion, $idCategoria): array {
            $grupos = Grupo::query()
                ->where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->orderBy('nombre')
                ->orderBy('id')
                ->get(['id', 'nombre']);

            if ($grupos->isEmpty()) {
                throw new DomainException('No hay grupos disponibles para esta categoría.');
            }

            $equiposCategoriaIds = EquipoEdicion::query()
                ->where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->pluck('idEquipo')
                ->unique()
                ->values();

            $totalEquipos = $equiposCategoriaIds->count();
            if ($totalEquipos === 0) {
                return $this->armarResultadoSinEquipos($grupos);
            }

            $idsGrupos = $grupos->pluck('id');

            $asignacionesExistentes = EquipoGrupo::query()
                ->whereIn('idGrupo', $idsGrupos)
                ->whereIn('idEquipo', $equiposCategoriaIds)
                ->get(['idGrupo', 'idEquipo']);

            $equiposYaAsignadosIds = $asignacionesExistentes
                ->pluck('idEquipo')
                ->unique()
                ->values();

            $equiposPendientesIds = $equiposCategoriaIds
                ->diff($equiposYaAsignadosIds)
                ->values();

            $conteoPorGrupo = $grupos->mapWithKeys(function (Grupo $grupo) use ($asignacionesExistentes): array {
                return [
                    $grupo->id => $asignacionesExistentes->where('idGrupo', $grupo->id)->count(),
                ];
            });

            $nuevosPorGrupo = $grupos->mapWithKeys(function (Grupo $grupo): array {
                return [$grupo->id => 0];
            });

            $equiposPendientes = Equipo::query()
                ->whereIn('id', $equiposPendientesIds)
                ->orderBy('nombre')
                ->orderBy('id')
                ->get(['id']);

            $asignadosNuevos = 0;

            foreach ($equiposPendientes as $equipoPendiente) {
                $grupoDestino = $this->obtenerGrupoConMenorCarga($grupos, $conteoPorGrupo);

                $asignacion = EquipoGrupo::firstOrCreate([
                    'idEquipo' => $equipoPendiente->id,
                    'idGrupo' => $grupoDestino->id,
                ]);

                TablaPosicion::firstOrCreate(
                    [
                        'idGrupo' => $grupoDestino->id,
                        'idEquipo' => $equipoPendiente->id,
                        'idEdicion' => $idEdicion,
                    ],
                    [
                        'golesFavor' => 0,
                        'golesContra' => 0,
                        'diferenciaGoles' => 0,
                        'jugado' => 0,
                        'ganado' => 0,
                        'empatado' => 0,
                        'perdido' => 0,
                        'puntos' => 0,
                    ]
                );

                if ($asignacion->wasRecentlyCreated) {
                    $conteoPorGrupo[$grupoDestino->id] = ((int) $conteoPorGrupo[$grupoDestino->id]) + 1;
                    $nuevosPorGrupo[$grupoDestino->id] = ((int) $nuevosPorGrupo[$grupoDestino->id]) + 1;
                    $asignadosNuevos++;
                }
            }

            $pendientesIniciales = $equiposPendientesIds->count();
            $pendientesRestantes = max(0, $pendientesIniciales - $asignadosNuevos);

            return [
                'total_equipos' => $totalEquipos,
                'ya_asignados' => $equiposYaAsignadosIds->count() + $asignadosNuevos,
                'pendientes_iniciales' => $pendientesIniciales,
                'pendientes' => $pendientesRestantes,
                'asignados' => $asignadosNuevos,
                'sin_pendientes' => $pendientesRestantes === 0,
                'grupos' => $grupos->map(function (Grupo $grupo) use ($conteoPorGrupo, $nuevosPorGrupo): array {
                    return [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'equipos_totales' => (int) ($conteoPorGrupo[$grupo->id] ?? 0),
                        'nuevos_asignados' => (int) ($nuevosPorGrupo[$grupo->id] ?? 0),
                    ];
                })->values()->all(),
            ];
        });
    }

    /**
     * Selecciona el grupo con menor carga para mantener balance.
     *
     * En empate, prioriza nombre y luego id para que el reparto sea
     * determinístico y predecible para el usuario.
     */
    private function obtenerGrupoConMenorCarga(Collection $grupos, Collection $conteoPorGrupo): Grupo
    {
        return $grupos->sort(function (Grupo $grupoA, Grupo $grupoB) use ($conteoPorGrupo): int {
            $cargaA = (int) ($conteoPorGrupo[$grupoA->id] ?? 0);
            $cargaB = (int) ($conteoPorGrupo[$grupoB->id] ?? 0);

            if ($cargaA !== $cargaB) {
                return $cargaA <=> $cargaB;
            }

            $comparacionPorNombre = strcmp((string) $grupoA->nombre, (string) $grupoB->nombre);
            if ($comparacionPorNombre !== 0) {
                return $comparacionPorNombre;
            }

            return $grupoA->id <=> $grupoB->id;
        })->firstOrFail();
    }

    /**
     * Estructura de respuesta para categorías sin equipos en edición.
     *
     * @return array<string, mixed>
     */
    private function armarResultadoSinEquipos(Collection $grupos): array
    {
        return [
            'total_equipos' => 0,
            'ya_asignados' => 0,
            'pendientes_iniciales' => 0,
            'pendientes' => 0,
            'asignados' => 0,
            'sin_pendientes' => true,
            'grupos' => $grupos->map(function (Grupo $grupo): array {
                return [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'equipos_totales' => 0,
                    'nuevos_asignados' => 0,
                ];
            })->values()->all(),
        ];
    }
}
