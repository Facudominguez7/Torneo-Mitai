{{-- Equipos que deben planillas de partidos jugados hasta hoy, con el detalle de cada planilla adeudada. --}}
<section class="mt-4" aria-labelledby="cobros-deudores-titulo" data-cobros-deudores>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 id="cobros-deudores-titulo" class="text-lg font-bold text-pa-tinta">Equipos que deben planillas</h2>
            @if ($deudores->isNotEmpty())
                <p class="text-sm text-pa-tinta-cuerpo">{{ $deudores->count() }} {{ $deudores->count() === 1 ? 'equipo' : 'equipos' }} · $ {{ number_format($deudores->sum('deuda_ars'), 0, ',', '.') }} en total · de mayor a menor deuda</p>
            @endif
        </div>
        @if ($deudores->isNotEmpty())
            <a data-cobros-informe-deudores target="_blank" rel="noopener"
                href="{{ route('cobros.informe-deudores', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                aria-label="Informe de todos los deudores (se abre en otra pestaña)"
                class="inline-flex min-h-11 items-center justify-center rounded-md bg-brand-navy px-4 py-2 text-sm font-semibold text-white hover:bg-brand-navy-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-resaltado/40 focus-visible:ring-offset-2">Informe de deudores</a>
        @endif
    </div>

    @if ($deudores->isEmpty())
        <p class="mt-3 rounded-lg border border-pa-linea bg-pa-superficie p-4 text-sm text-pa-tinta-media">Ningún equipo debe planillas a hoy.</p>
    @else
        @php($categoriasDeudores = $deudores->pluck('categoria')->filter()->unique()->sort()->values())
        <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)]">
            <div class="min-w-0">
                <label for="cobros-deudores-buscar" class="block text-sm font-medium text-pa-tinta-media">Buscar equipo</label>
                <input id="cobros-deudores-buscar" type="search" data-cobros-deudores-buscar autocomplete="off" maxlength="150"
                    class="mt-1 min-h-11 w-full rounded-md border-pa-campo-borde text-sm focus:border-pa-acento focus:ring-pa-acento bg-pa-campo-fondo">
            </div>
            <div class="min-w-0">
                <label for="cobros-deudores-categoria" class="block text-sm font-medium text-pa-tinta-media">Categoría</label>
                <select id="cobros-deudores-categoria" data-cobros-deudores-categoria
                    class="mt-1 min-h-11 w-full rounded-md border-pa-campo-borde text-sm focus:border-pa-acento focus:ring-pa-acento">
                    <option value="">Todas</option>
                    @foreach ($categoriasDeudores as $categoria)
                        <option value="{{ $categoria }}">{{ $categoria }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="mt-3 hidden rounded-lg border border-pa-linea bg-pa-superficie p-4 text-sm text-pa-tinta-media" data-cobros-deudores-vacio role="status">Ningún equipo coincide con la búsqueda.</p>

        <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ($deudores as $deudor)
                @php($planillas = collect($deudor['planillas']))
                <li data-cobros-deudor="{{ $deudor['idEquipo'] }}" data-equipo="{{ Str::lower($deudor['equipo']) }}" data-categoria="{{ $deudor['categoria'] }}"
                    class="flex h-full flex-col rounded-lg border border-pa-linea bg-pa-superficie p-4">
                    <span class="break-words font-semibold text-pa-tinta">{{ $deudor['equipo'] }}</span>
                    <span class="text-xs text-pa-tinta-suave">{{ $deudor['categoria'] }}</span>
                    <span class="mt-2 text-2xl font-bold tabular-nums text-pa-resaltado">$ {{ number_format($deudor['deuda_ars'], 0, ',', '.') }}</span>
                    <span class="text-sm text-pa-tinta-cuerpo">{{ $deudor['cargos'] }} {{ $deudor['cargos'] === 1 ? 'planilla pendiente' : 'planillas pendientes' }}</span>
                    <ul class="mt-3 flex flex-col gap-1.5 border-t border-pa-linea-suave pt-3 text-sm">
                        @foreach ($planillas->take(3) as $planilla)
                            @include('Panel.cobros.deudor-planilla')
                        @endforeach
                    </ul>
                    @if ($planillas->count() > 3)
                        <details class="mt-1.5 text-sm">
                            <summary class="min-h-11 cursor-pointer rounded py-2 font-semibold text-pa-resaltado focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-resaltado/40">
                                +{{ $planillas->count() - 3 }} {{ $planillas->count() - 3 === 1 ? 'planilla' : 'planillas' }} más
                            </summary>
                            <ul class="flex flex-col gap-1.5">
                                @foreach ($planillas->slice(3) as $planilla)
                                    @include('Panel.cobros.deudor-planilla')
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <div class="mt-auto flex flex-wrap gap-2 pt-3">
                        @if ($configuracion?->activado_at)
                            <button type="button" data-cobros-pagar-deudor="{{ $deudor['idEquipo'] }}" data-cobro="pago" data-cargo="{{ json_encode($deudor['pago']) }}"
                                aria-label="Registrar pago de {{ $deudor['equipo'] }}: planilla más antigua"
                                class="inline-flex min-h-11 items-center justify-center rounded-md bg-brand-navy px-3 py-2 text-sm font-semibold text-white hover:bg-brand-navy-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-resaltado/40 focus-visible:ring-offset-2">Registrar pago</button>
                        @endif
                        <a data-cobros-informe="{{ $deudor['idEquipo'] }}" target="_blank" rel="noopener"
                            href="{{ route('cobros.informe-equipo', ['idEdicion' => $EdicionSeleccionada->id, 'idEquipo' => $deudor['idEquipo']]) }}"
                            aria-label="Ver informe de {{ $deudor['equipo'] }} (se abre en otra pestaña)"
                            class="inline-flex min-h-11 items-center justify-center rounded-md border border-pa-campo-borde px-3 py-2 text-sm font-semibold text-pa-resaltado hover:border-pa-acento focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-resaltado/40 focus-visible:ring-offset-2">Ver informe</a>
                    </div>
                </li>
            @endforeach
        </ul>
        <script>
            (() => {
                const seccion = document.currentScript.closest('[data-cobros-deudores]');
                const buscar = seccion.querySelector('[data-cobros-deudores-buscar]');
                const categoria = seccion.querySelector('[data-cobros-deudores-categoria]');
                const vacio = seccion.querySelector('[data-cobros-deudores-vacio]');
                const tarjetas = [...seccion.querySelectorAll('[data-cobros-deudor]')];
                const filtrar = () => {
                    const texto = buscar.value.trim().toLocaleLowerCase('es');
                    let visibles = 0;
                    tarjetas.forEach((tarjeta) => {
                        const coincide = tarjeta.dataset.equipo.includes(texto) && (!categoria.value || tarjeta.dataset.categoria === categoria.value);
                        tarjeta.classList.toggle('hidden', !coincide);
                        visibles += coincide ? 1 : 0;
                    });
                    vacio.classList.toggle('hidden', visibles > 0);
                };
                buscar.addEventListener('input', filtrar);
                categoria.addEventListener('change', filtrar);
            })();
        </script>
    @endif
</section>
