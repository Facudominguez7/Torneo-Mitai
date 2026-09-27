@extends('Panel.admin')

@section('contenido')
    @php
        $totalPartidos = $partidos->count();
        $partidosJugados = $partidos->filter(fn ($p) => $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null)->count();
        $partidosPendientes = $totalPartidos - $partidosJugados;
        $pendientesPorPartido = collect($equiposSinAsistencia['equipos'] ?? [])->groupBy('partido_key');

        // El isset($idFase) original siempre era falso porque el controlador
        // nunca pasa $idFase a la vista (solo $nombreFase); el encabezado de
        // categoría solo dependía, en la práctica, de si hay filtro de
        // categoría activo. Se preserva ese comportamiento observado.
        $mostrarEncabezadoCategoria = ! $idCategoria;

        // Cuadro: categoría > copa > fase (columnas), conservando el orden en
        // que aparecen los partidos ya ordenados por el controlador
        // (pendientes primero, luego por fecha/horario).
        // Copas y fases se ordenan por su primer partido para que el cuadro se
        // lea en el orden en que se juega el torneo (las rondas sin copa, como
        // las tumbadas, suelen ir antes que las copas).
        $primerHorario = fn ($grupo) => $grupo->min(fn ($p) => $p->horario ?? '9999-12-31');

        $cuadro = $partidos
            ->groupBy(fn ($p) => $p->categoria->nombreCategoria ?? 'Sin categoría')
            ->map(fn ($partidosCategoria) => $partidosCategoria
                ->groupBy(fn ($p) => $p->copa->nombre ?? '')
                ->sortBy($primerHorario)
                ->map(fn ($partidosCopa) => $partidosCopa
                    ->groupBy(fn ($p) => $p->fase->nombre)
                    ->sortBy($primerHorario)));
    @endphp

    @if (isset($EdicionSeleccionada))
        @php
            $urlImprimirFixture = route('admin.imprimir-fixture-instancias-finales', ['idEdicion' => $EdicionSeleccionada->id ?? '']);
            $urlImprimirArbitros = route('admin.imprimir-fixture-arbitros', [
                'idEdicion' => $EdicionSeleccionada->id ?? '',
                'horario' => request('horario'),
            ]);
            $urlPlanillaCobro = route('admin.planilla-cobro', ['idEdicion' => $EdicionSeleccionada->id ?? '']);

            $contextoActivo = array_filter([$nombreCategoria ?? null, $nombreFase ?? null]);
            $subtituloInstancias = $totalPartidos . ' ' . ($totalPartidos === 1 ? 'partido' : 'partidos')
                . ($contextoActivo ? ' · ' . implode(' · ', $contextoActivo) : '');
        @endphp

        <x-ui.page-header title="Instancias finales" :subtitle="$subtituloInstancias">
            <x-slot name="actions">
                <x-ui.button href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final']) }}" variant="primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar partido
                </x-ui.button>
                @if (request()->filled('idCategoria'))
                    <x-ui.button variant="success"
                        href="{{ route('instancia_final.create', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idFase' => request('idFase'), 'idCopa' => request('idCopa'), 'estado' => request('estado'), 'busqueda' => request('busqueda'), 'fecha' => request('fecha'), 'horario' => request('horario')]) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar en esta categoría
                    </x-ui.button>
                @endif
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <select id="selector-impresion-instancias"
                        class="min-w-[200px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm outline-none focus:border-brand-navy focus:ring-2 focus:ring-brand-navy/30">
                        <option value="">Seleccionar opción</option>
                        <option value="fixture">Fixture</option>
                        <option value="arbitros">Planilla Árbitros</option>
                        <option value="planilla_cobro">Planilla de Cobro</option>
                    </select>
                    <x-ui.button type="button" variant="secondary"
                        onclick="ejecutarImpresionInstanciasFinales('{{ $urlImprimirFixture }}', '{{ $urlImprimirArbitros }}', '{{ $urlPlanillaCobro }}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir
                    </x-ui.button>
                </div>
            </x-slot>
        </x-ui.page-header>
    @endif

    {{-- Los filtros y chips leen la edición seleccionada, igual que en main. --}}
    @if (isset($EdicionSeleccionada))
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-end gap-3">
            @if ($categorias->count() > 0)
                <div class="w-full md:w-56">
                    <x-ui.select label="Categoría" id="filtro-categoria">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" {{ request('idCategoria') == $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nombreCategoria }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif

            @php
                $horariosInstanciasFinales = \App\Models\InstanciaFinal::where('idEdicion', $EdicionSeleccionada->id ?? 0)
                    ->whereNotNull('horario')
                    ->pluck('horario')
                    ->map(fn ($datetime) => \Carbon\Carbon::parse($datetime)->format('H:i'))
                    ->unique()
                    ->sort()
                    ->values();
            @endphp

            @if ($horariosInstanciasFinales->count() > 0)
                <div class="w-full md:w-48">
                    <x-ui.select label="Horario" id="filtro-horario">
                        <option value="">Todos</option>
                        @foreach ($horariosInstanciasFinales as $horario)
                            <option value="{{ $horario }}" {{ request('horario') == $horario ? 'selected' : '' }}>
                                {{ $horario }} hs
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif
        </div>

        @if (request()->filled('idCategoria') || request()->filled('idFase') || request()->filled('horario') || request()->filled('fecha'))
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if (request()->filled('idCategoria') && isset($nombreCategoria))
                    <x-ui.filter-chip active
                        href="{{ route('instancia_final.index', array_filter(array_merge(request()->except('idCategoria'), ['idEdicion' => $EdicionSeleccionada->id]))) }}">
                        Categoría: {{ $nombreCategoria }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('idFase') && isset($nombreFase))
                    <x-ui.filter-chip active
                        href="{{ route('instancia_final.index', array_filter(array_merge(request()->except('idFase'), ['idEdicion' => $EdicionSeleccionada->id]))) }}">
                        Fase: {{ $nombreFase }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('horario'))
                    <x-ui.filter-chip active
                        href="{{ route('instancia_final.index', array_filter(array_merge(request()->except('horario'), ['idEdicion' => $EdicionSeleccionada->id]))) }}">
                        Horario: {{ request('horario') }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('fecha'))
                    <x-ui.filter-chip active
                        href="{{ route('instancia_final.index', array_filter(array_merge(request()->except('fecha'), ['idEdicion' => $EdicionSeleccionada->id]))) }}">
                        Fecha: {{ request('fecha') }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
            </div>
        @endif
    </div>
    @endif

    <div class="mt-6 flex flex-wrap justify-center gap-4">
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-gray-900">{{ $totalPartidos }}</span>
            <p class="text-xs font-medium text-gray-500">Partidos</p>
        </div>
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-emerald-600">{{ $partidosJugados }}</span>
            <p class="text-xs font-medium text-gray-500">Jugados</p>
        </div>
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-amber-600">{{ $partidosPendientes }}</span>
            <p class="text-xs font-medium text-gray-500">Pendientes</p>
        </div>
    </div>

    @if ($partidos->isEmpty())
        <x-ui.empty-state class="mt-6" message="No se encontraron partidos de instancias finales con estos filtros"
            description="Probá cambiar los filtros o agregá un nuevo partido.">
            @if (isset($EdicionSeleccionada))
                <x-slot name="actions">
                    <x-ui.button href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final']) }}" variant="primary" size="sm">
                        Agregar partido
                    </x-ui.button>
                </x-slot>
            @endif
        </x-ui.empty-state>
    @else
        @foreach ($cuadro as $nombreCategoriaGrupo => $copasEnCategoria)
            <section class="mt-8">
                @if ($mostrarEncabezadoCategoria)
                    <h2 class="mb-3 text-xl font-bold text-gray-800">{{ $nombreCategoriaGrupo }}</h2>
                @endif

                @foreach ($copasEnCategoria as $nombreCopaGrupo => $fasesEnCopa)
                    <div class="mt-4 first:mt-0">
                        @if ($nombreCopaGrupo !== '')
                            <h3 class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-sm font-semibold text-yellow-700">
                                🏆 {{ $nombreCopaGrupo }}
                            </h3>
                        @else
                            {{-- Sin encabezado, estas rondas parecían parte de la copa anterior. --}}
                            <h3 class="mb-3 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">
                                Sin copa
                            </h3>
                        @endif

                        {{-- Una columna de ancho fijo por fase; si no entran, scroll horizontal dentro de la copa. --}}
                        <div data-cuadro-fases
                            class="flex flex-col gap-4 lg:grid lg:grid-flow-col lg:auto-cols-[17rem] lg:items-start lg:overflow-x-auto lg:pb-2">
                            @foreach ($fasesEnCopa as $nombreFaseGrupo => $partidosDeFase)
                                <div>
                                    <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $nombreFaseGrupo }}</h4>
                                    <div class="space-y-4">
                                        @foreach ($partidosDeFase as $p)
                                            @include('Panel.instancia_final._card', ['p' => $p])
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    @endif

    <script>
        function ejecutarImpresionInstanciasFinales(urlFixture, urlArbitros, urlPlanillaCobro) {
            const selector = document.getElementById('selector-impresion-instancias');

            if (!selector?.value) {
                Swal.fire({
                    icon: 'info',
                    title: 'Seleccioná una opción',
                    text: 'Elegí qué querés imprimir antes de continuar.',
                    confirmButtonColor: '#2563eb',
                });
                return;
            }

            if (selector.value === 'fixture') {
                imprimirEnIframe(urlFixture);
                return;
            }

            if (selector.value === 'arbitros') {
                imprimirEnIframe(urlArbitros);
                return;
            }

            if (selector.value === 'planilla_cobro') {
                imprimirConTitulo(urlPlanillaCobro, 'Planilla de Cobro');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";

            // Actualizar la URL con los filtros seleccionados, preservando
            // los que ya están aplicados.
            function updateUrl(params) {
                var newUrl = new URL(window.location.href);
                if (idEdicion) newUrl.searchParams.set('idEdicion', idEdicion);

                Object.keys(params).forEach(function (key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.location.href = newUrl;
            }

            var selectHorario = document.getElementById('filtro-horario');
            if (selectHorario) {
                selectHorario.addEventListener('change', function () {
                    updateUrl({ horario: this.value });
                });
            }

            var selectCategoria = document.getElementById('filtro-categoria');
            if (selectCategoria) {
                selectCategoria.addEventListener('change', function () {
                    updateUrl({ idCategoria: this.value });
                });
            }
        });
    </script>
@endsection
