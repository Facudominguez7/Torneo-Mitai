{{--
    Podio (T8b, panel-admin-redesign): fusión de Campeones y Subcampeones.
    Por categoría y copa muestra lo guardado (con editar/borrar, igual que
    los listados viejos) y, cuando la final ya se jugó y todavía no hay nada
    cargado, una sugerencia para confirmar en un click (crea campeón y
    subcampeón en una transacción — ver ControladorPodio::confirmar).
--}}
@extends('Panel.admin')

@section('contenido')
    @php
        $variantePorEstado = [
            'definido' => 'success',
            'final_jugada' => 'warning',
            'en_juego' => 'info',
            'sin_instancias' => 'neutral',
        ];
    @endphp

    <x-ui.page-header title="Podio"
        subtitle="Campeones y subcampeones de cada copa, por categoría. Se sugieren solos cuando se juega la final.">
        <x-slot name="actions">
            @if ($EdicionSeleccionada)
                <x-ui.button href="{{ route('campeones', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                    variant="secondary" target="_blank" rel="noopener">
                    Ver en el portal
                </x-ui.button>
            @endif
        </x-slot>
    </x-ui.page-header>

    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición para ver el podio"
            description="Elegí una edición en la barra superior para cargar o confirmar campeones y subcampeones." />
    @elseif (! $tieneContenido)
        <x-ui.empty-state class="mt-6" message="Todavía no hay instancias finales ni podio cargado en esta edición"
            description="Cargá una final en Instancias finales para que Podio pueda sugerir el campeón y el subcampeón.">
            <x-slot name="actions">
                <x-ui.button href="{{ route('instancia_final.index', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                    variant="primary" size="sm">
                    Ir a Instancias finales
                </x-ui.button>
            </x-slot>
        </x-ui.empty-state>
    @else
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($categorias as $categoria)
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-bold text-gray-800">{{ $categoria['nombreCategoria'] }}</h2>
                        <x-ui.badge :variant="$variantePorEstado[$categoria['estado']['tipo']] ?? 'neutral'">
                            {{ $categoria['estado']['label'] }}
                        </x-ui.badge>
                    </div>

                    <div class="mt-4 space-y-5">
                        @foreach ($categoria['copas'] as $copa)
                            <div class="rounded-lg border border-gray-100 p-3">
                                <h3 class="mb-2 text-sm font-semibold text-gray-600">{{ $copa['nombreCopa'] }}</h3>

                                <div class="space-y-3 text-sm">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">1° Campeón</p>
                                        @forelse ($copa['campeones'] as $campeon)
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <span class="text-gray-800">{{ $campeon->equipo->nombre ?? 'Equipo sin nombre' }}</span>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    <x-ui.button href="{{ route('campeon.edit', $campeon) }}" variant="secondary" size="sm">
                                                        Editar
                                                    </x-ui.button>
                                                    <x-ui.confirm-delete
                                                        action="{{ route('campeon.destroy', $campeon) }}"
                                                        :fields="['idEdicion' => $campeon->idEdicion]"
                                                        entity="el campeón de {{ $copa['nombreCopa'] }} en {{ $categoria['nombreCategoria'] }}"
                                                        size="sm" />
                                                </div>
                                            </div>
                                        @empty
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <span class="text-gray-400">Sin cargar</span>
                                                <x-ui.button
                                                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoria['idCategoria'], 'tipo' => 'campeon']) }}"
                                                    variant="secondary" size="sm">
                                                    Cargar campeón
                                                </x-ui.button>
                                            </div>
                                        @endforelse
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">2° Subcampeón</p>
                                        @forelse ($copa['subcampeones'] as $subcampeon)
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <span class="text-gray-800">{{ $subcampeon->equipo->nombre ?? 'Equipo sin nombre' }}</span>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    <x-ui.button href="{{ route('subcampeon.edit', $subcampeon) }}" variant="secondary" size="sm">
                                                        Editar
                                                    </x-ui.button>
                                                    <x-ui.confirm-delete
                                                        action="{{ route('subcampeon.destroy', $subcampeon) }}"
                                                        :fields="['idEdicion' => $subcampeon->idEdicion]"
                                                        entity="el subcampeón de {{ $copa['nombreCopa'] }} en {{ $categoria['nombreCategoria'] }}"
                                                        size="sm" />
                                                </div>
                                            </div>
                                        @empty
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <span class="text-gray-400">Sin cargar</span>
                                                <x-ui.button
                                                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoria['idCategoria'], 'tipo' => 'subcampeon']) }}"
                                                    variant="secondary" size="sm">
                                                    Cargar subcampeón
                                                </x-ui.button>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($copa['sugerencia'])
                                    <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3">
                                        <p class="text-sm font-semibold text-emerald-800">Sugerido por el resultado de la final</p>
                                        <p class="mt-1 text-sm text-emerald-700">
                                            Campeón: {{ $copa['sugerencia']['nombreCampeon'] }} · Subcampeón: {{ $copa['sugerencia']['nombreSubcampeon'] }}
                                        </p>
                                        <form action="{{ route('podio.confirmar') }}" method="POST" class="mt-2">
                                            @csrf
                                            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                            <input type="hidden" name="idCategoria" value="{{ $categoria['idCategoria'] }}">
                                            <input type="hidden" name="idCopa" value="{{ $copa['idCopa'] }}">
                                            <x-ui.button type="submit" variant="success" size="sm">
                                                Confirmar
                                            </x-ui.button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
