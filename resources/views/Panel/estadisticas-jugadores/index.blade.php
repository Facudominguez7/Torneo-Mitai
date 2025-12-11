{{--
Vista de Estadísticas Globales de Jugadores

Muestra todos los jugadores del torneo con sus equipos,
estadísticas globales y estadísticas por edición.
Incluye filtros por edición y búsqueda por nombre/DNI.
Ordenados por partidos jugados (mayor a menor).
--}}
@extends('Panel.admin')

@section('contenido')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-7xl mx-auto">
            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white flex items-center">
                    <svg class="w-8 h-8 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor">
                        <path
                            d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192l42.7 0c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0L21.3 320C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7l42.7 0C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3l-213.3 0zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352l117.3 0C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7l-330.7 0c-14.7 0-26.7-11.9-26.7-26.7z" />
                    </svg>
                    Estadísticas de Jugadores
                </h1>
                <p class="text-gray-300 mt-2">
                    Vista completa de todos los jugadores ordenados por partidos jugados
                </p>
            </div>

            {{-- Contadores de Jugadores por Partidos (solo activos) --}}
            @if(isset($contadores))
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-white mb-2">Jugadores Activos</h2>
                    <p class="text-sm text-gray-400 mb-4">Solo jugadores con estado activo en Lista de Buena Fe</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Total --}}
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium opacity-90">Total de Jugadores</p>
                                    <p class="text-4xl font-bold mt-1">{{ $contadores['total'] }}</p>
                                </div>
                                <svg class="w-12 h-12 opacity-75" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"
                                    fill="currentColor">
                                    <path
                                        d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192l42.7 0c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0L21.3 320C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7l42.7 0C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3l-213.3 0zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352l117.3 0C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7l-330.7 0c-14.7 0-26.7-11.9-26.7-26.7z" />
                                </svg>
                            </div>
                        </div>

                        {{-- 5 partidos o menos --}}
                        <div class="bg-gradient-to-r from-orange-400 to-orange-500 rounded-lg shadow-lg p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium opacity-90">5 partidos o menos</p>
                                    <p class="text-4xl font-bold mt-1">{{ $contadores['cinco_o_menos'] }}</p>
                                </div>
                                <svg class="w-12 h-12 opacity-75" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                                    fill="currentColor">
                                    <path
                                        d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM184 232l144 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-144 0c-13.3 0-24-10.7-24-24s10.7-24 24-24z" />
                                </svg>
                            </div>
                        </div>

                        {{-- Más de 5 partidos --}}
                        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium opacity-90">Más de 5 partidos</p>
                                    <p class="text-4xl font-bold mt-1">{{ $contadores['mas_de_cinco'] }}</p>
                                </div>
                                <svg class="w-12 h-12 opacity-75" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                                    fill="currentColor">
                                    <path
                                        d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344l0-64-64 0c-13.3 0-24-10.7-24-24s10.7-24 24-24l64 0 0-64c0-13.3 10.7-24 24-24s24 10.7 24 24l0 64 64 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-64 0 0 64c0 13.3-10.7 24-24 24s-24-10.7-24-24z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Cards de Conteo por Edición (histórico) --}}
            @if($conteoPorEdicion->isNotEmpty())
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-white mb-2">Histórico por Edición</h2>
                    <p class="text-sm text-gray-400 mb-4">Todos los jugadores que participaron en partidos</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                        @foreach($conteoPorEdicion as $conteo)
                                <a href="{{ route('estadisticas-jugadores', ['idEdicion' => $conteo->idEdicion]) }}"
                                    class="block transform transition-transform hover:scale-105">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 text-center
                                        {{ isset($EdicionSeleccionada) && $EdicionSeleccionada->id == $conteo->idEdicion
                            ? 'ring-4 ring-blue-500 bg-blue-50 dark:bg-blue-900'
                            : '' }}">
                                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 truncate">
                                            {{ $conteo->nombre_edicion }}
                                        </p>
                                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                                            {{ $conteo->total_jugadores }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">jugadores</p>
                                    </div>
                                </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Filtros y Búsqueda --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <form action="{{ route('estadisticas-jugadores') }}" method="GET"
                    class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {{-- Filtro de Edición --}}
                    <div>
                        <label for="idEdicion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Edición
                        </label>
                        <select id="idEdicion" name="idEdicion"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Todas las ediciones</option>
                            @foreach($ediciones as $edicion)
                                <option value="{{ $edicion->id }}" {{ isset($EdicionSeleccionada) && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                    {{ $edicion->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Búsqueda por Nombre/DNI --}}
                    <div class="md:col-span-2">
                        <label for="busqueda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar por Nombre, Apellido o DNI
                        </label>
                        <input type="text" id="busqueda" name="busqueda" value="{{ $busqueda ?? '' }}"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Ej: García, Juan, 12345678">
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                                fill="currentColor">
                                <path
                                    d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
                            </svg>
                            Buscar
                        </button>
                        <a href="{{ route('estadisticas-jugadores') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            {{-- Tabla de Jugadores --}}
            @if($jugadoresData->isEmpty())
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg" role="alert">
                    <p class="font-bold text-lg">No se encontraron jugadores</p>
                    <p class="mt-2">
                        @if($busqueda)
                            No hay jugadores que coincidan con la búsqueda "{{ $busqueda }}".
                        @elseif(isset($EdicionSeleccionada))
                            No hay jugadores registrados en la edición {{ $EdicionSeleccionada->nombre }}.
                        @else
                            No hay jugadores registrados en el sistema.
                        @endif
                    </p>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    {{-- Contador de resultados y nota de ordenamiento --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Mostrando <span class="font-semibold">{{ $jugadoresData->firstItem() }}</span> -
                                <span class="font-semibold">{{ $jugadoresData->lastItem() }}</span> de
                                <span class="font-semibold">{{ $jugadoresData->total() }}</span> jugadores
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                                Ordenados por cantidad de partidos (mayor a menor)
                            </p>
                        </div>
                    </div>

                    {{-- Tabla responsive --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Jugador
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Equipo(s)
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        <span title="Goles Totales">⚽ Goles</span>
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-blue-50 dark:bg-blue-900">
                                        <span title="Partidos Jugados">📋 Partidos</span>
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        <span title="Tarjetas Rojas">🟥 T. Rojas</span>
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Detalles
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($jugadoresData as $jugador)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        {{-- Jugador --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="flex-shrink-0 h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                                    <span class="text-blue-600 dark:text-blue-300 font-semibold text-sm">
                                                        {{ strtoupper(substr($jugador->nombre, 0, 1) . substr($jugador->apellido, 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $jugador->apellido }}, {{ $jugador->nombre }}
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        DNI: {{ $jugador->dni }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Equipos (puede tener varios) --}}
                                        <td class="px-6 py-4">
                                            @if($jugador->equipos && $jugador->equipos->isNotEmpty())
                                                <div class="space-y-1">
                                                    @foreach($jugador->equipos->take(3) as $equipo)
                                                        <div class="text-sm">
                                                            <span class="text-gray-900 dark:text-white">{{ $equipo->nombre_equipo }}</span>
                                                            <span
                                                                class="text-xs text-gray-500 dark:text-gray-400 ml-1">({{ $equipo->nombre_categoria }})</span>
                                                        </div>
                                                    @endforeach
                                                    @if($jugador->equipos->count() > 3)
                                                        <p class="text-xs text-gray-400">+{{ $jugador->equipos->count() - 3 }} más...</p>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-sm">Sin equipo</span>
                                            @endif
                                        </td>

                                        {{-- Goles --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span
                                                class="text-lg font-bold {{ $jugador->goles_calculados > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}">
                                                {{ $jugador->goles_calculados ?? 0 }}
                                            </span>
                                        </td>

                                        {{-- Partidos (columna resaltada) --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-center bg-blue-50 dark:bg-blue-900/30">
                                            <span class="text-xl font-bold text-blue-600 dark:text-blue-400">
                                                {{ $jugador->partidos_calculados }}
                                            </span>
                                        </td>

                                        {{-- Tarjetas Rojas --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span
                                                class="text-lg font-bold {{ ($jugador->tarjetas_rojas_calculadas ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                                                {{ $jugador->tarjetas_rojas_calculadas ?? 0 }}
                                            </span>
                                        </td>

                                        {{-- Botón de detalles --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            @if($jugador->estadisticas_por_edicion && $jugador->estadisticas_por_edicion->isNotEmpty())
                                                <button type="button" onclick="toggleDetalles('detalles-{{ $jugador->dni }}')"
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <svg class="w-4 h-4 mr-1 transition-transform" id="icon-{{ $jugador->dni }}"
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                                                        <path
                                                            d="M201.4 374.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 306.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z" />
                                                    </svg>
                                                    Ver
                                                </button>
                                            @else
                                                <span class="text-gray-400 text-xs">Sin datos</span>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Fila expandible con estadísticas por edición --}}
                                    @if($jugador->estadisticas_por_edicion && $jugador->estadisticas_por_edicion->isNotEmpty())
                                        <tr id="detalles-{{ $jugador->dni }}" class="hidden bg-gray-50 dark:bg-gray-900">
                                            <td colspan="6" class="px-6 py-4">
                                                <div class="ml-14">
                                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                                        📊 Estadísticas por Edición
                                                    </h4>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        @foreach($jugador->estadisticas_por_edicion as $stats)
                                                            <div
                                                                class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow border border-gray-200 dark:border-gray-700">
                                                                <p class="font-medium text-gray-800 dark:text-gray-200 text-sm mb-2">
                                                                    {{ $stats->nombre_edicion }}
                                                                </p>
                                                                <div class="grid grid-cols-3 gap-2 text-center">
                                                                    <div>
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Goles</p>
                                                                        <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                                                            {{ $stats->goles }}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Partidos</p>
                                                                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                                            {{ $stats->partidos }}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">T. Rojas</p>
                                                                        <p class="text-lg font-bold text-red-600 dark:text-red-400">
                                                                            {{ $stats->tarjetas_rojas }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación --}}
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        {{ $jugadoresData->appends(request()->except('page'))->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /**
         * Toggle para mostrar/ocultar la fila de detalles de un jugador.
         * 
         * @param {string} id - ID del elemento a mostrar/ocultar
         */
        function toggleDetalles(id) {
            const elemento = document.getElementById(id);
            const icono = document.getElementById('icon-' + id.replace('detalles-', ''));

            if (elemento) {
                elemento.classList.toggle('hidden');

                // Rotar el icono
                if (icono) {
                    if (elemento.classList.contains('hidden')) {
                        icono.style.transform = 'rotate(0deg)';
                    } else {
                        icono.style.transform = 'rotate(180deg)';
                    }
                }
            }
        }
    </script>
@endsection