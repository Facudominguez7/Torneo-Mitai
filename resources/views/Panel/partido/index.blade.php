@extends('Panel.admin')

@section('contenido')
    @if (isset($EdicionSeleccionada))
        <div class="flex flex-row justify-center mb-2 px-2 gap-2 flex-wrap">
            <div>
                <a href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'partido']) }}">
                    <button
                        class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg w-full md:w-auto">
                        Agregar Partido
                    </button>
                </a>
            </div>
            @if (isset($idCategoria) && $idCategoria)
                <div>
                    <a href="{{ route('partido.create-interzonal', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria]) }}">
                        <button
                            class="bg-amber-500 hover:bg-amber-600 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg w-full md:w-auto flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Partido Interzonal
                        </button>
                    </a>
                </div>
            @endif
        </div>
    @endif
    <div class="bg-white p-6">
        <h1 class="text-2xl font-bold">Partidos de Fútbol</h1>
        <div class="mt-2 px-2">
            <div x-data="{ open: false }" class="relative text-left md:ml-5 filtro-fecha hidden">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 w-full md:w-auto">
                            Filtrar por fecha
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach ($fechas as $f)
                            <div class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                                data-id="{{ $f->id }}" data-nombre="{{ $f->nombre }}">
                                {{ $f->nombre }}
                            </div>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            </div>
            <div x-data="{ open: false }" class="relative text-left filtro-grupo hidden">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 w-full md:w-auto">
                            Filtrar por Grupo
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach ($grupos as $g)
                            <div class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                                data-id="{{ $g->id }}" data-nombre="{{ $g->nombre }}">
                                {{ $g->nombre }}
                            </div>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            </div>
            <div x-data="{ open: false }" class="relative inline-block text-left filtro-categoria w-full md:w-auto">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 w-full md:w-auto">
                            Seleccionar Categoria
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach ($categorias as $c)
                            <div class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                                data-id="{{ $c->id }}" data-nombre="{{ $c->nombreCategoria }}">
                                {{ $c->nombreCategoria }}
                            </div>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            </div>
            <div class="md:ml-5 inline-block items-center mt-4 w-full md:w-auto">
                <button id="reset-filters" class="btn btn-eliminar w-full md:w-auto">
                    Eliminar Filtros
                </button>
            </div>
        </div>
        <div class="flex items-center justify-center flex-col md:flex-row gap-2 mt-5 mb-0 px-2 text-center">
            @if (isset($nombreFecha))
                <div class="md:mr-5">
                    <h1 class="text-3xl font-bold">{{ $nombreFecha }}</h1>
                </div>
            @endif
            @if (isset($nombreGrupo))
                <div class="md:mr-5">
                    <h1 class="text-3xl font-bold">{{ $nombreGrupo }}</h1>
                </div>
            @endif
            @if (isset($nombreCategoria))
                <div class="md:mr-5">
                    <h1 class="text-3xl font-bold">{{ $nombreCategoria }}</h1>
                </div>
            @endif

        </div>

    </div>
    <div class="bg-white p-4 md:p-6">
        @foreach ($partidos as $p)
            <div class="grid gap-4 p-4 md:p-6 bg-gray-300">
                <div class="bg-card rounded-lg shadow-xl bg-white">
                    <div class="px-4 md:px-6 py-3 text-muted-foreground grid grid-cols-1 gap-2">
                        <div class="text-sm md:text-base text-center font-semibold text-gray-600 uppercase tracking-wide flex items-center justify-center gap-2">
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
                    </div>
                    <div class="px-4 md:px-6 py-4 grid grid-cols-1 md:flex md:justify-center md:items-center gap-3">
                        <a href="{{ route('cargar-resultado', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                            class="inline-flex justify-center">
                            <button class="w-full md:w-auto bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-full transition">
                                {{ $p->golesEquipoLocal === null && $p->golesEquipoVisitante === null ? 'Cargar Resultado' : 'Editar Resultado' }}
                            </button>
                        </a>
                        <a href="{{ route('partido.edit', $p->id) }}" class="inline-flex justify-center">
                            <button class="w-full md:w-auto bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-full transition">
                                Editar
                            </button>
                        </a>
                        <a href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0]) }}" class="inline-flex justify-center">
                            <button class="w-full md:w-auto bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2 px-4 rounded-full transition">
                                Planillas
                            </button>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-center mt-5">
        {{ $partidos->links() }}
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var idEdicion = "{{ $EdicionSeleccionada->id }}";
            var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
            var idFecha = new URLSearchParams(window.location.search).get('idFecha') || '';
            var idGrupo = new URLSearchParams(window.location.search).get('idGrupo') || '';

            var url = "{{ route('partido.index') }}";

            // Mostrar u ocultar filtros
            function toggleFiltersVisibility() {
                const fechaFilterContainer = document.querySelector('.filtro-fecha');
                const grupoFilterContainer = document.querySelector('.filtro-grupo');
                const categoriaFilterSelected = idCategoria.trim() !== '';
                const grupoFilterSelected = idGrupo.trim() !== '';

                // Mostrar el filtro de grupo solo si se ha seleccionado una categoría
                grupoFilterContainer.classList.toggle('hidden', !categoriaFilterSelected);

                // Mostrar el filtro de fecha solo si se ha seleccionado una categoría y un grupo
                fechaFilterContainer.classList.toggle('hidden', !(categoriaFilterSelected && grupoFilterSelected));
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
            document.querySelectorAll('.filtro-categoria [data-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    idCategoria = this.getAttribute('data-id');
                    document.querySelectorAll('.filtro-categoria [data-id]').forEach(function(i) {
                        i.classList.remove('active');
                    });
                    this.classList.add('active');
                    toggleFiltersVisibility();
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            });

            // Añadir eventos a cada filtro de "Fecha"
            document.querySelectorAll('.filtro-fecha [data-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    idFecha = this.getAttribute('data-id');
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            });

            // Añadir eventos a cada filtro de "Grupo"
            document.querySelectorAll('.filtro-grupo [data-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    idGrupo = this.getAttribute('data-id');
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFecha,
                        idGrupo
                    });
                });
            });

            // Añadir evento al botón de eliminar filtros
            document.getElementById('reset-filters').addEventListener('click', function() {
                updateUrl({
                    idCategoria: '',
                    idEdicion,
                    idFecha: '',
                    idGrupo: ''
                });
            });

            // Inicializar el estado de visibilidad de los filtros
            toggleFiltersVisibility();
        });
    </script>
@endsection
