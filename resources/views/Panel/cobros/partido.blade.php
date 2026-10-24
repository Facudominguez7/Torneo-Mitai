<div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-cobros-grilla>
    @foreach ([[$partido->idEquipoLocal, $partido->equipoLocal->nombre, 'Local'], [$partido->idEquipoVisitante, $partido->equipoVisitante->nombre, 'Visitante']] as [$idEquipoCobro, $nombreEquipoCobro, $condicionCobro])
        @php
            $cargoCobro = $cobrosPlanilla->get($idEquipoCobro);
        @endphp
        @continue($cargoCobro === null)
        @php
            $deudaCobro = number_format($cargoCobro['deuda_ars'], 0, ',', '.');
            [$cobroVariant, $cobroLabel] = match ($cargoCobro['estado']) {
                'pagado' => ['success', 'Pagada'],
                'exento' => ['info', 'Exenta'],
                'parcial' => ['warning', 'Pago parcial, debe $ '.$deudaCobro],
                default => ['danger', 'Debe $ '.$deudaCobro],
            };
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-3" data-planilla-cobro="{{ $cargoCobro['id'] }}" data-deuda="{{ $cargoCobro['deuda_ars'] }}" data-estado="{{ $cargoCobro['estado'] }}">
            <div class="min-w-0 space-y-1">
                <p class="break-words font-semibold text-gray-900">{{ $nombreEquipoCobro }} <span class="text-sm font-normal text-gray-500">({{ $condicionCobro }})</span></p>
                <x-ui.badge :variant="$cobroVariant">{{ $cobroLabel }}</x-ui.badge>
            </div>
            @if ($cargoCobro['deuda_ars'] > 0 && $configuracionCobros?->activado_at)
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-cobro="pago" data-cargo="{{ json_encode($cargoCobro) }}" class="min-h-11 rounded bg-blue-900 px-4 py-2 text-sm font-medium text-white">Registrar pago</button>
                    <button type="button" data-cobro="exencion" data-cargo="{{ json_encode($cargoCobro) }}" class="min-h-11 rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Marcar exento</button>
                </div>
            @endif
        </div>
    @endforeach
</div>
