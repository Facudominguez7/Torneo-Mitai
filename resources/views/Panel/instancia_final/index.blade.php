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
        <h1 class="text-2xl font-bold">Instancias Finales</h1>

        {{-- Barra de búsqueda por equipo --}}
        <div class="mt-4 mb-4">
            <div class="flex flex-wrap gap-3 items-center">
                <div class="flex-1 min-w-[250px] max-w-md">
                    <div class="relative">
                        <input type="text" id="busqueda-equipo" placeholder="Buscar por nombre de equipo..."
                            value="{{ $busquedaEquipo ?? '' }}"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                <button id="btn-buscar"
                    class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition-all">
                    Buscar
                </button>
            </div>
        </div>

        {{-- Filtros por estado (Pendientes/Jugados) --}}
        <div class="mt-4 mb-4">
            <div class="flex flex-wrap gap-2">
                <button data-estado=""
                    class="filtro-estado px-4 py-2 rounded-full text-sm font-medium transition-all {{ !isset($estado) || $estado === '' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Todos
                </button>
                <button data-estado="pendiente"
                    class="filtro-estado px-4 py-2 rounded-full text-sm font-medium transition-all {{ ($estado ?? '') === 'pendiente' ? 'bg-yellow-500 text-white' : 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' }}">
                    🕐 Pendientes
                </button>
                <button data-estado="jugado"
                    class="filtro-estado px-4 py-2 rounded-full text-sm font-medium transition-all {{ ($estado ?? '') === 'jugado' ? 'bg-green-500 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                    ✅ Jugados
                </button>
            </div>
        </div>

        {{-- Filtros agrupados --}}
        <div class="mt-4 flex flex-wrap gap-3 items-center">
            {{-- Filtro por fase --}}
            @if($fases->count() > 0)
                <select id="filtro-fase"
                    class="bg-white border border-gray-300 text-gray-700 py-2.5 px-4 rounded-lg text-sm font-medium shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                    <option value="">📋 Todas las fases</option>
                    @foreach ($fases as $f)
                        <option value="{{ $f->id }}" {{ request('idFase') == $f->id ? 'selected' : '' }}>{{ $f->nombre }}</option>
                    @endforeach
                </select>
            @endif

            {{-- Filtro por categoría --}}
            @if($categorias->count() > 0)
                <select id="filtro-categoria"
                    class="bg-white border border-gray-300 text-gray-700 py-2.5 px-4 rounded-lg text-sm font-medium shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                    <option value="">🏷️ Todas las categorías</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id }}" {{ request('idCategoria') == $c->id ? 'selected' : '' }}>
                            {{ $c->nombreCategoria }}
                        </option>
                    @endforeach
                </select>
            @endif

            {{-- Filtro por copa --}}
            @if(isset($copas) && $copas->count() > 0)
                <select id="filtro-copa"
                    class="bg-white border border-amber-300 text-amber-700 py-2.5 px-4 rounded-lg text-sm font-medium shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 cursor-pointer">
                    <option value="">🏆 Todas las copas</option>
                    @foreach ($copas as $copa)
                        <option value="{{ $copa->id }}" {{ request('idCopa') == $copa->id ? 'selected' : '' }}>{{ $copa->nombre }}
                        </option>
                    @endforeach
                </select>
            @endif

            {{-- Filtro por fecha --}}
            @if(isset($fechasDisponibles) && $fechasDisponibles->count() > 0)
                <select id="filtro-fecha"
                    class="bg-white border border-green-300 text-green-700 py-2.5 px-4 rounded-lg text-sm font-medium shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer">
                    <option value="">📅 Todas las fechas</option>
                    @foreach ($fechasDisponibles as $fecha)
                        <option value="{{ $fecha }}" {{ request('fecha') == $fecha ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
            @endif

            {{-- Botón eliminar filtros - solo si hay filtros activos --}}
            @if(request('idCategoria') || request('idFase') || request('idCopa') || request('estado') || request('busqueda') || request('fecha'))
                <button id="reset-filters"
                    class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white py-2.5 px-4 rounded-lg text-sm font-medium transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Limpiar
                </button>
            @endif
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

        {{-- Estadísticas resumen --}}
        @php
            $totalPartidos = $partidos->count();
            $partidosJugados = $partidos->filter(fn($p) => $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null)->count();
            $partidosPendientes = $totalPartidos - $partidosJugados;
            $partidosAgrupados = $partidos->groupBy(fn($p) => $p->categoria->nombreCategoria ?? 'Sin categoría');
        @endphp

        <div class="bg-white p-6 border-t">
            <div class="flex flex-wrap gap-4 justify-center mb-4">
                <div class="bg-blue-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-blue-600">{{ $totalPartidos }}</span>
                    <p class="text-xs text-blue-600 font-medium">Total Partidos</p>
                </div>
                <div class="bg-green-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-green-600">{{ $partidosJugados }}</span>
                    <p class="text-xs text-green-600 font-medium">Jugados</p>
                </div>
                <div class="bg-yellow-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-yellow-600">{{ $partidosPendientes }}</span>
                    <p class="text-xs text-yellow-600 font-medium">Pendientes</p>
                </div>
            </div>


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
                            <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="40" height="40"
                                alt="{{ $p->equipoVisitante->nombre }}" class="rounded-full"
                                style="aspect-ratio: 40/40; object-fit: cover;" />
                        </div>
                        <div class="flex flex-col text-center text-2xl font-bold mt-4 md:mt-0">
                            <span>{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                            @if (
                                    $p->golesEquipoLocal !== null &&
                                    $p->golesEquipoVisitante !== null &&
                                    $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                    $p->penalesEquipoLocal !== null &&
                                    $p->penalesEquipoVisitante !== null
                                )
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
                                href="{{ route('cargar-resultado-instancia', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final', 'fecha' => request('fecha')]) }}">
                                <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full">
                                    Cargar Resultado
                                </button>
                            </a>
                        </div>
                    @endif
                    <div class="flex justify-center my-4 gap-2">
                        <a
                            href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0, 'fecha' => request('fecha')]) }}">
                            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Planillas
                            </button>
                        </a>
                        <a
                            href="{{ route('instancia_final.edit', ['instancia_final' => $p->id, 'fecha' => request('fecha')]) }}">
                            <button class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                Editar
                            </button>
                        </a>
                        <form
                            action="{{ route('instancia_final.destroy', ['instancia_final' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'fecha' => request('fecha')]) }}"
                            method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este partido?');">
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
        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
            var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
            var idFase = new URLSearchParams(window.location.search).get('idFase') || '';
            var idCopa = new URLSearchParams(window.location.search).get('idCopa') || '';
            var estado = new URLSearchParams(window.location.search).get('estado') || '';
            var busqueda = new URLSearchParams(window.location.search).get('busqueda') || '';
            var fecha = new URLSearchParams(window.location.search).get('fecha') || '';

            var url = "{{ route('instancia_final.index') }}";


            // Actualizar la URL con los filtros seleccionados
            function updateUrl(params) {
                var newUrl = new URL(url, window.location.origin);
                Object.keys(params).forEach(function (key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.location.href = newUrl;
            }

            // Filtro por Fase (select)
            var selectFase = document.getElementById('filtro-fase');
            if (selectFase) {
                selectFase.addEventListener('change', function () {
                    idFase = this.value;
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            }

            // Filtro por Categoría (select)
            var selectCategoria = document.getElementById('filtro-categoria');
            if (selectCategoria) {
                selectCategoria.addEventListener('change', function () {
                    idCategoria = this.value;
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            }

            // Filtro por Copa (select)
            var selectCopa = document.getElementById('filtro-copa');
            if (selectCopa) {
                selectCopa.addEventListener('change', function () {
                    idCopa = this.value;
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            }

            // Filtro por Fecha (select)
            var selectFecha = document.getElementById('filtro-fecha');
            if (selectFecha) {
                selectFecha.addEventListener('change', function () {
                    fecha = this.value;
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            }

            // Añadir eventos a cada filtro de "Estado" (Pendiente/Jugado)
            document.querySelectorAll('.filtro-estado').forEach(function (item) {
                item.addEventListener('click', function () {
                    estado = this.getAttribute('data-estado');
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            });

            // Búsqueda por equipo
            var inputBusqueda = document.getElementById('busqueda-equipo');
            var btnBuscar = document.getElementById('btn-buscar');

            if (btnBuscar) {
                btnBuscar.addEventListener('click', function () {
                    busqueda = inputBusqueda.value.trim();
                    updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                });
            }

            // Buscar al presionar Enter
            if (inputBusqueda) {
                inputBusqueda.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        busqueda = inputBusqueda.value.trim();
                        updateUrl({ idCategoria, idEdicion, idFase, idCopa, estado, busqueda, fecha });
                    }
                });
            }

            // Añadir evento al botón de eliminar filtros (solo si existe)
            var resetBtn = document.getElementById('reset-filters');
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    updateUrl({ idCategoria: '', idEdicion, idFase: '', idCopa: '', estado: '', busqueda: '', fecha: '' });
                });
            }

        });
    </script>
@endsection