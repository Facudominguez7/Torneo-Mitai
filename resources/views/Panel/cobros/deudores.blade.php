{{-- Equipos que deben planillas de partidos jugados hasta hoy; cada tarjeta filtra la consulta por ese equipo. --}}
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
                <li>
                    <a data-cobros-deudor="{{ $deudor['idEquipo'] }}"
                        href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id, 'idEquipo' => $deudor['idEquipo'], 'estado' => 'deudores']) }}"
                        class="flex h-full flex-col rounded-lg border border-gray-200 border-l-4 border-l-brand-gold bg-white p-4 transition hover:border-brand-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                        <span class="break-words font-semibold text-gray-900">{{ $deudor['equipo'] }}</span>
                        <span class="text-xs text-gray-500">{{ $deudor['categoria'] }}</span>
                        <span class="mt-2 text-2xl font-bold text-brand-navy">$ {{ number_format($deudor['deuda_ars'], 0, ',', '.') }}</span>
                        <span class="text-sm text-gray-600">{{ $deudor['cargos'] }} {{ $deudor['cargos'] === 1 ? 'planilla pendiente' : 'planillas pendientes' }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
