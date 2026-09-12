<?php

namespace App\Services;

use App\Models\Fecha;
use App\Models\Partido;
use Illuminate\Support\Collection;

class FixtureExploradorService
{
    /**
     * Resuelve la fecha relevante de una edición (y opcionalmente categoría):
     * la dueña del próximo partido no jugado con horario futuro; si no hay,
     * la del último partido jugado; si no hay partidos, la primera por id.
     */
    public function resolverFechaRelevante(int $idEdicion, ?int $idCategoria = null): ?int
    {
        $idProxima = Partido::query()
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->where(function ($query) {
                $query->where('jugado', 0)->orWhereNull('jugado');
            })
            ->whereNotNull('horario_datetime')
            ->where('horario_datetime', '>=', now())
            ->orderBy('horario_datetime')
            ->orderBy('idFechas')
            ->value('idFechas');

        if ($idProxima !== null) {
            return (int) $idProxima;
        }

        $idUltimaJugada = Partido::query()
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->where('jugado', 1)
            ->whereNotNull('horario_datetime')
            ->orderByDesc('horario_datetime')
            ->orderByDesc('idFechas')
            ->value('idFechas');

        if ($idUltimaJugada !== null) {
            return (int) $idUltimaJugada;
        }

        return Fecha::query()
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where(function ($sub) use ($idCategoria) {
                $sub->where('idCategoria', $idCategoria)->orWhereNull('idCategoria');
            }))
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Ordena fechas en orden calendario: por el primer horario de partido de
     * cada fecha (MIN asc). Las fechas sin partidos van al final por id y dos
     * fechas con el mismo primer horario quedan en orden determinista por id.
     */
    public function ordenarFechas(Collection $fechas, int $idEdicion, ?int $idCategoria = null): Collection
    {
        $primerosHorarios = Partido::query()
            ->where('idEdicion', $idEdicion)
            ->when($idCategoria !== null, fn ($query) => $query->where('idCategoria', $idCategoria))
            ->whereNotNull('horario_datetime')
            ->groupBy('idFechas')
            ->selectRaw('idFechas, MIN(horario_datetime) as primer_horario')
            ->pluck('primer_horario', 'idFechas');

        return $fechas->sort(fn ($una, $otra) => $this->compararPorCalendario($una, $otra, $primerosHorarios))
            ->values();
    }

    /**
     * @param  mixed  $una
     * @param  mixed  $otra
     */
    private function compararPorCalendario($una, $otra, Collection $primerosHorarios): int
    {
        $horarioUna = $primerosHorarios->get($una->id);
        $horarioOtra = $primerosHorarios->get($otra->id);

        if ($horarioUna === null || $horarioOtra === null) {
            if ($horarioUna === null && $horarioOtra === null) {
                return $una->id <=> $otra->id;
            }

            return $horarioUna === null ? 1 : -1;
        }

        $porHorario = strcmp((string) $horarioUna, (string) $horarioOtra);

        return $porHorario !== 0 ? $porHorario : $una->id <=> $otra->id;
    }
}
