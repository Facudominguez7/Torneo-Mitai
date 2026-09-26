@extends('Panel.admin')

@section('contenido')
    @php
        $totalPartidos = $partidos->count();
        $contextoActivo = array_filter([
            $nombreCategoria ?? null,
            $nombreGrupo ?? null,
            $nombreFecha ?? null,
        ]);
        $subtituloPartidos = $totalPartidos . ' ' . ($totalPartidos === 1 ? 'partido' : 'partidos')
            . ($contextoActivo ? ' · ' . implode(' · ', $contextoActivo) : '');

        // Agrupar por horario solo cuando la página está filtrada a una única
        // fecha: en el listado general (varias fechas mezcladas) se conserva
        // el orden plano actual para no fragmentar el listado en decenas de
        // grupos.
        $agruparPorHorario = isset($nombreFecha) && $partidos->isNotEmpty();

        $gruposPorHorario = $agruparPorHorario
            ? $partidos->groupBy(function ($p) {
                // Misma prioridad que la tarjeta: el horario legacy (texto en
                // 24 h, como "17:30" o "19.30") gana sobre horario_datetime.
                if ($p->horario !== null && $p->horario !== '') {
                    if (preg_match('/^(\d{1,2})(?:[:.](\d{2}))?$/', trim((string) $p->horario), $partes)) {
                        return sprintf('%02d:%s', (int) $partes[1], $partes[2] ?? '00');
                    }

                    return 'sin-horario';
                }

                if ($p->horario_datetime) {
                    return \Carbon\Carbon::parse($p->horario_datetime)->format('H:i');
                }

                return 'sin-horario';
            })->sortKeys()
            : null;
    @endphp

    @if (isset($EdicionSeleccionada))
        @php
            $urlImprimirArbitros = route('admin.imprimir-fixture-arbitros-partidos', [
                'idEdicion' => $EdicionSeleccionada->id,
                'horario' => request('horario'),
            ]);

            // Acción primaria única: con categoría y grupo crea directo conservando
            // los filtros activos; sin contexto completo deriva al selector.
            $urlNuevoPartido = isset($idCategoria, $idGrupo) && $idCategoria && $idGrupo
                ? route('partido.create', array_filter([
                    'idEdicion' => $EdicionSeleccionada->id,
                    'idCategoria' => $idCategoria,
                    'idGrupo' => $idGrupo,
                    'idFecha' => request('idFecha'),
                    'horario' => request('horario'),
                    'busqueda' => request('busqueda'),
                ], static fn ($valor) => $valor !== null && $valor !== ''))
                : route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'partido', 'forzarSeleccion' => 1]);
        @endphp

        <x-ui.page-header title="Partidos" :subtitle="$subtituloPartidos">
            <x-slot name="actions">
                <x-ui.button href="{{ $urlNuevoPartido }}" variant="primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo partido
                </x-ui.button>
                @if (isset($idCategoria) && $idCategoria)
                    <x-ui.button variant="secondary"
                        href="{{ route('partido.create-interzonal', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria]) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Partido interzonal
                    </x-ui.button>
                @endif
                <x-ui.button variant="secondary" onclick="imprimirEnIframe('{{ $urlImprimirArbitros }}')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir planilla de árbitros
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>
    @endif

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="relative w-full md:w-64">
                <x-ui.search label="Buscar equipo" labelHidden id="buscar-equipo" name="busqueda"
                    value="{{ request('busqueda') }}" placeholder="Buscar equipo..." />
                <button type="button" id="limpiar-busqueda" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="filtro-fecha hidden w-full md:w-48">
                <x-ui.select label="Fecha" id="filtro-fecha-select">
                    <option value="">Filtrar por fecha</option>
                    @foreach ($fechas as $f)
                        <option value="{{ $f->id }}" {{ (string) request('idFecha') === (string) $f->id ? 'selected' : '' }}>
                            {{ $f->nombre }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($mostrarFiltroGrupo)
                <div class="filtro-grupo w-full md:w-48">
                    <x-ui.select label="Grupo" id="filtro-grupo-select">
                        <option value="">Filtrar por grupo</option>
                        @foreach ($grupos as $g)
                            <option value="{{ $g->id }}" {{ (string) request('idGrupo') === (string) $g->id ? 'selected' : '' }}>
                                {{ $g->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif

            <div class="w-full md:w-48">
                <x-ui.select label="Categoría" id="filtro-categoria-select">
                    <option value="">Seleccionar categoría</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id }}" {{ (string) request('idCategoria') === (string) $c->id ? 'selected' : '' }}>
                            {{ $c->nombreCategoria }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="w-full md:w-40">
                <x-ui.select label="Horario" id="filtro-horario-select">
                    <option value="">Todos los horarios</option>
                    @foreach ($horariosDisponibles as $h)
                        <option value="{{ $h }}" {{ (string) request('horario') === (string) $h ? 'selected' : '' }}>
                            {{ $h }} hs
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <button type="button" id="reset-filters"
                class="self-end rounded-md px-1 py-2 text-sm font-semibold text-brand-navy hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                Limpiar todo
            </button>
        </div>

        @if (request()->filled('idCategoria') || request()->filled('idGrupo') || request()->filled('idFecha') || request()->filled('horario'))
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if (request()->filled('horario'))
                    <x-ui.filter-chip active
                        href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')])) }}">
                        Horario: {{ request('horario') }} hs
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('idCategoria') && isset($nombreCategoria))
                    <x-ui.filter-chip active
                        href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha'), 'horario' => request('horario')])) }}">
                        Categoría: {{ $nombreCategoria }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('idGrupo') && isset($nombreGrupo))
                    <x-ui.filter-chip active
                        href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idFecha' => request('idFecha'), 'horario' => request('horario')])) }}">
                        Grupo: {{ $nombreGrupo }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
                @if (request()->filled('idFecha') && isset($nombreFecha))
                    <x-ui.filter-chip active
                        href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'horario' => request('horario')])) }}">
                        Fecha: {{ $nombreFecha }}
                        <span aria-hidden="true">×</span>
                    </x-ui.filter-chip>
                @endif
            </div>
        @endif

        <p id="contador-partidos" class="mt-4 text-center text-sm text-gray-500">
            Mostrando {{ $totalPartidos }} {{ $totalPartidos === 1 ? 'partido' : 'partidos' }}
        </p>
    </div>

    @if ($partidos->isEmpty())
        <x-ui.empty-state class="mt-6" message="No se encontraron partidos con estos filtros"
            description="Probá cambiar los filtros o cargá un nuevo partido.">
            @if (isset($EdicionSeleccionada))
                <x-slot name="actions">
                    <x-ui.button href="{{ $urlNuevoPartido }}" variant="primary" size="sm">
                        Nuevo partido
                    </x-ui.button>
                </x-slot>
            @endif
        </x-ui.empty-state>
    @elseif ($agruparPorHorario)
        @foreach ($gruposPorHorario as $horarioGrupo => $partidosGrupo)
            <div class="mt-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    {{ $horarioGrupo === 'sin-horario' ? 'Sin horario asignado' : $horarioGrupo . ' hs' }}
                </h2>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($partidosGrupo as $p)
                        @include('Panel.partido._card', ['p' => $p])
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($partidos as $p)
                @include('Panel.partido._card', ['p' => $p])
            @endforeach
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var idEdicion = "{{ $EdicionSeleccionada->id }}";
            var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
            var idFecha = new URLSearchParams(window.location.search).get('idFecha') || '';
            var idGrupo = new URLSearchParams(window.location.search).get('idGrupo') || '';
            var horario = new URLSearchParams(window.location.search).get('horario') || '';

            var url = "{{ route('partido.index') }}";

            // Mostrar u ocultar filtros
            function toggleFiltersVisibility() {
                const fechaFilterContainer = document.querySelector('.filtro-fecha');
                const grupoFilterContainer = document.querySelector('.filtro-grupo');
                const categoriaFilterSelected = idCategoria.trim() !== '';
                const edicionFilterSelected = idEdicion.trim() !== '';

                // Mostrar el filtro de grupo solo si existe y se ha seleccionado una categoría
                if (grupoFilterContainer) {
                    grupoFilterContainer.classList.toggle('hidden', !categoriaFilterSelected);
                }

                // Mostrar el filtro de fecha siempre que exista una edición seleccionada.
                fechaFilterContainer.classList.toggle('hidden', !edicionFilterSelected);
            }

            // Actualizar la URL con los filtros seleccionados
            function updateUrl(params) {
                var newUrl = new URL(url);
                Object.keys(params).forEach(function(key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.location.href = newUrl;
            }

            // Añadir eventos a cada filtro de "Categoria"
            const categoriaSelect = document.getElementById('filtro-categoria-select');

            if (categoriaSelect) {
                categoriaSelect.addEventListener('change', function() {
                    idCategoria = this.value;
                    idGrupo = '';
                    idFecha = '';
                    toggleFiltersVisibility();
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            }

            const fechaSelect = document.getElementById('filtro-fecha-select');

            if (fechaSelect) {
                fechaSelect.addEventListener('change', function() {
                    idFecha = this.value;
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            }

            const grupoSelect = document.getElementById('filtro-grupo-select');

            if (grupoSelect) {
                grupoSelect.addEventListener('change', function() {
                    idGrupo = this.value;
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            }

            // Filtro de horario
            const horarioSelect = document.getElementById('filtro-horario-select');

            if (horarioSelect) {
                horarioSelect.addEventListener('change', function() {
                    const horarioValue = this.value;
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo,
                        horario: horarioValue
                    });
                });
            }

            // Añadir evento al botón de eliminar filtros
            document.getElementById('reset-filters').addEventListener('click', function() {
                updateUrl({
                    idCategoria: '',
                    idEdicion,
                    idFecha: '',
                    idGrupo: '',
                    horario: '',
                    busqueda: ''
                });
            });

            // Inicializar el estado de visibilidad de los filtros
            toggleFiltersVisibility();

            const buscadorInput = document.getElementById('buscar-equipo');
            const btnLimpiar = document.getElementById('limpiar-busqueda');
            const contadorPartidos = document.getElementById('contador-partidos');

            // Sincroniza el término de búsqueda en la URL sin recargar la página,
            // conservando el resto de los filtros activos.
            function actualizarBusquedaEnUrl(termino) {
                var params = new URLSearchParams(window.location.search);
                var valor = termino.trim();

                if (valor === '') {
                    params.delete('busqueda');
                } else {
                    params.set('busqueda', valor);
                }

                var queryString = params.toString();
                window.history.replaceState({}, '', window.location.pathname + (queryString ? '?' + queryString : ''));
            }

            function filtrarPorEquipo(termino) {
                const busqueda = termino.toLowerCase().trim();
                let partidosVisibles = 0;

                document.querySelectorAll('.partido-card').forEach(card => {
                    const local = card.dataset.local || '';
                    const visitante = card.dataset.visitante || '';
                    const coincide = busqueda === '' || local.includes(busqueda) || visitante.includes(busqueda);
                    card.classList.toggle('hidden', !coincide);
                    if (coincide) partidosVisibles++;
                });

                if (contadorPartidos) {
                    contadorPartidos.textContent = 'Mostrando ' + partidosVisibles + (partidosVisibles === 1 ? ' partido' : ' partidos');
                }

                if (btnLimpiar) {
                    btnLimpiar.classList.toggle('hidden', busqueda === '');
                }
            }

            if (buscadorInput) {
                buscadorInput.addEventListener('input', function() {
                    filtrarPorEquipo(this.value);
                    actualizarBusquedaEnUrl(this.value);
                });

                if (btnLimpiar) {
                    btnLimpiar.addEventListener('click', function() {
                        buscadorInput.value = '';
                        filtrarPorEquipo('');
                        actualizarBusquedaEnUrl('');
                        buscadorInput.focus();
                    });
                }

                filtrarPorEquipo(buscadorInput.value);
            }
        });
    </script>
@endsection
