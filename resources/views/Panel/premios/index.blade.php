{{--
    Premios individuales (T8c, panel-admin-redesign): fusión de Goleador del
    torneo y Valla menos vencida. Por categoría muestra lo guardado (con
    Cambiar/Eliminar, igual que los listados viejos) y, cuando todavía no hay
    nada cargado, una sugerencia (tabla de goleadores / golesContra) para
    elegir y guardar en un click — ver SugerenciaPremios y
    ControladorPremios::guardarGoleador/guardarValla.
--}}
@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Premios individuales"
        subtitle="Goleador del torneo y valla menos vencida de cada categoría. Elegí de la sugerencia o cargalo a mano." />

    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición para ver los premios"
            description="Elegí una edición en la barra superior para cargar o cambiar el goleador y la valla de cada categoría." />
    @elseif (! $tieneContenido)
        <x-ui.empty-state class="mt-6" message="Todavía no hay tabla de goleadores ni goles en contra cargados en esta edición"
            description="Cargá resultados de partidos para que Premios individuales pueda sugerir goleador y valla." />
    @else
        <x-ui.data-table class="mt-6">
            <x-slot name="head">
                <tr>
                    <th scope="col">Categoría</th>
                    <th scope="col">Goleador del torneo</th>
                    <th scope="col">Valla menos vencida</th>
                </tr>
            </x-slot>

            <x-slot name="body">
                @foreach ($categorias as $categoria)
                    <tr class="align-top">
                        <td class="font-bold text-gray-800">{{ $categoria['nombreCategoria'] }}</td>

                        <td>
                            <div class="space-y-2">
                                @forelse ($categoria['goleador']['guardados'] as $goleador)
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ $goleador->nombre }}</p>
                                            <p class="text-xs text-gray-500">{{ $goleador->equipo->nombre ?? 'Equipo sin nombre' }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <x-ui.button href="{{ route('goleador.edit', $goleador) }}" variant="secondary" size="sm">
                                                Cambiar
                                            </x-ui.button>
                                            <x-ui.confirm-delete
                                                action="{{ route('goleador.destroy', $goleador) }}"
                                                :fields="['idEdicion' => $goleador->idEdicion]"
                                                entity="a {{ $goleador->nombre }} como goleador de {{ $categoria['nombreCategoria'] }}"
                                                size="sm" />
                                        </div>
                                    </div>
                                @empty
                                    @if ($categoria['goleador']['sugerencias']->isNotEmpty())
                                        <form action="{{ route('premios.goleador') }}" method="POST" class="space-y-2">
                                            @csrf
                                            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                            <input type="hidden" name="idCategoria" value="{{ $categoria['idCategoria'] }}">
                                            <label for="dni_jugador_{{ $categoria['idCategoria'] }}" class="sr-only">
                                                Goleador sugerido para {{ $categoria['nombreCategoria'] }}
                                            </label>
                                            <x-ui.select id="dni_jugador_{{ $categoria['idCategoria'] }}" name="dni_jugador">
                                                @foreach ($categoria['goleador']['sugerencias'] as $sugerencia)
                                                    <option value="{{ $sugerencia['dni_jugador'] }}" {{ $sugerencia['esSugerida'] ? 'selected' : '' }}>
                                                        {{ $sugerencia['label'] }}
                                                    </option>
                                                @endforeach
                                            </x-ui.select>
                                            <x-ui.button type="submit" variant="success" size="sm">
                                                Guardar
                                            </x-ui.button>
                                        </form>
                                    @else
                                        <p class="text-sm text-gray-400">Sin sugerencia disponible todavía</p>
                                    @endif
                                @endforelse

                                <x-ui.button
                                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoria['idCategoria'], 'tipo' => 'goleador']) }}"
                                    variant="secondary" size="sm">
                                    Cargar a mano
                                </x-ui.button>
                            </div>
                        </td>

                        <td>
                            <div class="space-y-2">
                                @forelse ($categoria['valla']['guardados'] as $valla)
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ $valla->nombre }}</p>
                                            <p class="text-xs text-gray-500">{{ $valla->equipo->nombre ?? 'Equipo sin nombre' }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <x-ui.button href="{{ route('valla.edit', $valla) }}" variant="secondary" size="sm">
                                                Cambiar
                                            </x-ui.button>
                                            <x-ui.confirm-delete
                                                action="{{ route('valla.destroy', $valla) }}"
                                                :fields="['idEdicion' => $valla->idEdicion]"
                                                entity="a {{ $valla->nombre }} como valla de {{ $categoria['nombreCategoria'] }}"
                                                size="sm" />
                                        </div>
                                    </div>
                                @empty
                                    @if ($categoria['valla']['sugerencias']->isNotEmpty())
                                        <form action="{{ route('premios.valla') }}" method="POST" class="space-y-2">
                                            @csrf
                                            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                            <input type="hidden" name="idCategoria" value="{{ $categoria['idCategoria'] }}">
                                            <label for="idEquipo_{{ $categoria['idCategoria'] }}" class="sr-only">
                                                Equipo sugerido para la valla de {{ $categoria['nombreCategoria'] }}
                                            </label>
                                            <x-ui.select id="idEquipo_{{ $categoria['idCategoria'] }}" name="idEquipo">
                                                @foreach ($categoria['valla']['sugerencias'] as $sugerencia)
                                                    <option value="{{ $sugerencia['idEquipo'] }}" {{ $sugerencia['esSugerida'] ? 'selected' : '' }}>
                                                        {{ $sugerencia['label'] }}
                                                    </option>
                                                @endforeach
                                            </x-ui.select>
                                            <label for="nombre_arquero_{{ $categoria['idCategoria'] }}" class="mb-1 mt-2 block text-xs font-medium text-gray-700">
                                                Arquero
                                            </label>
                                            <input type="text" id="nombre_arquero_{{ $categoria['idCategoria'] }}" name="nombre"
                                                required maxlength="50" placeholder="Nombre del arquero"
                                                class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm text-gray-700 shadow-sm focus:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-navy/30">
                                            <x-ui.button type="submit" variant="success" size="sm">
                                                Guardar
                                            </x-ui.button>
                                        </form>
                                    @else
                                        <p class="text-sm text-gray-400">Sin sugerencia disponible todavía</p>
                                    @endif
                                @endforelse

                                <x-ui.button
                                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoria['idCategoria'], 'tipo' => 'valla']) }}"
                                    variant="secondary" size="sm">
                                    Cargar a mano
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot>
        </x-ui.data-table>

        <p class="mt-3 text-xs text-gray-400">
            Las sugerencias salen de la tabla de goleadores y de los goles en contra de cada equipo.
        </p>
    @endif
@endsection
