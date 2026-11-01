@php
    $formatoArs = fn (int $importe): string => '$ '.number_format($importe, 0, ',', '.');
    $diaCorto = function (string $fecha): string {
        $momento = \Carbon\Carbon::parse($fecha)->locale('es');

        return rtrim($momento->isoFormat('ddd'), '.').' '.$momento->format('d/m');
    };
@endphp
<div class="grid md:grid-cols-2 md:divide-x divide-y divide-gray-200 md:divide-y-0" data-cobros-grilla data-firma="{{ hash('sha256', $deudasPlanilla->toJson().$cobrosPlanilla->toJson().json_encode($equiposInciertos).($configuracionCobros?->activado_at ?? '')) }}">
    @foreach ([[$partido->idEquipoLocal, $partido->equipoLocal->nombre, 'Local'], [$partido->idEquipoVisitante, $partido->equipoVisitante->nombre, 'Visitante']] as [$equipoId, $nombre, $condicion])
        @php
            $deudasEquipo = $deudasPlanilla->where('idEquipo', $equipoId)->values();
            $cargoActual = $cobrosPlanilla->get($equipoId);
            $incierto = in_array($equipoId, $equiposInciertos);
            $puedeCobrar = auth()->user()->isAdmin() && $configuracionCobros?->activado_at;
        @endphp
        <div class="grid min-w-0 content-start gap-2 px-6 py-3" x-data="{ seleccion: '{{ $deudasEquipo->first()['id'] ?? '' }}' }" data-equipo-franja="{{ $equipoId }}">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <h3 class="text-base font-bold text-gray-900">{{ $nombre }} <span class="text-sm font-medium text-gray-500">{{ $condicion }}</span></h3>
                @if ($deudasEquipo->isNotEmpty())
                    <strong class="whitespace-nowrap font-normal tabular-nums"><b class="font-bold text-red-700">Debe {{ $formatoArs((int) $deudasEquipo->sum('deuda_ars')) }}</b> <small class="text-sm text-gray-500">en {{ $deudasEquipo->count() }} {{ $deudasEquipo->count() === 1 ? 'planilla' : 'planillas' }}</small></strong>
                @elseif ($incierto)
                    <strong class="whitespace-nowrap font-semibold text-gray-700">Saldo indeterminado</strong>
                @else
                    <strong class="whitespace-nowrap font-semibold text-green-700">Al día</strong>
                @endif
            </div>
            @if ($deudasEquipo->isNotEmpty() && $incierto)
                <p class="text-sm text-gray-600">Saldo adicional indeterminado por fecha no disponible.</p>
            @endif
            @if ($deudasEquipo->isEmpty())
                <p class="text-sm text-gray-500">{{ $incierto ? 'No se puede calcular la deuda: el partido no tiene fecha.' : 'No debe planillas de esta edición.' }}</p>
            @else
                <label for="deuda-equipo-{{ $equipoId }}" class="text-sm font-semibold text-gray-700">Planilla adeudada</label>
                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <select id="deuda-equipo-{{ $equipoId }}" data-deuda-selector x-model="seleccion" class="min-h-10 w-full min-w-0 rounded-md border-gray-300 text-sm">
                        @foreach ($deudasEquipo as $deuda)
                            <option value="{{ $deuda['id'] }}">{{ collect([$deuda['fecha_nombre'], $diaCorto($deuda['fecha']), $deuda['sin_partido'] ? 'Deuda manual' : 'vs '.$deuda['oponente'], $formatoArs($deuda['deuda_ars'])])->filter()->implode(' · ') }}</option>
                        @endforeach
                    </select>
                    @foreach ($deudasEquipo as $deuda)
                        @php
                            $momento = \Carbon\Carbon::parse($deuda['fecha'])->locale('es');
                            $detalle = collect([
                                $deuda['sin_partido']
                                    ? ucfirst($momento->isoFormat('dddd DD/MM')).'. Deuda cargada a mano'.(filled($deuda['motivo']) ? ': '.$deuda['motivo'] : '').'.'
                                    : ucfirst($momento->isoFormat('dddd DD/MM, HH:mm')).' hs'.(filled($deuda['cancha']) ? ', cancha '.$deuda['cancha'] : '').'.',
                                $deuda['manual'] && ! $deuda['sin_partido'] ? 'Cargada a mano.' : null,
                                $deuda['aplicado_ars'] > 0 ? 'Pagó '.$formatoArs($deuda['aplicado_ars']).' de '.$formatoArs($deuda['precio_ars']).'.' : 'Sin pagos aplicados.',
                            ])->filter()->implode(' ');
                        @endphp
                        <div x-show="seleccion === '{{ $deuda['id'] }}'" class="contents" data-planilla-cobro="{{ $deuda['id'] }}" data-deuda="{{ $deuda['deuda_ars'] }}" data-estado="{{ $deuda['estado'] }}">
                            @if ($puedeCobrar)
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" data-cobro="pago" data-cargo="{{ json_encode($deuda) }}" class="min-h-10 whitespace-nowrap rounded-md bg-brand-navy px-3.5 text-sm font-semibold text-white hover:bg-brand-navy-deep">Registrar pago</button>
                                    <button type="button" data-cobro="exencion" data-cargo="{{ json_encode($deuda) }}" class="min-h-10 whitespace-nowrap rounded-md border border-gray-300 px-3.5 text-sm">Marcar exento</button>
                                </div>
                            @endif
                            <p class="text-sm text-gray-500 sm:col-span-2">{{ $detalle }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (auth()->user()->isAdmin() && $cargoActual && $cargoActual['deuda_ars'] === 0)
                <p class="text-sm text-gray-600">Planilla actual: {{ match ($cargoActual['estado']) { 'pagado' => 'Pagada', 'exento' => 'Exenta', 'no_vino' => 'No vino', default => 'Sin deuda' } }}</p>
            @endif
            @if ($puedeCobrar && $cargoActual)
                @if ($cargoActual['deuda_ars'] > 0 && ! $deudasEquipo->contains('id', $cargoActual['id']))
                    <div class="flex flex-wrap gap-2" data-planilla-cobro="{{ $cargoActual['id'] }}" data-deuda="{{ $cargoActual['deuda_ars'] }}" data-estado="{{ $cargoActual['estado'] }}">
                        <span class="w-full text-sm text-gray-600">Cobro del partido actual</span>
                        <button type="button" data-cobro="pago" data-cargo="{{ json_encode($cargoActual) }}" class="min-h-10 rounded-md bg-brand-navy px-3.5 text-sm font-semibold text-white hover:bg-brand-navy-deep">Registrar pago</button>
                        <button type="button" data-cobro="exencion" data-cargo="{{ json_encode($cargoActual) }}" class="min-h-10 rounded-md border border-gray-300 px-3.5 text-sm">Marcar exento</button>
                    </div>
                @endif
                @if ($cargoActual['deuda_ars'] > 0 && $cargoActual['aplicado_ars'] === 0)
                    <div><button type="button" data-cobro="no_vino" data-cargo="{{ json_encode($cargoActual) }}" class="min-h-10 rounded-md border border-gray-300 px-3.5 text-sm">No vino</button></div>
                @elseif ($cargoActual['estado'] === 'no_vino')
                    <div><button type="button" data-cobro="deshacer_no_vino" data-cargo="{{ json_encode($cargoActual) }}" class="min-h-10 rounded-md border border-gray-300 px-3.5 text-sm">Deshacer no vino</button></div>
                @endif
            @endif
        </div>
    @endforeach
</div>
