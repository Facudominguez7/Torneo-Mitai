<?php

namespace App\Services\Panel;

use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\ConsultaCobrosService;
use Illuminate\Support\Collection;

class EstadoAdministrativoPartidoService
{
    public function __construct(private readonly ConsultaCobrosService $cobros) {}

    /**
     * @param  Collection<int, Partido>  $partidos
     * @return Collection<int, array{estado: ?string, ausentes: array<int, int>}>
     */
    public function resolver(Collection $partidos): Collection
    {
        if ($partidos->isEmpty()) {
            return collect();
        }

        $ausencias = AusenciaCobroPlanilla::query()->vigentes()
            ->join('cargo_planillas', 'cargo_planillas.id', '=', 'ausencia_cobro_planillas.cargo_id')
            ->where('cargo_planillas.parent_type', Partido::class)
            ->whereIn('cargo_planillas.parent_id', $partidos->pluck('id'))
            ->get(['cargo_planillas.parent_id', 'cargo_planillas.idEquipo'])
            ->groupBy('parent_id');
        $cargos = $this->cobros->cargosDePartidos(CargoPlanilla::TIPO_PARTIDO, $partidos->pluck('id')->all());

        return $partidos->mapWithKeys(function (Partido $partido) use ($ausencias, $cargos): array {
            $equipos = [(int) $partido->idEquipoLocal, (int) $partido->idEquipoVisitante];
            $ausentes = ($ausencias->get($partido->id) ?? collect())->pluck('idEquipo')
                ->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => in_array($id, $equipos, true))->unique()->values()->all();
            $estado = null;

            if ($partido->ganador_por_no_vino !== null) {
                $estado = 'administrativo';
            } elseif (! $partido->resultado_no_vino_suprimido && count($ausentes) === 1
                && $partido->golesEquipoLocal === null && $partido->golesEquipoVisitante === null) {
                $rival = $equipos[0] === $ausentes[0] ? $equipos[1] : $equipos[0];
                $cargo = $cargos->get($partido->id.'-'.$rival);
                if ($cargo !== null && $cargo['deuda_ars'] > 0) {
                    $estado = 'pendiente_regularizacion';
                }
            }

            return [(int) $partido->id => ['estado' => $estado, 'ausentes' => $ausentes]];
        });
    }
}
