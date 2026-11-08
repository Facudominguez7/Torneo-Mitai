{{--
Vista de Estadísticas Globales de Jugadores

Muestra todos los jugadores del torneo con sus equipos,
estadísticas globales y estadísticas por edición.
Incluye filtros por edición, categoría, umbral de participaciones y
búsqueda por nombre/DNI. Solo cuentan las participaciones reales (asistencia
en un partido efectivamente disputado, #202).
Ordenados por partidos jugados (mayor a menor).
--}}
@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Estadísticas de jugadores"
        subtitle="Vista completa de todos los jugadores ordenados por partidos jugados. Solo cuentan los jugadores activos en la lista de buena fe." />

    {{-- Contadores de Jugadores por Partidos (solo activos) --}}
    @if (isset($contadores))
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            <div class="min-w-[160px] rounded-lg border border-pa-linea bg-pa-superficie px-6 py-4 text-center">
                <span class="text-2xl font-bold text-pa-tinta">{{ $contadores['total'] }}</span>
                <p class="text-xs font-medium text-pa-tinta-suave">Jugadores activos</p>
            </div>
            <div class="min-w-[160px] rounded-lg border border-pa-linea bg-pa-superficie px-6 py-4 text-center">
                <span class="text-2xl font-bold text-amber-600">{{ $contadores['menos_de_umbral'] }}</span>
                <p class="text-xs font-medium text-pa-tinta-suave">Menos de {{ $contadores['umbral'] }}</p>
            </div>
            <div class="min-w-[160px] rounded-lg border border-pa-linea bg-pa-superficie px-6 py-4 text-center">
                <span class="text-2xl font-bold text-pa-exito">{{ $contadores['umbral_o_mas'] }}</span>
                <p class="text-xs font-medium text-pa-tinta-suave">{{ $contadores['umbral'] }} o más</p>
            </div>
        </div>
    @endif

    {{-- Conteo por Edición (histórico), como chips navegables --}}
    @if ($conteoPorEdicion->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-pa-tinta-cuerpo">Por edición:</span>
            @foreach ($conteoPorEdicion as $conteo)
                <x-ui.filter-chip
                    href="{{ route('estadisticas-jugadores', ['idEdicion' => $conteo->idEdicion]) }}"
                    :active="isset($EdicionSeleccionada) && $EdicionSeleccionada->id == $conteo->idEdicion">
                    {{ $conteo->nombre_edicion }} · {{ $conteo->total_jugadores }}
                </x-ui.filter-chip>
            @endforeach
        </div>
    @endif

    @php
        // Igual que en el markup original: las comillas que rodean la
        // búsqueda son texto literal de la plantilla (nunca pasan por
        // e()); solo el valor de $busqueda se escapa, con e() explícito
        // porque acá se arma como un string ya escapado para {!! !!}.
        $mensajeVacioJugadores = 'No se encontraron jugadores';
        if ($busqueda) {
            $descripcionVacioJugadores = 'No hay jugadores que coincidan con la búsqueda "'.e($busqueda).'".';
        } elseif ($desde !== null) {
            $descripcionVacioJugadores = 'No hay jugadores con '.$umbral.' o más participaciones'
                .(isset($EdicionSeleccionada) ? ' en la edición '.e($EdicionSeleccionada->nombre) : '').'.';
        } elseif (isset($EdicionSeleccionada)) {
            $descripcionVacioJugadores = 'No hay jugadores registrados en la edición '.e($EdicionSeleccionada->nombre).'.';
        } else {
            $descripcionVacioJugadores = 'No hay jugadores registrados en el sistema.';
        }
    @endphp

    <x-ui.data-table class="mt-6" :empty="$jugadoresData->isEmpty()">
        <x-slot name="filters">
            <form action="{{ route('estadisticas-jugadores') }}" method="GET" class="flex flex-wrap items-end gap-3">
                <div class="w-full md:w-56">
                    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
                        <option value="">Todas las ediciones</option>
                        @foreach ($ediciones as $edicion)
                            <option value="{{ $edicion->id }}" {{ isset($EdicionSeleccionada) && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                {{ $edicion->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                @if ($categorias->isNotEmpty())
                    <div class="w-full md:w-56">
                        <x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
                            <option value="">Todas las categorías</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" {{ (int) $idCategoria === $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->nombreCategoria }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @endif

                <div class="w-full md:w-44">
                    {{-- El umbral es libre (#202): cualquier entero del rango. Arranca
                         vacío para listar a todos; el KPI marca la referencia configurada. --}}
                    <label for="desde" class="mb-1 block text-sm font-medium text-pa-tinta-media">Participaciones mínimas</label>
                    <input type="number" name="desde" id="desde" value="{{ $desde }}" min="1" max="999"
                        step="1" inputmode="numeric"
                        class="block w-full rounded-lg border-pa-campo-borde bg-pa-superficie px-4 py-2 text-sm text-pa-tinta-media shadow-sm placeholder:text-pa-tinta-suave/60 focus:border-pa-resaltado focus:outline-none focus:ring-2 focus:ring-pa-resaltado/30 focus-visible:ring-2" />
                </div>

                <div class="min-w-[16rem] flex-1">
                    <x-ui.search label="Buscar por Nombre, Apellido o DNI" name="busqueda" :value="$busqueda ?? ''"
                        placeholder="Ej: García, Juan, 12345678" />
                </div>

                <x-ui.button type="submit" variant="primary">Buscar</x-ui.button>
                <x-ui.button href="{{ route('estadisticas-jugadores', array_filter(['idEdicion' => $EdicionSeleccionada?->id])) }}" variant="secondary">Limpiar</x-ui.button>
            </form>
        </x-slot>

        <x-slot name="emptyState">
            <div role="alert">
                <p class="text-lg font-bold text-pa-tinta">{{ $mensajeVacioJugadores }}</p>
                <p class="mt-2 text-pa-tinta-cuerpo">{!! $descripcionVacioJugadores !!}</p>
            </div>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">Jugador</th>
                <th scope="col">Equipo(s)</th>
                <th scope="col" class="text-center"><span title="Goles Totales">⚽ Goles</span></th>
                <th scope="col" class="text-center bg-brand-navy/5"><span title="Partidos Jugados">📋 Partidos</span></th>
                <th scope="col" class="text-center"><span title="Tarjetas Rojas">🟥 T. Rojas</span></th>
                <th scope="col" class="text-center">Detalles</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($jugadoresData as $jugador)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-navy/10">
                                <span class="text-sm font-semibold text-pa-resaltado">
                                    {{ strtoupper(substr($jugador->nombre, 0, 1) . substr($jugador->apellido, 0, 1)) }}
                                </span>
                            </div>
                            <div>
                                <p class="font-medium text-pa-tinta">{{ $jugador->apellido }}, {{ $jugador->nombre }}</p>
                                <p class="text-sm text-pa-tinta-suave">DNI: {{ $jugador->dni }}</p>
                            </div>
                        </div>
                    </td>

                    <td>
                        @if ($jugador->equipos && $jugador->equipos->isNotEmpty())
                            <div class="space-y-1">
                                @foreach ($jugador->equipos->take(3) as $equipo)
                                    <div class="text-sm">
                                        <span class="text-pa-tinta">{{ $equipo->nombre_equipo }}</span>
                                        <span class="ml-1 text-xs text-pa-tinta-suave">({{ $equipo->nombre_categoria }})</span>
                                    </div>
                                @endforeach
                                @if ($jugador->equipos->count() > 3)
                                    <p class="text-xs text-pa-tinta-suave">+{{ $jugador->equipos->count() - 3 }} más...</p>
                                @endif
                            </div>
                        @else
                            <span class="text-sm text-pa-tinta-suave">Sin equipo</span>
                        @endif
                    </td>

                    <td class="text-center">
                        <span @class([
                            'text-lg font-bold',
                            'text-pa-exito' => $jugador->goles_calculados > 0,
                            'text-pa-tinta-suave' => !$jugador->goles_calculados,
                        ])>
                            {{ $jugador->goles_calculados ?? 0 }}
                        </span>
                    </td>

                    <td class="text-center bg-brand-navy/5">
                        <span class="text-xl font-bold text-pa-resaltado">{{ $jugador->partidos_calculados }}</span>
                    </td>

                    <td class="text-center">
                        <span @class([
                            'text-lg font-bold',
                            'text-pa-peligro' => ($jugador->tarjetas_rojas_calculadas ?? 0) > 0,
                            'text-pa-tinta-suave' => !($jugador->tarjetas_rojas_calculadas ?? 0),
                        ])>
                            {{ $jugador->tarjetas_rojas_calculadas ?? 0 }}
                        </span>
                    </td>

                    <td class="text-center">
                        @if ($jugador->estadisticas_por_edicion && $jugador->estadisticas_por_edicion->isNotEmpty())
                            <button type="button" onclick="toggleDetalles('detalles-{{ $jugador->dni }}')"
                                class="inline-flex items-center rounded-full border border-pa-campo-borde px-3 py-1.5 text-xs font-medium text-pa-tinta-media shadow-sm hover:bg-pa-fila-bg focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-resaltado/50">
                                <svg class="mr-1 h-4 w-4 transition-transform" id="icon-{{ $jugador->dni }}"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true">
                                    <path d="M201.4 374.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 306.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z" />
                                </svg>
                                Por edición
                            </button>
                        @else
                            <span class="text-xs text-pa-tinta-suave">Sin datos</span>
                        @endif
                    </td>
                </tr>

                {{-- Fila expandible con estadísticas por edición --}}
                @if ($jugador->estadisticas_por_edicion && $jugador->estadisticas_por_edicion->isNotEmpty())
                    <tr id="detalles-{{ $jugador->dni }}" class="hidden bg-pa-fila-bg">
                        <td colspan="6" class="px-6 py-4">
                            <div class="ml-14">
                                <h4 class="mb-3 text-sm font-semibold text-pa-tinta-media">
                                    📊 Estadísticas por Edición
                                </h4>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($jugador->estadisticas_por_edicion as $stats)
                                        <div class="rounded-lg border border-pa-linea bg-pa-superficie p-3 shadow-sm">
                                            <p class="mb-2 text-sm font-medium text-pa-tinta">
                                                {{ $stats->nombre_edicion }}
                                            </p>
                                            <div class="grid grid-cols-3 gap-2 text-center">
                                                <div>
                                                    <p class="text-xs text-pa-tinta-suave">Goles</p>
                                                    <p class="text-lg font-bold text-pa-exito">{{ $stats->goles }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-pa-tinta-suave">Partidos</p>
                                                    <p class="text-lg font-bold text-pa-resaltado">{{ $stats->partidos }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-pa-tinta-suave">T. Rojas</p>
                                                    <p class="text-lg font-bold text-pa-peligro">{{ $stats->tarjetas_rojas }}</p>
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
        </x-slot>

        <x-slot name="footer">
            @unless ($jugadoresData->isEmpty())
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-pa-tinta-cuerpo">
                        Mostrando <span class="font-semibold">{{ $jugadoresData->firstItem() }}</span> -
                        <span class="font-semibold">{{ $jugadoresData->lastItem() }}</span> de
                        <span class="font-semibold">{{ $jugadoresData->total() }}</span> jugadores
                        <span class="italic text-pa-tinta-suave">· Ordenados por cantidad de partidos (mayor a menor)</span>
                    </p>
                    {{ $jugadoresData->appends(request()->except('page'))->links() }}
                </div>
            @endunless
        </x-slot>
    </x-ui.data-table>
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
