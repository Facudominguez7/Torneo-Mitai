@extends('Panel.admin')

@section('contenido')
    <div class="flex justify-center">
        @if (isset($EdicionSeleccionada))
            <div class="flex flex-row justify-center mb-2">
                <a href="{{ route('tabla_posicion.create', $parametrosRegreso) }}">
                    <button
                        class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                        Agregar Posición
                    </button>
                </a>
            </div>
        @endif
    </div>

    @if (isset($EdicionSeleccionada))
        @if ($categorias->isNotEmpty())
            <div class="mx-auto mb-4 w-full max-w-5xl px-2 sm:px-6 lg:px-8">
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Ver por categoría</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categorias as $categoria)
                            <a href="{{ route('tabla_posicion.index', ['idEdicion' => $idEdicion, 'idCategoria' => $categoria->id, 'idGrupo' => $idGrupo]) }}"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition {{ (string) $idCategoria === (string) $categoria->id ? 'bg-white text-slate-900' : 'bg-slate-800/70 text-white hover:bg-slate-700' }}">
                                {{ $categoria->nombreCategoria }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-center mb-4">
            <form action="{{ route('tabla_posicion.index') }}" method="GET" class="flex flex-wrap justify-center gap-3">
                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                <select name="idGrupo" class="border border-gray-300 rounded-lg px-4 py-2">
                    <option value="">Todos los Grupos</option>
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo->id }}" {{ (string) $idGrupo === (string) $grupo->id ? 'selected' : '' }}>
                            {{ $grupo->categoria?->nombreCategoria ? $grupo->categoria->nombreCategoria . ' · ' : '' }}{{ $grupo->nombre }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Filtrar
                </button>
                @if (!empty($idGrupo) || !empty($idCategoria))
                    <a href="{{ route('tabla_posicion.index', ['idEdicion' => $idEdicion]) }}"
                        class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Limpiar
                    </a>
                @endif
            </form>
        </div>
    @endif

    <div class="mx-auto w-full max-w-5xl space-y-4 pb-2 px-2 sm:px-6 lg:px-8">
        @if ($tablaPosiciones->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-center text-gray-700">
                No existen registros
            </div>
        @endif

        @foreach ($tablaPosicionesPorGrupo as $idGrupoFila => $filasGrupo)
            @php
                $nombreGrupo = $filasGrupo->first()?->grupo?->nombre ?? 'Grupo sin nombre';
            @endphp

            <section class="rounded-xl bg-white/95 shadow">
                <header class="bg-slate-800 px-4 py-3 text-sm font-semibold text-white">
                    {{ $nombreGrupo }}
                </header>

                <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Id</th>
                            <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Equipo</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">Puntos</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">J</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">G</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">E</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">P</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">GF</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">GC</th>
                            <th class="hidden lg:table-cell p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 text-center whitespace-nowrap">DG</th>
                            <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filasGrupo as $fila)
                            <tr class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id</span>
                                    {{ $fila->id }}
                                </td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Equipo</span>
                                    {{ $fila->equipo?->nombre ?? '-' }}
                                </td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->puntos }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->jugado }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->ganado }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->empatado }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->perdido }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->golesFavor }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->golesContra }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->diferenciaGoles }}</td>
                                <td class="p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static whitespace-nowrap">
                                    <div class="lg:flex lg:justify-center lg:space-x-4 lg:flex-row flex justify-center flex-col">
                                        <a href="{{ route('tabla_posicion.edit', array_merge(['tabla_posicion' => $fila], $parametrosRegreso)) }}" class="btn btn-editar mb-1 lg:mb-0">Editar</a>
                                        <form class="mb-0" action="{{ route('tabla_posicion.destroy', $fila) }}" method="post">
                                            @method('DELETE')
                                            @csrf
                                            <input type="hidden" name="idEdicion" value="{{ $fila->idEdicion }}">
                                            <input type="hidden" name="idCategoria" value="{{ $fila->grupo?->idCategoria }}">
                                            <input type="hidden" name="idGrupo" value="{{ $fila->idGrupo }}">
                                            <button class="btn btn-eliminar" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
