@extends('Panel.admin')

@section('contenido')
    <div class="flex justify-center">
        @if (isset($EdicionSeleccionada))
            <div class="flex flex-row justify-center mb-2">
                <a href="{{ route('tabla_posicion.create', ['idEdicion' => $EdicionSeleccionada->id]) }}">
                    <button
                        class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                        Agregar Posición
                    </button>
                </a>
            </div>
        @endif
    </div>

    @if (isset($EdicionSeleccionada))
        <div class="flex justify-center mb-4">
            <form action="{{ route('tabla_posicion.index') }}" method="GET" class="flex flex-wrap justify-center gap-3">
                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                <select name="idGrupo" class="border border-gray-300 rounded-lg px-4 py-2">
                    <option value="">Todos los Grupos</option>
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo->id }}" {{ (string) $idGrupo === (string) $grupo->id ? 'selected' : '' }}>
                            {{ $grupo->nombre }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Filtrar
                </button>
                @if (!empty($idGrupo))
                    <a href="{{ route('tabla_posicion.index', ['idEdicion' => $idEdicion]) }}"
                        class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Limpiar
                    </a>
                @endif
            </form>
        </div>
    @endif

    <div class="mx-auto w-full max-w-5xl flex justify-center items-stretch pb-2 px-2 sm:px-6 lg:px-8">
        <table class="border-collapse w-full mt-2">
            <thead>
                <tr>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Id</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Grupo</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Equipo</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Puntos</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">GF</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">GC</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">DG</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">J</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">G</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">P</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">E</th>
                    <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">Accion</th>
                </tr>
            </thead>
            <tbody>
                @if ($tablaPosiciones->isEmpty())
                    <tr class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                        <td colspan="12" class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            No existen registros
                        </td>
                    </tr>
                @endif

                @foreach ($tablaPosiciones as $fila)
                    <tr class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                        <td class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id</span>
                            {{ $fila->id }}
                        </td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Grupo</span>
                            {{ $fila->grupo?->nombre ?? '-' }}
                        </td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Equipo</span>
                            {{ $fila->equipo?->nombre ?? '-' }}
                        </td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->puntos }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->golesFavor }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->golesContra }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->diferenciaGoles }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->jugado }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->ganado }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->perdido }}</td>
                        <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">{{ $fila->empatado }}</td>
                        <td class="w-full lg:w-full p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <div class="lg:flex lg:justify-center lg:space-x-4 lg:flex-row flex justify-center flex-col">
                                <a href="{{ route('tabla_posicion.edit', $fila) }}" class="btn btn-editar mb-1 lg:mb-0">Editar</a>
                                <form class="mb-0" action="{{ route('tabla_posicion.destroy', $fila) }}" method="post">
                                    @method('DELETE')
                                    @csrf
                                    <input type="hidden" name="idEdicion" value="{{ $fila->idEdicion }}">
                                    <button class="btn btn-eliminar" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $tablaPosiciones->links() }}
    </div>
@endsection
