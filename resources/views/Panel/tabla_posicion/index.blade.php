@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Tabla de posiciones" subtitle="Se recalcula cada vez que confirmás un partido.">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('tabla_posicion.create', $parametrosRegreso) }}" variant="primary">
                    Agregar Posición
                </x-ui.button>
                <x-ui.button href="{{ route('tabla-posiciones', $parametrosRegreso) }}" variant="secondary"
                    target="_blank" rel="noopener">
                    Ver en el portal
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    @if (isset($EdicionSeleccionada))
        @if ($categorias->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2" aria-label="Categoría">
                @foreach ($categorias as $categoria)
                    <x-ui.filter-chip
                        href="{{ route('tabla_posicion.index', ['idEdicion' => $idEdicion, 'idCategoria' => $categoria->id, 'idGrupo' => $idGrupo]) }}"
                        :active="(string) $idCategoria === (string) $categoria->id">
                        {{ $categoria->nombreCategoria }}
                    </x-ui.filter-chip>
                @endforeach
            </div>
        @endif

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
            <form action="{{ route('tabla_posicion.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                <div class="w-full md:w-64">
                    <x-ui.select label="Grupo" name="idGrupo" id="idGrupo">
                        <option value="">Todos los Grupos</option>
                        @foreach ($grupos as $grupo)
                            <option value="{{ $grupo->id }}" {{ (string) $idGrupo === (string) $grupo->id ? 'selected' : '' }}>
                                {{ $grupo->categoria?->nombreCategoria ? $grupo->categoria->nombreCategoria . ' · ' : '' }}{{ $grupo->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="primary">
                    Filtrar
                </x-ui.button>
                @if (!empty($idGrupo) || !empty($idCategoria))
                    <x-ui.button href="{{ route('tabla_posicion.index', ['idEdicion' => $idEdicion]) }}" variant="secondary">
                        Limpiar
                    </x-ui.button>
                @endif
            </form>
        </div>
    @endif

    <div class="mt-6 space-y-4">
        @if ($tablaPosiciones->isEmpty())
            <x-ui.empty-state message="No existen registros" />
        @endif

        <div class="grid grid-cols-1 gap-4">
            @foreach ($tablaPosicionesPorGrupo as $idGrupoFila => $filasGrupo)
                @php
                    $nombreGrupo = $filasGrupo->first()?->grupo?->nombre ?? 'Grupo sin nombre';
                @endphp

                <div>
                    <h2 class="mb-2 text-base font-bold text-gray-800">{{ $nombreGrupo }}</h2>

                    <x-ui.data-table>
                        <x-slot name="head">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Equipo</th>
                                <th scope="col" class="text-center">PJ</th>
                                <th scope="col" class="text-center">G</th>
                                <th scope="col" class="text-center">E</th>
                                <th scope="col" class="text-center">P</th>
                                <th scope="col" class="text-center">GF</th>
                                <th scope="col" class="text-center">GC</th>
                                <th scope="col" class="text-center">DG</th>
                                <th scope="col" class="text-center" title="Goles en contra generales de la edición">GC Gral</th>
                                <th scope="col" class="text-center">Pts</th>
                                <th scope="col" class="text-center">Acción</th>
                            </tr>
                        </x-slot>

                        <x-slot name="body">
                            @foreach ($filasGrupo as $fila)
                                @php $rank = $loop->iteration; @endphp
                                <tr>
                                    <td>
                                        <span data-posicion="{{ $rank }}"
                                            @if ($rank <= 2) data-destacado="clasifica" @endif
                                            @class([
                                                'inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                                                'bg-brand-gold/40 text-brand-navy' => $rank <= 2,
                                                'bg-gray-100 text-gray-600' => $rank > 2,
                                            ])
                                        >{{ $rank }}</span>
                                    </td>
                                    <td class="font-semibold">{{ $fila->equipo?->nombre ?? '-' }}</td>
                                    <td class="text-center">{{ $fila->jugado }}</td>
                                    <td class="text-center">{{ $fila->ganado }}</td>
                                    <td class="text-center">{{ $fila->empatado }}</td>
                                    <td class="text-center">{{ $fila->perdido }}</td>
                                    <td class="text-center">{{ $fila->golesFavor }}</td>
                                    <td class="text-center">{{ $fila->golesContra }}</td>
                                    <td class="text-center">{{ $fila->diferenciaGoles }}</td>
                                    <td class="text-center">{{ $fila->golesContraGeneral ?? '-' }}</td>
                                    <td class="text-center font-extrabold text-brand-navy">{{ $fila->puntos }}</td>
                                    <td>
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            <x-ui.button
                                                href="{{ route('tabla_posicion.edit', array_merge(['tabla_posicion' => $fila], $parametrosRegreso)) }}"
                                                variant="success" size="sm">
                                                Editar
                                            </x-ui.button>
                                            <x-ui.confirm-delete size="sm"
                                                action="{{ route('tabla_posicion.destroy', $fila) }}"
                                                entity="la posición de {{ $fila->equipo?->nombre ?? 'el equipo' }}"
                                                :fields="[
                                                    'idEdicion' => $fila->idEdicion,
                                                    'idCategoria' => $fila->grupo?->idCategoria,
                                                    'idGrupo' => $fila->idGrupo,
                                                ]"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot>
                    </x-ui.data-table>
                </div>
            @endforeach
        </div>
    </div>
@endsection
