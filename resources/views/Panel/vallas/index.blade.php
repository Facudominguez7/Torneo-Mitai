{{--
    Vallas menos vencidas — ranking (T8c, panel-admin-redesign): equipos de
    la edición ordenados por goles en contra, igual criterio que
    ControladorHome::tablaVallas (la versión pública). Solo lectura: cargar o
    cambiar el arquero se hace desde Premios individuales.
--}}
@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Vallas menos vencidas" subtitle="Se calcula con los goles en contra de cada equipo.">
        @if ($EdicionSeleccionada)
            <x-slot name="actions">
                <x-ui.button
                    href="{{ route('tabla-vallas', array_filter(['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria])) }}"
                    variant="primary" target="_blank" rel="noopener">
                    Ver en el portal
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición para ver el ranking de vallas"
            description="Elegí una edición en la barra superior para ver los equipos ordenados por goles en contra." />
    @else
        @if ($categorias->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2" aria-label="Categoría">
                <x-ui.filter-chip href="{{ route('vallas.index', ['idEdicion' => $EdicionSeleccionada->id]) }}" :active="empty($idCategoria)">
                    Todas
                </x-ui.filter-chip>
                @foreach ($categorias as $categoria)
                    <x-ui.filter-chip
                        href="{{ route('vallas.index', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoria->id]) }}"
                        :active="(string) $idCategoria === (string) $categoria->id">
                        {{ $categoria->nombreCategoria }}
                    </x-ui.filter-chip>
                @endforeach
            </div>
        @endif

        <x-ui.data-table class="mt-6" :empty="$equipos->isEmpty()">
            <x-slot name="emptyState">
                <x-ui.empty-state message="Todavía no hay equipos cargados en esta edición"
                    description="El ranking se arma solo con los goles en contra que va cargando cada partido." />
            </x-slot>

            <x-slot name="head">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Equipo</th>
                    @if (empty($idCategoria))
                        <th scope="col">Categoría</th>
                    @endif
                    <th scope="col" class="text-center">Goles en contra</th>
                    <th scope="col">Arquero</th>
                </tr>
            </x-slot>

            <x-slot name="body">
                @foreach ($equipos as $indice => $equipoEdicion)
                    @php
                        $posicion = $indice + 1;
                        $arquero = $arquerosPorEquipo->get($equipoEdicion->idEquipo);
                    @endphp
                    <tr data-posicion="{{ $posicion }}">
                        <td>
                            @if ($posicion === 1)
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-gold/40 text-xs font-extrabold text-brand-navy">
                                    1
                                </span>
                            @else
                                <span class="font-semibold text-gray-500">{{ $posicion }}</span>
                            @endif
                        </td>
                        <td class="font-semibold">{{ $equipoEdicion->equipo->nombre ?? 'Equipo sin nombre' }}</td>
                        @if (empty($idCategoria))
                            <td>{{ $equipoEdicion->categoria->nombreCategoria ?? '' }}</td>
                        @endif
                        <td class="text-center font-extrabold text-brand-navy">{{ $equipoEdicion->golesContra }}</td>
                        <td class="{{ $arquero ? 'text-gray-800' : 'text-gray-400' }}">
                            {{ $arquero->nombre ?? 'Sin definir' }}
                        </td>
                    </tr>
                @endforeach
            </x-slot>
        </x-ui.data-table>
    @endif
@endsection
