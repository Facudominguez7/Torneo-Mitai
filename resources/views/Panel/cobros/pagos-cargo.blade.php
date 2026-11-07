@php
    $pagosRegistrados = $cargo['pagos'] ?? [];
    // Con los cobros desactivados no se ofrece editar, igual que no se ofrece registrar.
    $cobrosActivos = ($configuracionCobros ?? null)?->activado_at !== null;
@endphp
@if ($pagosRegistrados !== [])
    <div class="grid gap-2 sm:col-span-2" data-pagos-cargo="{{ $cargo['id'] }}">
        @foreach ($pagosRegistrados as $pagoRegistrado)
            <div class="flex flex-wrap items-center justify-between gap-2" data-pago-registrado="{{ $pagoRegistrado['pago_id'] }}" data-editable="{{ $pagoRegistrado['editable'] ? '1' : '0' }}">
                <p class="break-words text-sm text-gray-700">Pago #{{ $pagoRegistrado['pago_id'] }} · $ {{ number_format($pagoRegistrado['importe_ars'], 0, ',', '.') }} · {{ $pagoRegistrado['medio'] === 'mercado_pago' ? 'Mercado Pago' : 'Efectivo' }}</p>
                @if ($cobrosActivos && $pagoRegistrado['editable'])
                    <button type="button" data-cobro="editar_pago" data-pago="{{ json_encode($pagoRegistrado + $cargo) }}" class="min-h-11 whitespace-nowrap rounded-md border border-gray-300 px-3.5 text-sm font-medium text-gray-700">Editar pago</button>
                @elseif ($cobrosActivos)
                    <p class="break-words text-sm text-gray-600">No se puede editar: su excedente ya cubrió otras planillas.</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
