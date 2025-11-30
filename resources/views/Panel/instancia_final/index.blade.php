@extends('Panel.admin')

@section('contenido')
    @if (isset($EdicionSeleccionada))
        <div class="flex flex-row justify-center mb-2">
            <div>
                <a
                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'instancia_final']) }}">
                    <button
                        class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                        Agregar Partido
                    </button>
                </a>
            </div>
        </div>
    @endif
    <div class="bg-white p-6">
        <h1 class="text-2xl font-bold">Tumbadas</h1>
        <div class="mt-2">
            <div x-data="{ open: false }" class="relative inline-block text-left ml-5 filtro-fase">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 text-black bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-full transition-all duration-300">
                            Filtrar por fase
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach ($fases as $f)
                            <div class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                                data-id="{{ $f->id }}" data-nombre="{{ $f->nombre }}">
                                {{ $f->nombre }}
                            </div>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            </div>
            <div x-data="{ open: false }" class="relative inline-block text-left filtro-categoria">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 text-black bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-full transition-all duration-300">
                            Seleccionar Categoria
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
            <div class="ml-5 inline-block items-center mt-4">
                <button id="reset-filters" class="btn btn-eliminar">
                    Eliminar Filtros
                </button>
            </div>
        </div>
        <div class="flex items-center justify-center flex-row mt-5 mb-0">
            @if (isset($nombreFase))
                <div class="mr-5">
                    <h1 class="text-4xl font-bold">{{ $nombreFase }}</h1>
                </div>
            @endif
            @if (isset($nombreCategoria))
                <div class="mr-5">
                    <h1 class="text-4xl font-bold">{{ $nombreCategoria }}</h1>
                </div>
            @endif
        </div>
    </div>
    <div class="bg-white p-6">
        @foreach ($partidos as $p)
            <div class="grid border-4 border-black gap-4">
                @if (!$idCategoria || isset($idFase))
                    <div class="px-6 text-muted-foreground text-center">
                        <div class="font-bold text-4xl">{{ $p->categoria->nombreCategoria }}</div>
                    </div>
                @endif
                <div class="px-6 text-muted-foreground text-center">
                    <div class="font-bold">ID Partido: {{ $p->id }}</div>
                    <div class="font-bold text-3xl">{{ $p->fase->nombre }}</div>
                    @if (isset($p->copa->nombre))
                        <span class="font-bold">{{ $p->copa->nombre }}</span>
                    @endif
                </div>
                <div class="bg-card rounded-lg shadow-sm">
                    <div class="flex flex-col md:flex-row items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-2">
                            <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" width="40" height="40"
                                alt="{{ $p->equipoLocal->nombre }}" class="rounded-full"
                                style="aspect-ratio: 40/40; object-fit: cover;" />
                            <div class="text-lg">{{ $p->equipoLocal->nombre }}</div>
                            <div class="text-muted-foreground">vs</div>
                            <div class="text-lg">{{ $p->equipoVisitante->nombre }}</div>
                            <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="40"
                                height="40" alt="{{ $p->equipoVisitante->nombre }}" class="rounded-full"
                                style="aspect-ratio: 40/40; object-fit: cover;" />
                        </div>
                        <div class="flex flex-col text-center text-2xl font-bold mt-4 md:mt-0">
                            <span>{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                            @if (
                                $p->golesEquipoLocal !== null &&
                                    $p->golesEquipoVisitante !== null &&
                                    $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                    $p->penalesEquipoLocal !== null &&
                                    $p->penalesEquipoVisitante !== null)
                                <span>({{ $p->penalesEquipoLocal }} - {{ $p->penalesEquipoVisitante }})</span>
                            @endif
                            @if (!is_null($p->resultadoGlobal))
                                <div class="text-2xl font-bold mt-4 md:mt-0 text-center text-[--color-primary]">
                                    Resultado Global: {{ $p->resultadoGlobal }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row items-center justify-between px-6 py-2 text-muted-foreground">
                        <div class="flex items-center gap-2 mb-2 md:mb-0">
                            <span class="text-xl">{{ \Carbon\Carbon::parse($p->horario)->format('d-m-Y H:i') }}</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <span>Cancha {{ $p->cancha }}</span>
                        </div>
                    </div>
                    @if ($p->golesEquipoLocal === null && $p->golesEquipoVisitante === null)
                        <div class="px-6 py-4 flex justify-center">
                            <a
                                href="{{ route('cargar-resultado-instancia', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final']) }}">
                                <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full">
                                    Cargar Resultado
                                </button>
                            </a>
                        </div>
                    @endif
                    <div class="flex justify-center my-4 gap-2">
                        <a
                            href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0 ]) }}">
                            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Planillas
                            </button>
                        </a>
                        <a href="{{ route('instancia_final.edit', $p->id) }}">
                            <button class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                Editar
                            </button>
                        </a>
                        <form action="{{ route('instancia_final.destroy', ['instancia_final' => $p->id, 'idEdicion' => $EdicionSeleccionada->id]) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este partido?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <hr class="border-4">
        @endforeach
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var idEdicion = "{{ $EdicionSeleccionada->id }}";
            var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
            var idFase = new URLSearchParams(window.location.search).get('idFase') || '';

            var url = "{{ route('instancia_final.index') }}";

            // Mostrar u ocultar filtros
            function toggleFiltersVisibility() {
                const faseFilterContainer = document.querySelector('.filtro-fase');
                const categoriaFilterContainer = document.querySelector('.filtro-categoria');

                // Mostrar ambos filtros
                faseFilterContainer.classList.remove('hidden');
                categoriaFilterContainer.classList.remove('hidden');
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
                        idFase: '', // Resetear el filtro de fase al seleccionar una categoría
                    });
                });
            });

            // Añadir eventos a cada filtro de "Fase"
            document.querySelectorAll('.filtro-fase [data-id]').forEach(function(item) {
                item.addEventListener('click', function() {
                    idFase = this.getAttribute('data-id');
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        idFase,
                    });
                });
            });

            // Añadir evento al botón de eliminar filtros
            document.getElementById('reset-filters').addEventListener('click', function() {
                updateUrl({
                    idCategoria: '',
                    idEdicion,
                    idFase: '',
                });
            });

            // Inicializar el estado de visibilidad de los filtros
            toggleFiltersVisibility();
        });
    </script>
@endsection
