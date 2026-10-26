@if ($cargos->isEmpty())
    <p class="rounded-lg border border-gray-200 bg-white p-6 text-gray-700">No hay cargos para estos filtros. Los partidos anteriores a la activación no generan cargos retroactivos.</p>
@else
    <div class="{{ ($impresion ?? false) ? '' : 'hidden md:block' }} rounded-lg border border-gray-200 bg-white">
        <table class="w-full table-fixed text-left text-sm">
            <caption class="sr-only">Cargos de planilla filtrados, importes en ARS</caption>
            <thead class="bg-gray-50 text-gray-700"><tr>
                <th class="w-1/3 p-3">Equipo / partido</th><th class="p-3">Estado</th>
                <th class="p-3 text-right">Original</th><th class="p-3 text-right">Aplicado</th>
                <th class="p-3 text-right">Eximido</th><th class="p-3 text-right">Deuda</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($cargos as $cargo)
                    <tr>
                        <td class="break-words p-3"><strong>{{ $cargo['equipo'] }}</strong><br>vs. {{ $cargo['oponente'] }}<br><span class="text-gray-600">{{ $cargo['categoria'] }} · {{ $cargo['fecha'] ?? 'Sin fecha' }} · #{{ $cargo['partido'] }} {{ $cargo['tipo'] === 'planilla_instancia_final' ? '(final)' : '' }}</span>@unless ($impresion ?? false) @include('Panel.cobros.acciones') @endunless</td>
                        <td class="break-words p-3">{{ str_replace('_', ' ', ucfirst($cargo['estado'])) }}</td>
                        @foreach (['precio_ars', 'aplicado_ars', 'eximido_ars', 'deuda_ars'] as $campo)
                            <td class="break-words p-3 text-right tabular-nums">$ {{ number_format($cargo[$campo], 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @unless ($impresion ?? false)
        <div class="grid gap-3 md:hidden">
            @foreach ($cargos as $cargo)
                <article class="min-w-0 rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="break-words font-semibold text-gray-900">{{ $cargo['equipo'] }}</h2>
                    <p class="break-words text-sm text-gray-600">vs. {{ $cargo['oponente'] }} · {{ $cargo['categoria'] }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $cargo['fecha'] ?? 'Sin fecha' }} · Partido #{{ $cargo['partido'] }} {{ $cargo['tipo'] === 'planilla_instancia_final' ? '(final)' : '' }}</p>
                    <p class="mt-2 font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($cargo['estado'])) }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        @foreach (['precio_ars' => 'Original', 'aplicado_ars' => 'Aplicado', 'eximido_ars' => 'Eximido', 'deuda_ars' => 'Deuda'] as $campo => $nombre)
                            <div class="min-w-0"><dt class="text-gray-600">{{ $nombre }}</dt><dd class="break-words font-semibold">$ {{ number_format($cargo[$campo], 0, ',', '.') }}</dd></div>
                        @endforeach
                    </dl>
                    @include('Panel.cobros.acciones')
                </article>
            @endforeach
        </div>
    @endunless
@endif
