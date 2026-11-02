{{-- Equipos que deben planillas de partidos jugados hasta hoy, con el detalle de cada planilla adeudada. --}}
<section class="mt-4" aria-labelledby="cobros-deudores-titulo">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="cobros-deudores-titulo" class="text-lg font-bold text-gray-900">Equipos que deben planillas</h2>
        @if ($deudores->isNotEmpty())
            <p class="text-sm text-gray-600">{{ $deudores->count() }} {{ $deudores->count() === 1 ? 'equipo' : 'equipos' }} · $ {{ number_format($deudores->sum('deuda_ars'), 0, ',', '.') }} en total</p>
        @endif
    </div>

    @if ($deudores->isEmpty())
        <p class="mt-3 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">Ningún equipo debe planillas a hoy.</p>
    @else
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ($deudores as $deudor)
                <li data-cobros-deudor="{{ $deudor['idEquipo'] }}"
                    class="flex h-full flex-col rounded-lg border border-gray-200 border-l-4 border-l-brand-gold bg-white p-4">
                    <span class="break-words font-semibold text-gray-900">{{ $deudor['equipo'] }}</span>
                    <span class="text-xs text-gray-500">{{ $deudor['categoria'] }}</span>
                    <span class="mt-2 text-2xl font-bold text-brand-navy">$ {{ number_format($deudor['deuda_ars'], 0, ',', '.') }}</span>
                    <span class="text-sm text-gray-600">{{ $deudor['cargos'] }} {{ $deudor['cargos'] === 1 ? 'planilla pendiente' : 'planillas pendientes' }}</span>
                    <ul class="mt-3 flex flex-col gap-1.5 border-t border-gray-100 pt-3 text-sm">
                        @foreach ($deudor['planillas'] as $planilla)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 break-words text-gray-700">
                                    {{ $planilla['detalle'] }}
                                    @if ($planilla['dia'])
                                        <span class="text-xs text-gray-500">· {{ $planilla['dia'] }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-gray-900">$ {{ number_format($planilla['deuda_ars'], 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a data-cobros-informe="{{ $deudor['idEquipo'] }}" target="_blank" rel="noopener"
                        href="{{ route('cobros.informe-equipo', ['idEdicion' => $EdicionSeleccionada->id, 'idEquipo' => $deudor['idEquipo']]) }}"
                        class="mt-3 inline-flex min-h-11 items-center justify-center self-start rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-brand-navy hover:border-brand-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">Ver informe</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
