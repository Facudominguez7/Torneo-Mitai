@extends('Panel.admin')

@section('contenido')
    @php
        // El controlador nunca pasó $idEdicion/$idCategoria a la vista (solo
        // $EdicionSeleccionada, que ya viene resuelta de $request->idEdicion);
        // se derivan acá desde el request(), igual que el resto de esta
        // vista ya lee request()->search_value directamente, sin tocar el
        // controlador.
        $idEdicion = $EdicionSeleccionada->id ?? request('idEdicion');
        $idCategoria = request('idCategoria');
    @endphp

    <x-ui.page-header title="Tabla de goleadores" subtitle="Se arma sola con los goles cargados de cada partido.">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => $tipo]) }}" variant="secondary">
                    Corrección manual
                </x-ui.button>
                <x-ui.button
                    href="{{ route('tabla-goleadores', array_filter(['idEdicion' => $idEdicion, 'idCategoria' => $idCategoria])) }}"
                    variant="primary" target="_blank" rel="noopener">
                    Ver en el portal
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    @if (isset($EdicionSeleccionada) && isset($categorias) && $categorias->isNotEmpty())
        <div class="mt-6 flex flex-wrap gap-2" aria-label="Categoría">
            <x-ui.filter-chip href="{{ route('tabla_goleador.index', ['idEdicion' => $idEdicion]) }}" :active="empty($idCategoria)">
                Todas
            </x-ui.filter-chip>
            @foreach ($categorias as $categoria)
                <x-ui.filter-chip
                    href="{{ route('tabla_goleador.index', ['idEdicion' => $idEdicion, 'idCategoria' => $categoria->id]) }}"
                    :active="(string) $idCategoria === (string) $categoria->id">
                    {{ $categoria->nombreCategoria }}
                </x-ui.filter-chip>
            @endforeach
        </div>
    @endif

    @php
        // El podio es un resumen visual de los primeros tres puestos; la
        // tabla de abajo sigue listando TODAS las filas (incluidas esas
        // mismas tres) para no perder Editar/Eliminar/Ver jugador en ningún
        // puesto. Solo tiene sentido cuando el orden es "todo goles dentro
        // de una sola categoría" (idCategoria activo), en la primera
        // página y SIN búsqueda activa: con search_value el orden deja de
        // ser el ranking real de goles de la categoría y pasa a ser el
        // orden relativo a los resultados filtrados (revisión
        // R3-podio-busqueda).
        $todasLasFilas = collect($goleadores_t->items())->values();
        $searchValue = request('search_value');
        $mostrarPodio = isset($EdicionSeleccionada) && !empty($idCategoria) && empty($searchValue) && $goleadores_t->currentPage() === 1 && $todasLasFilas->isNotEmpty();
        $filasPodio = $mostrarPodio ? $todasLasFilas->slice(0, 3)->values() : collect();
        $rankBase = ($goleadores_t->currentPage() - 1) * $goleadores_t->perPage();
    @endphp

    @if ($mostrarPodio)
        <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach ($filasPodio as $index => $g)
                @php $posicion = $index + 1; @endphp
                <article data-podio-posicion="{{ $posicion }}"
                    @class([
                        'flex items-center gap-4 rounded-xl border p-4',
                        'border-brand-gold bg-brand-gold/10' => $posicion === 1,
                        'border-gray-200 bg-white' => $posicion !== 1,
                    ])
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-gold/40 text-lg font-extrabold text-brand-navy">
                        {{ $posicion }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-gray-900">{{ $g->nombre }}</p>
                        <p class="truncate text-sm text-gray-500">{{ $g->nombreEquipo }}</p>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="text-2xl font-extrabold text-brand-navy">{{ $g->cantidadGoles }}</span>
                        <span class="text-xs text-gray-500">goles</span>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <x-ui.data-table class="mt-6" :empty="$goleadores_t->isEmpty()">
        <x-slot name="filters">
            <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[16rem] flex-1">
                    <x-ui.search label="Buscar" labelHidden name="search_value"
                        :value="old('search_value', request()->search_value)" placeholder="Buscar..." />
                </div>
                <x-ui.button type="submit" variant="primary">Buscar</x-ui.button>
                @foreach (request()->except('search_value') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
            </form>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">#</th>
                <th scope="col">Jugador</th>
                <th scope="col">Equipo</th>
                @if (empty($idCategoria))
                    <th scope="col">Categoría</th>
                @endif
                <th scope="col" class="text-center">Goles</th>
                <th scope="col" class="text-center">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($todasLasFilas as $index => $g)
                @php $rank = $rankBase + $index + 1; @endphp
                <tr>
                    {{-- Con búsqueda activa el orden ya no es el ranking real
                         de goles, sino el orden relativo a los resultados
                         filtrados: mostrar un número acá sería un puesto
                         engañoso, así que se muestra un guion neutral y se
                         omite el atributo data-posicion (revisión
                         R3-podio-busqueda). --}}
                    @if (empty($searchValue))
                        <td data-posicion="{{ $rank }}" class="font-semibold text-gray-500">{{ $rank }}</td>
                    @else
                        <td class="font-semibold text-gray-500">—</td>
                    @endif
                    <td class="font-semibold">{{ $g->nombre }}</td>
                    <td>{{ $g->nombreEquipo }}</td>
                    @if (empty($idCategoria))
                        <td>{{ $g->nombreCategoria }}</td>
                    @endif
                    <td class="text-center font-extrabold text-brand-navy">{{ $g->cantidadGoles }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button href="{{ route('jugador.consulta', ['dni' => $g->dni_jugador]) }}" variant="secondary" size="sm">
                                Ver jugador
                            </x-ui.button>
                            <x-ui.button href="{{ route('tabla_goleador.edit', $g) }}" variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('tabla_goleador.destroy', $g) }}"
                                entity="a {{ $g->nombre }} de la tabla de goleadores"
                                :fields="['idEdicion' => $g->idEdicion]"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-center">
                {{ $goleadores_t->links() }}
            </div>
        </x-slot>
    </x-ui.data-table>
@endsection
