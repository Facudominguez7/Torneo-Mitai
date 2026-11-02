<?php

namespace App\Services\Cobros;

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\Fecha;
use App\Models\PagoPlanilla;
use App\Models\PoliticaExencionCobroPlanilla;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class ConsultaCobrosService
{
    /**
     * Los ingresos se consultan por recepción; las aplicaciones por cargo.
     * Nunca se suman pagos a través de un join con sus aplicaciones.
     */
    public function consultar(array $filtros): array
    {
        $edicion = $filtros['idEdicion'] ?? null;
        $cargos = CargoPlanilla::query()->where('idEdicion', $edicion)
            ->with(['parent', 'equipo', 'categoria'])->orderBy('id')->get();
        $filas = $this->filas($cargos)->filter(fn (array $fila): bool => $this->coincide($fila, $filtros))->values();

        $cuentas = CuentaCobroPlanilla::query()->where('idEdicion', $edicion)->pluck('id');
        $pagos = PagoPlanilla::query()->whereIn('cuenta_id', $cuentas)->get();
        $usoPagos = AplicacionPagoPlanilla::query()->whereIn('pago_id', $pagos->modelKeys())
            ->selectRaw('pago_id, SUM(importe_ars) as total')->groupBy('pago_id')->pluck('total', 'pago_id');
        $credito = $pagos->sum(function (PagoPlanilla $pago) use ($usoPagos): int {
            $saldo = $pago->importe_ars - (int) ($usoPagos[$pago->id] ?? 0);
            if ($saldo < 0) {
                throw new RuntimeException('Un pago registra aplicaciones superiores a su importe.');
            }

            return $saldo;
        });
        $recibidos = $pagos->filter(fn (PagoPlanilla $pago): bool => (! isset($filtros['recibido_desde']) || $pago->recibido_at->toDateString() >= $filtros['recibido_desde'])
            && (! isset($filtros['recibido_hasta']) || $pago->recibido_at->toDateString() <= $filtros['recibido_hasta']));

        return [
            'cargos' => $filas,
            'configuracion' => ConfiguracionCobroPlanilla::query()->find(1),
            'resumen' => [
                'deuda_ars' => (int) $filas->sum('deuda_ars'),
                'deudores' => $filas->where('deuda_ars', '>', 0)->unique('idEquipo')->count(),
                'eximido_ars' => (int) $filas->sum('eximido_ars'),
                'exenciones' => $filas->where('eximido_ars', '>', 0)->count(),
                'recaudacion_asociada_ars' => (int) $filas->sum('aplicado_ars'),
                'ingresos_ars' => (int) $recibidos->sum('importe_ars'), 'credito_ars' => (int) $credito,
                'ingresos_por_medio' => $recibidos->groupBy('medio')->map(fn (Collection $grupo): int => (int) $grupo->sum('importe_ars'))->all(),
            ],
        ];
    }

    /**
     * Estado de cobro de los cargos de los partidos indicados, indexado por "partido-equipo".
     *
     * @param  array<int, int>  $partidos
     * @return Collection<string, array<string, mixed>>
     */
    public function cargosDePartidos(string $tipo, array $partidos): Collection
    {
        if ($partidos === []) {
            return collect();
        }

        $cargos = CargoPlanilla::query()->where('tipo', $tipo)->whereIn('parent_id', $partidos)
            ->with(['parent', 'equipo', 'categoria'])->orderBy('id')->get();

        return $this->filas($cargos)->keyBy(fn (array $fila): string => $fila['partido'].'-'.$fila['idEquipo']);
    }

    /**
     * Exención y deuda pendiente de cada equipo en la edición, indexadas por idEquipo.
     *
     * @param  array<int, int>  $equipos
     * @return Collection<int, array{exento: bool, deuda_ars: int}>
     */
    public function estadoEquipos(int $edicionId, array $equipos): Collection
    {
        if ($equipos === []) {
            return collect();
        }

        $cargos = CargoPlanilla::query()->where('idEdicion', $edicionId)->whereIn('idEquipo', $equipos)
            ->with(['parent', 'equipo', 'categoria'])->orderBy('id')->get();
        $deudas = $this->filas($cargos)->groupBy('idEquipo')->map(fn (Collection $filas): int => (int) $filas->sum('deuda_ars'));
        $exentos = PoliticaExencionCobroPlanilla::query()->where('idEdicion', $edicionId)->whereIn('idEquipo', $equipos)->pluck('idEquipo')->flip();

        return collect($equipos)->mapWithKeys(fn (int $equipo): array => [$equipo => [
            'exento' => $exentos->has($equipo), 'deuda_ars' => (int) ($deudas[$equipo] ?? 0),
        ]]);
    }

    /**
     * Deuda de cada equipo por los partidos jugados hasta hoy, de mayor a menor; `pago` es la planilla adeudada más antigua, que se cobra desde la tarjeta.
     *
     * @return Collection<int, array{idEquipo: int, equipo: string, categoria: string, deuda_ars: int, cargos: int, pago: array<string, mixed>, planillas: array<int, array{detalle: string, dia: ?string, deuda_ars: int}>}>
     */
    public function deudoresPorEquipo(int $edicionId): Collection
    {
        $cargos = $this->consultar(['idEdicion' => $edicionId, 'estado' => 'deudores', 'periodo' => 'hasta_hoy'])['cargos'];
        $nombresFecha = Fecha::query()->whereIn('id', $cargos->pluck('idFecha')->filter()->unique())->pluck('nombre', 'id');

        return $cargos
            ->groupBy('idEquipo')
            ->map(function (Collection $filas) use ($nombresFecha): array {
                $fila = $filas->first();
                $ordenadas = $filas->sortBy('fecha');

                return [
                    'idEquipo' => (int) $fila['idEquipo'], 'equipo' => $fila['nombre_vigente'] ?? $fila['equipo'],
                    'categoria' => $fila['categoria'], 'deuda_ars' => (int) $filas->sum('deuda_ars'), 'cargos' => $filas->count(),
                    'pago' => $ordenadas->first(),
                    'planillas' => $ordenadas->map(fn (array $cargo): array => [
                        'detalle' => $this->detallePlanilla($cargo, $nombresFecha[$cargo['idFecha']] ?? null),
                        'dia' => $cargo['fecha'] ? Carbon::parse($cargo['fecha'])->format('d/m') : null,
                        'deuda_ars' => (int) $cargo['deuda_ars'],
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('deuda_ars')
            ->values();
    }

    /**
     * Cuenta de planillas de un equipo por los partidos jugados hasta hoy: lo que debe y lo que tiene al día.
     *
     * @return array{debe: Collection<int, array<string, mixed>>, al_dia: Collection<int, array<string, mixed>>, saldo_ars: int, equipo: ?string, categoria: ?string}
     */
    public function informeEquipo(int $edicionId, int $equipoId): array
    {
        $filas = $this->consultar(['idEdicion' => $edicionId, 'idEquipo' => $equipoId, 'estado' => 'todos', 'periodo' => 'hasta_hoy'])['cargos'];
        $nombresFecha = Fecha::query()->whereIn('id', $filas->pluck('idFecha')->filter()->unique())->pluck('nombre', 'id');
        $canchas = CargoPlanilla::query()->whereKey($filas->pluck('id'))->with('parent')->get()
            ->mapWithKeys(fn (CargoPlanilla $cargo): array => [$cargo->id => $cargo->parent?->cancha]);
        $planillas = $filas->sortBy('fecha')->map(fn (array $fila): array => $fila + [
            'detalle' => $this->detallePlanilla($fila, $nombresFecha[$fila['idFecha']] ?? null),
            'dia' => $this->diaPlanilla($fila, $canchas[$fila['id']] ?? null),
        ])->values();
        $primera = $filas->first();

        return [
            'debe' => $planillas->where('deuda_ars', '>', 0)->values(),
            'al_dia' => $planillas->where('deuda_ars', 0)->values(),
            'saldo_ars' => (int) $planillas->sum('deuda_ars'),
            'equipo' => $primera ? ($primera['nombre_vigente'] ?? $primera['equipo']) : null,
            'categoria' => $primera['categoria'] ?? null,
        ];
    }

    /** Día del partido abreviado ("sáb 05/10 · 10:30 · Cancha 1"); la deuda manual solo lleva su día. */
    private function diaPlanilla(array $fila, mixed $cancha): ?string
    {
        if ($fila['fecha'] === null) {
            return null;
        }
        $dia = Carbon::parse($fila['fecha']);
        $partes = [['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][$dia->dayOfWeek].' '.$dia->format('d/m')];
        if (! $fila['sin_partido']) {
            $partes[] = $dia->format('H:i');
            if ($cancha !== null && $cancha !== '') {
                $partes[] = 'Cancha '.$cancha;
            }
        }

        return implode(' · ', $partes);
    }

    /** Describe la planilla adeudada: deuda manual por su motivo, partido por su fecha y su rival. */
    private function detallePlanilla(array $cargo, ?string $nombreFecha): string
    {
        if ($cargo['sin_partido']) {
            return $cargo['motivo'] ?: 'Deuda cargada a mano';
        }
        $instancia = $nombreFecha ?? ($cargo['tipo'] === CargoPlanilla::TIPO_INSTANCIA_FINAL ? 'Instancia final' : 'Partido');

        return $instancia.' vs '.$cargo['oponente'];
    }

    /** @param array<int, int> $equipos */
    public function deudasParaPlanilla(int $edicionId, array $equipos, ?string $fecha): array
    {
        $cargos = CargoPlanilla::query()->where('idEdicion', $edicionId)->whereIn('idEquipo', $equipos)
            ->with(['parent', 'equipo', 'categoria'])->orderBy('id')->get();
        $porId = $cargos->keyBy('id');
        $filas = $this->filas($cargos)->where('deuda_ars', '>', 0);
        $nombresFecha = Fecha::query()->whereIn('id', $filas->pluck('idFecha')->filter()->unique())->pluck('nombre', 'id');
        // Datos de la franja: nombre de la fecha, cancha y si la deuda se cargó a mano.
        $filas = $filas->map(fn (array $fila): array => $fila + [
            'fecha_nombre' => $nombresFecha[$fila['idFecha']] ?? null,
            'cancha' => $porId[$fila['id']]->parent?->cancha,
            'manual' => data_get($porId[$fila['id']]->snapshot, 'origen') === 'manual',
        ]);
        $dia = $this->diaConocido($fecha);
        $limite = $dia === null ? null : min($dia, today()->toDateString());
        $inciertos = $limite === null ? $equipos : $filas->whereNull('fecha')->pluck('idEquipo')->unique()->all();
        $deudas = $limite === null ? collect() : $filas->filter(fn (array $fila): bool => $fila['fecha'] !== null
            && substr($fila['fecha'], 0, 10) <= $limite)->sortBy('fecha')->values();

        return ['deudas' => $deudas, 'inciertos' => $inciertos];
    }

    private function diaConocido(?string $fecha): ?string
    {
        if ($fecha === null || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[T\s]|$)/', $fecha)) {
            return null;
        }
        try {
            return Carbon::parse($fecha)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function deudaCargo(int $cargoId): int
    {
        $cargos = CargoPlanilla::query()->whereKey($cargoId)->with(['parent', 'equipo', 'categoria'])->get();

        return (int) ($this->filas($cargos)->first()['deuda_ars'] ?? 0);
    }

    /**
     * @param  Collection<int, CargoPlanilla>  $cargos
     * @return Collection<int, array<string, mixed>>
     */
    private function filas(Collection $cargos): Collection
    {
        $aplicados = AplicacionPagoPlanilla::query()->whereIn('cargo_id', $cargos->modelKeys())
            ->selectRaw('cargo_id, SUM(importe_ars) as total')->groupBy('cargo_id')->pluck('total', 'cargo_id');
        $eximidos = ExencionCobroPlanilla::query()->whereIn('cargo_id', $cargos->modelKeys())
            ->selectRaw('cargo_id, SUM(importe_ars) as total')->groupBy('cargo_id')->pluck('total', 'cargo_id');
        $ausentes = AusenciaCobroPlanilla::query()->vigentes()->whereIn('cargo_id', $cargos->modelKeys())->pluck('cargo_id')->flip();

        return $cargos->map(function (CargoPlanilla $cargo) use ($aplicados, $eximidos, $ausentes): array {
            $aplicado = (int) ($aplicados[$cargo->id] ?? 0);
            $eximido = (int) ($eximidos[$cargo->id] ?? 0);
            $ausente = $ausentes->has($cargo->id);
            $deuda = (new DistribucionCreditoService)->calcularDeuda([
                'precio_original' => $cargo->precio_original, 'aplicado_ars' => $aplicado, 'eximido_ars' => $eximido, 'ausente' => $ausente,
            ], $cargo->id);
            $padre = $cargo->parent;
            $sinPartido = $cargo->esDeudaSinPartido();
            $fecha = $sinPartido
                ? data_get($cargo->snapshot, 'fecha_deuda')
                : $padre?->horario_datetime ?? $padre?->horario ?? data_get($cargo->snapshot, 'fecha_partido');

            return [
                'id' => $cargo->id, 'idEquipo' => $cargo->idEquipo, 'idCategoria' => $cargo->idCategoria,
                'tipo' => $cargo->tipo, 'partido' => $cargo->parent_id, 'sin_partido' => $sinPartido,
                'motivo' => data_get($cargo->snapshot, 'motivo'),
                'idFecha' => $padre?->idFechas ?? data_get($cargo->snapshot, 'partido.id_fecha'),
                'nombre_vigente' => $cargo->equipo?->nombre,
                'equipo' => data_get($cargo->snapshot, 'equipo.nombre') ?? $cargo->equipo?->nombre ?? 'Equipo eliminado',
                'oponente' => $sinPartido ? null : data_get($cargo->snapshot, 'oponente.nombre', 'Sin rival histórico'),
                'categoria' => data_get($cargo->snapshot, 'categoria.nombre') ?? $cargo->categoria?->nombreCategoria ?? 'Categoría eliminada',
                'fecha' => $this->diaConocido($fecha ? (string) $fecha : null) ? Carbon::parse($fecha)->format('Y-m-d H:i') : null,
                'precio_ars' => $cargo->precio_original, 'aplicado_ars' => $aplicado,
                'eximido_ars' => $eximido, 'deuda_ars' => $deuda,
                'estado' => $ausente ? 'no_vino' : ($deuda > 0 ? ($aplicado > 0 ? 'parcial' : 'pendiente') : ($eximido > 0 ? 'exento' : 'pagado')),
            ];
        });
    }

    public function resultadoPago(PagoPlanilla $pago): array
    {
        $cuenta = $pago->cuenta;
        $filas = $this->consultar(['idEdicion' => $cuenta->idEdicion, 'idEquipo' => $cuenta->idEquipo, 'estado' => 'todos', 'periodo' => 'todos'])['cargos'];
        $aplicaciones = AplicacionPagoPlanilla::query()->where('pago_id', $pago->id)->get();
        $pagosCuenta = PagoPlanilla::query()->where('cuenta_id', $cuenta->id)->get();
        $usado = AplicacionPagoPlanilla::query()->whereIn('pago_id', $pagosCuenta->modelKeys())->sum('importe_ars');

        return [
            'id' => $pago->id, 'medio' => $pago->medio, 'importe_ars' => $pago->importe_ars,
            'aplicado_ars' => (int) $aplicaciones->sum('importe_ars'),
            'aplicado_origen_ars' => (int) $aplicaciones->where('cargo_id', $pago->cargo_origen_id)->sum('importe_ars'),
            'deuda_cargo_ars' => $filas->firstWhere('id', $pago->cargo_origen_id)['deuda_ars'],
            'credito_pago_ars' => $pago->importe_ars - (int) $aplicaciones->sum('importe_ars'),
            'credito_equipo_ars' => (int) $pagosCuenta->sum('importe_ars') - (int) $usado,
        ];
    }

    private function coincide(array $fila, array $filtros): bool
    {
        foreach (['idCategoria', 'idEquipo', 'idFecha', 'tipo', 'partido'] as $clave) {
            if (isset($filtros[$clave]) && (string) $filtros[$clave] !== (string) $fila[$clave]) {
                return false;
            }
        }
        if (isset($filtros['buscar']) && ! str_contains(mb_strtolower(($fila['nombre_vigente'] ?? '').' '.$fila['equipo']), mb_strtolower(trim($filtros['buscar'])))) {
            return false;
        }
        $estado = $filtros['estado'] ?? 'deudores';
        if ($estado === 'deudores' ? $fila['deuda_ars'] === 0 : ($estado !== 'todos' && $estado !== $fila['estado'])) {
            return false;
        }
        if (isset($filtros['fecha']) && substr($fila['fecha'] ?? '', 0, 10) !== $filtros['fecha']) {
            return false;
        }
        $periodo = $filtros['periodo'] ?? 'hasta_hoy';
        $dia = $fila['fecha'] ? substr($fila['fecha'], 0, 10) : null;

        return $periodo === 'todos' || ($dia !== null && ($periodo === 'futuros' ? $dia > today()->toDateString() : $dia <= today()->toDateString()));
    }
}
