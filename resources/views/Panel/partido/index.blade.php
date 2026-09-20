@extends('Panel.admin')

@section('contenido')
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
        <div class="mb-2 flex flex-col gap-2 px-2 sm:flex-row sm:flex-wrap sm:items-center">
            <a href="{{ $urlNuevoPartido }}"
                class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold bg-emerald-600 text-white shadow-md transition hover:bg-emerald-500 sm:w-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo partido
            </a>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                @if (isset($idCategoria) && $idCategoria)
                    <a href="{{ route('partido.create-interzonal', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria]) }}"
                        class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold border border-amber-300 bg-amber-100 text-amber-900 transition hover:bg-amber-200 sm:w-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Partido Interzonal
                    </a>
                @endif
                <button type="button" onclick="imprimirEnIframe('{{ $urlImprimirArbitros }}')"
                    class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir Planilla Árbitros
                </button>
            </div>
        </div>
    @endif
    <div class="rounded-lg bg-white p-3 sm:p-4 md:p-6">
        <h1 class="text-xl font-bold sm:text-2xl">Partidos de Futbol</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 px-0 sm:px-2">
            <div class="relative filtro-fecha mt-2 w-full text-left md:ml-5 md:mt-0 md:w-[18rem] hidden">
                <select id="filtro-fecha-select"
                    class="w-full appearance-none rounded-full bg-gray-800 py-2 pl-4 pr-10 text-sm text-white transition-all duration-300 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 sm:text-base">
                    <option value="">Filtrar por fecha</option>
                    @foreach ($fechas as $f)
                        <option value="{{ $f->id }}" {{ (string) request('idFecha') === (string) $f->id ? 'selected' : '' }}>
                            {{ $f->nombre }}
                        </option>
                    @endforeach
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            @if ($mostrarFiltroGrupo)
            <div class="relative filtro-grupo mt-2 w-full text-left md:mt-0 md:w-[18rem]">
                <select id="filtro-grupo-select"
                    class="w-full appearance-none rounded-full bg-gray-800 py-2 pl-4 pr-10 text-sm text-white transition-all duration-300 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 sm:text-base">
                    <option value="">Filtrar por Grupo</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" {{ (string) request('idGrupo') === (string) $g->id ? 'selected' : '' }}>
                            {{ $g->nombre }}
                        </option>
                    @endforeach
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            @endif
            <div class="relative filtro-categoria mt-2 w-full md:mt-0 md:w-[18rem]">
                <select id="filtro-categoria-select"
                    class="w-full appearance-none rounded-full bg-gray-800 py-2 pl-4 pr-10 text-sm text-white transition-all duration-300 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 sm:text-base">
                    <option value="">Seleccionar Categoria</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id }}" {{ (string) request('idCategoria') === (string) $c->id ? 'selected' : '' }}>
                            {{ $c->nombreCategoria }}
                        </option>
                    @endforeach
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            {{-- Filtro de horario --}}
            <div class="relative mt-2 w-full md:mt-0 md:w-[12rem]">
                <select id="filtro-horario-select"
                    class="w-full appearance-none rounded-full bg-gray-800 py-2 pl-4 pr-10 text-sm text-white transition-all duration-300 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 sm:text-base">
                    <option value="">Todos los horarios</option>
                    @foreach ($horariosDisponibles as $h)
                        <option value="{{ $h }}" {{ (string) request('horario') === (string) $h ? 'selected' : '' }}>
                            {{ $h }} hs
                        </option>
                    @endforeach
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="mt-2 inline-block w-full items-center md:ml-2 md:mt-0 md:w-auto">
                <button id="reset-filters" class="btn btn-eliminar w-full md:w-auto">
                    Eliminar Filtros
                </button>
            </div>
            {{-- Buscador por nombre de equipo --}}
            <div class="relative mt-2 inline-block w-full items-center md:ml-2 md:mt-0 md:w-auto">
                <input type="text" id="buscar-equipo" name="busqueda" value="{{ request('busqueda') }}" placeholder="Buscar equipo..."
                    class="w-full md:w-64 rounded-full border border-gray-300 bg-white py-2 px-4 pl-10 text-sm text-gray-700 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <button type="button" id="limpiar-busqueda" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="mt-5 mb-0 flex flex-col items-center justify-center gap-2 px-2 text-center md:flex-row">
            @if (isset($nombreFecha))
                <div class="md:mr-5">
                    <h1 class="text-2xl font-bold sm:text-3xl">{{ $nombreFecha }}</h1>
                </div>
            @endif
            @if (isset($nombreGrupo))
                <div class="md:mr-5">
                    <h1 class="text-2xl font-bold sm:text-3xl">{{ $nombreGrupo }}</h1>
                </div>
            @endif
            @if (isset($nombreCategoria))
                <div class="md:mr-5">
                    <h1 class="text-2xl font-bold sm:text-3xl">{{ $nombreCategoria }}</h1>
                </div>
            @endif

        </div>

        @if (request()->filled('idCategoria') || request()->filled('idGrupo') || request()->filled('idFecha') || request()->filled('horario'))
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                @if (request()->filled('horario'))
                    <a href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')])) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-cyan-100 px-3 py-1 text-sm font-semibold text-cyan-800 hover:bg-cyan-200">
                        Horario: {{ request('horario') }} hs
                        <span aria-hidden="true">x</span>
                    </a>
                @endif
                @if (request()->filled('idCategoria') && isset($nombreCategoria))
                    <a href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha'), 'horario' => request('horario')])) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800 hover:bg-blue-200">
                        Categoría: {{ $nombreCategoria }}
                        <span aria-hidden="true">x</span>
                    </a>
                @endif
                @if (request()->filled('idGrupo') && isset($nombreGrupo))
                    <a href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idFecha' => request('idFecha'), 'horario' => request('horario')])) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-800 hover:bg-green-200">
                        Grupo: {{ $nombreGrupo }}
                        <span aria-hidden="true">x</span>
                    </a>
                @endif
                @if (request()->filled('idFecha') && isset($nombreFecha))
                    <a href="{{ route('partido.index', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'horario' => request('horario')])) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-purple-100 px-3 py-1 text-sm font-semibold text-purple-800 hover:bg-purple-200">
                        Fecha: {{ $nombreFecha }}
                        <span aria-hidden="true">x</span>
                    </a>
                @endif
            </div>
        @endif

        <p id="contador-partidos" class="mt-4 text-center text-sm text-gray-500">
            Mostrando {{ $partidos->count() }} {{ $partidos->count() === 1 ? 'partido' : 'partidos' }}
        </p>
    </div>
    <div class="mt-2 rounded-lg bg-white p-3 sm:p-4 md:p-6">
        @foreach ($partidos as $p)
            @php
                $resultadoCargado = $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null;
                $estadoGolesLabel = null;
                $estadoGolesClase = '';

                if ($p->goles_confirmados_at !== null) {
                    $estadoGolesLabel = 'Goles confirmados';
                    $estadoGolesClase = 'bg-emerald-100 text-emerald-700';
                } elseif ($resultadoCargado) {
                    $estadoGolesLabel = 'Goles pendientes de carga';
                    $estadoGolesClase = 'bg-amber-100 text-amber-700';
                }
            @endphp
            <div class="partido-card grid gap-4 bg-gray-300 p-3 sm:p-4 md:p-6" data-local="{{ strtolower($p->equipoLocal->nombre) }}" data-visitante="{{ strtolower($p->equipoVisitante->nombre) }}">
                <div class="bg-card rounded-lg shadow-xl bg-white">
                    <div class="px-4 md:px-6 py-3 text-muted-foreground grid grid-cols-1 gap-2">
                        <div class="text-sm md:text-base text-center font-semibold text-gray-600 uppercase tracking-wide flex items-center justify-center gap-2 flex-wrap">
                            {{ $p->fecha->nombre }}
                            @if ($p->es_interzonal)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-semibold">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Interzonal
                                </span>
                            @endif
                        </div>
                        {{-- Información de Categoría y Grupo --}}
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            @if ($p->categoria)
                                <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $p->categoria->nombreCategoria }}
                                </span>
                            @endif
                            @if ($p->grupo)
                                <span class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $p->grupo->nombre }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-col md:flex-row items-center justify-center gap-4">
                            <div class="flex flex-col items-center gap-2">
                                <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" width="60" height="60" alt="{{ $p->equipoLocal->nombre }}" class="w-14 h-14 md:w-[60px] md:h-[60px] rounded-full object-cover" />
                                <div class="text-base md:text-xl font-bold text-center leading-tight">{{ $p->equipoLocal->nombre }}</div>
                            </div>
                            <div class="text-sm md:text-lg font-semibold text-muted-foreground">vs</div>
                            <div class="flex flex-col items-center gap-2">
                                <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="60" height="60" alt="{{ $p->equipoVisitante->nombre }}" class="w-14 h-14 md:w-[60px] md:h-[60px] rounded-full object-cover" />
                                <div class="text-base md:text-xl font-bold text-center leading-tight">{{ $p->equipoVisitante->nombre }}</div>
                            </div>
                        </div>
                        <div class="text-2xl md:text-3xl text-center font-extrabold text-gray-900">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</div>
                        <div class="flex flex-col md:flex-row items-center justify-center md:justify-around gap-2 text-sm md:text-lg text-gray-600">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ is_null($p->horario) ? \Carbon\Carbon::parse($p->horario_datetime)->format('d-m-Y H:i') : $p->horario . ' PM' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">Cancha {{ $p->cancha }}</span>
                            </div>
                        </div>
                        @if ($estadoGolesLabel)
                            <div class="flex justify-center">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $estadoGolesClase }}">
                                    {{ $estadoGolesLabel }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 gap-2 px-3 py-3 sm:grid-cols-2 md:mx-auto md:max-w-2xl md:grid-cols-4 md:px-4">
                        <a href="{{ route('cargar-resultado', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')]) }}"
                            class="inline-flex h-9 w-full items-center justify-center rounded-full bg-blue-500 px-4 text-center text-sm font-semibold text-white transition hover:bg-blue-600">
                            {{ $p->golesEquipoLocal === null && $p->golesEquipoVisitante === null ? 'Cargar Resultado' : 'Editar Resultado' }}
                        </a>
                        <a href="{{ route('partido.edit', ['partido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')]) }}"
                            class="inline-flex h-9 w-full items-center justify-center rounded-full bg-yellow-500 px-4 text-center text-sm font-semibold text-white transition hover:bg-yellow-600">
                            Editar
                        </a>
                        <form action="{{ route('partido.destroy', array_filter(['partido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha'), 'horario' => request('horario'), 'busqueda' => request('busqueda')])) }}" method="POST" class="flex h-9 w-full" onsubmit="return confirm('¿Eliminar este partido? Se revertirán posiciones, historial y estadísticas asociadas.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-full bg-red-500 px-4 text-center text-sm font-semibold text-white transition hover:bg-red-600">
                                Eliminar
                            </button>
                        </form>
                        <a href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0, 'origen' => 'partido-index', 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha'), 'horario_filtro' => request('horario'), 'busqueda' => request('busqueda')]) }}"
                            class="inline-flex h-9 w-full items-center justify-center rounded-full bg-indigo-500 px-4 text-center text-sm font-semibold text-white transition hover:bg-indigo-600">
                            Planillas
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    </div>

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
