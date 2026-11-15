<?php

namespace App\Services\Panel;

use App\Models\InstanciaFinal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Instancias finales que muestra el dashboard: las del último día con
 * finales programadas de la edición, el equivalente a "la última fecha" de la
 * fase regular.
 */
class InstanciasFinalesDashboard
{
    /**
     * @return Collection<int, InstanciaFinal>
     */
    public function delUltimoDia(int $idEdicion, ?string $horario = null): Collection
    {
        $ultimoDia = $this->ultimoDia($idEdicion);

        if (! $ultimoDia) {
            return collect();
        }

        return InstanciaFinal::query()
            ->select(
                'instancias_finales.*',
                'el.nombre as nombre_local',
                'ev.nombre as nombre_visitante',
                'el.foto as foto_local',
                'ev.foto as foto_visitante',
                'fases.nombre as nombre_fase',
                'categorias.nombreCategoria as nombre_categoria',
                DB::raw("COALESCE(copas.nombre, '') as nombre_copa")
            )
            ->join('equipos as el', 'instancias_finales.idEquipoLocal', '=', 'el.id')
            ->join('equipos as ev', 'instancias_finales.idEquipoVisitante', '=', 'ev.id')
            ->join('fases', 'instancias_finales.idFase', '=', 'fases.id')
            ->join('categorias', 'instancias_finales.idCategoria', '=', 'categorias.id')
            ->leftJoin('copas', 'instancias_finales.idCopa', '=', 'copas.id')
            ->where('instancias_finales.idEdicion', $idEdicion)
            ->whereRaw('DATE(instancias_finales.horario) = ?', [$ultimoDia])
            ->when($horario, fn ($query) => $query->whereRaw('TIME(instancias_finales.horario) = ?', [$horario.':00']))
            ->orderBy('instancias_finales.horario')
            ->orderByDesc('categorias.nombreCategoria')
            ->orderBy('copas.secuencia')
            ->orderBy('fases.secuencia')
            ->get();
    }

    /**
     * Último día (Y-m-d) con instancias finales programadas en la edición.
     */
    public function ultimoDia(int $idEdicion): ?string
    {
        $ultimoDia = InstanciaFinal::query()
            ->where('idEdicion', $idEdicion)
            ->whereNotNull('horario')
            ->max(DB::raw('DATE(horario)'));

        return $ultimoDia ? (string) $ultimoDia : null;
    }

    /**
     * Horarios (HH:mm) del último día con finales, para el filtro del dashboard.
     *
     * @return Collection<int, string>
     */
    public function horarios(int $idEdicion): Collection
    {
        return $this->delUltimoDia($idEdicion)
            ->map(fn (InstanciaFinal $final): string => Carbon::parse($final->horario)->format('H:i'))
            ->unique()
            ->values();
    }
}
