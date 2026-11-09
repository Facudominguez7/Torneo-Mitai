@if ($cargos->isEmpty())
    <p class="rounded-lg border border-pa-linea bg-pa-superficie p-6 text-pa-tinta-media">No hay cargos para estos filtros. Los partidos anteriores a la activación no generan cargos retroactivos.</p>
@else
    <div class="{{ ($impresion ?? false) ? '' : 'hidden md:block' }} rounded-lg border border-pa-linea bg-pa-superficie">
        <table class="w-full table-fixed text-left text-sm">
            <caption class="sr-only">Cargos de planilla filtrados, importes en ARS</caption>
            <thead class="bg-pa-fila-bg text-pa-tinta-media"><tr>
                <th class="w-1/3 p-3">Equipo / partido</th><th class="p-3">Estado</th>
                <th class="p-3 text-right">Original</th><th class="p-3 text-right">Aplicado</th>
                <th class="p-3 text-right">Eximido</th><th class="p-3 text-right">Deuda</th>
            </tr></thead>
            <tbody class="divide-y divide-pa-linea">
                @foreach ($cargos as $cargo)
                    <tr>
                        <td class="break-words p-3"><strong>{{ $cargo['equipo'] }}</strong><br>@if ($cargo['sin_partido']) Deuda cargada a mano{{ filled($cargo['motivo']) ? ': '.$cargo['motivo'] : '' }}@else vs. {{ $cargo['oponente'] }}@endif<br><span class="text-pa-tinta-cuerpo">{{ $cargo['categoria'] }} · {{ $cargo['sin_partido'] && $cargo['fecha'] ? substr($cargo['fecha'], 0, 10) : ($cargo['fecha'] ?? 'Sin fecha') }}@unless ($cargo['sin_partido']) · #{{ $cargo['partido'] }} {{ $cargo['tipo'] === 'planilla_instancia_final' ? '(final)' : '' }}@endunless</span>@unless ($impresion ?? false) @include('Panel.cobros.acciones') @endunless</td>
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
                <article class="min-w-0 rounded-lg border border-pa-linea bg-pa-superficie p-4">
                    <h2 class="break-words font-semibold text-pa-tinta">{{ $cargo['equipo'] }}</h2>
                    <p class="break-words text-sm text-pa-tinta-cuerpo">{{ $cargo['sin_partido'] ? 'Deuda cargada a mano'.(filled($cargo['motivo']) ? ': '.$cargo['motivo'] : '') : 'vs. '.$cargo['oponente'] }} · {{ $cargo['categoria'] }}</p>
                    <p class="mt-1 text-sm text-pa-tinta-cuerpo">@if ($cargo['sin_partido']){{ $cargo['fecha'] ? substr($cargo['fecha'], 0, 10) : 'Sin fecha' }}@else{{ $cargo['fecha'] ?? 'Sin fecha' }} · Partido #{{ $cargo['partido'] }} {{ $cargo['tipo'] === 'planilla_instancia_final' ? '(final)' : '' }}@endif</p>
                    <p class="mt-2 font-medium text-pa-tinta">{{ str_replace('_', ' ', ucfirst($cargo['estado'])) }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        @foreach (['precio_ars' => 'Original', 'aplicado_ars' => 'Aplicado', 'eximido_ars' => 'Eximido', 'deuda_ars' => 'Deuda'] as $campo => $nombre)
                            <div class="min-w-0"><dt class="text-pa-tinta-cuerpo">{{ $nombre }}</dt><dd class="break-words font-semibold">$ {{ number_format($cargo[$campo], 0, ',', '.') }}</dd></div>
                        @endforeach
                    </dl>
                    @include('Panel.cobros.acciones')
                </article>
            @endforeach
        </div>
    @endunless
@endif
