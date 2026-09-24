@extends('Panel.admin')

@section('contenido')
    <div class="flex justify-center">
        @if (isset($EdicionSeleccionada))
            <div class="flex flex-row justify-center mb-2 mr-5">
                <x-ui.button href="{{ route('equipo.create', ['idEdicion' => $EdicionSeleccionada]) }}"
                    variant="primary" class="mt-2 mb-2">
                    Agregar Equipo
                </x-ui.button>
            </div>
            <div class="flex flex-row justify-center mb-2">
                <x-ui.button
                    href="{{ route('seleccionar-edicion', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'equipoedicion']) }}"
                    variant="secondary" class="mt-2 mb-2">
                    Agregar Equipo de una Edicion Anterior
                </x-ui.button>
            </div>
        @endif
    </div>
    <div class="flex justify-center mb-4">
        <form action="{{ route('equipo.index') }}" method="GET"
            class="flex flex-wrap items-end justify-center gap-3">
            <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
            <x-ui.search name="nombreEquipo" :value="$nombreEquipo ?? ''" placeholder="Buscar equipo por nombre"
                class="w-64" />
            <x-ui.select name="idCategoria">
                <option value="">Todas las Categorías</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" {{ $idCategoria == $categoria->id ? 'selected' : '' }}>
                        {{ $categoria->nombreCategoria }}
                    </option>
                @endforeach
            </x-ui.select>
            <x-ui.button type="submit" variant="primary">
                Filtrar
            </x-ui.button>
            @if (!empty($nombreEquipo) || !empty($idCategoria))
                <x-ui.button href="{{ route('equipo.index', ['idEdicion' => $idEdicion]) }}" variant="secondary">
                    Limpiar
                </x-ui.button>
            @endif
        </form>
    </div>
    <div class="mx-auto w-full max-w-2xl flex justify-center items-stretch pb-2 px-2 sm:px-6 lg:px-8">
        <table class="border-collapse w-full mt-2">
            <thead>
                <tr>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Id
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Logo
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Nombre
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Goles Recibidos
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Categoria
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Accion
                    </th>
                </tr>
            </thead>
            <tbody>
                @if ($equipos->isempty())
                    <tr>
                        <td colspan="5" class="p-3">
                            <x-ui.empty-state message="No existen registros" />
                        </td>
                    </tr>
                @endif
                @foreach ($equipos as $e)
                    <tr
                        class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                        <td
                            class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id
                            </span>
                            {{ $e->id }}
                        </td>
                        <td
                            class="w-full lg:w-auto p-3 text-gray-800 flex justify-center border border-b  lg:table-cell relative lg:static">
                            <img src="{{ asset('fotos/equipos/' . $e->foto) }}" alt="logo {{ $e->nombre }}">
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                            </span>
                            {{ $e->nombre }}
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                            </span>
                            {{ $e->goles_en_contra }}
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Categoria
                            </span>
                            {{ $e->nombreCategoria }}
                        </td>
                        <td
                            class="w-full lg:w-full p-3 text-gray-800 text-center border border-b  block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Accion
                            </span>
                            <div class="lg:flex lg:justify-center lg:space-x-4 lg:flex-row flex justify-center flex-col">
                                <x-ui.button href="{{ route('equipo.edit', $e->id) }}" variant="success"
                                    class="mb-1 lg:mb-0">
                                    Editar
                                </x-ui.button>
                                <x-ui.button
                                    href="{{ route('equipo.show', ['equipo' => $e, 'idEdicion' => $e->idEdicion]) }}"
                                    variant="primary" class="mb-1 lg:mb-0">
                                    Detalle
                                </x-ui.button>
                                <form class="mb-0" action="{{ route('equipo.destroy', $e->id) }}" method="post">
                                    @method('DELETE')
                                    @csrf
                                    <input type="number" id="idEdicion" name="idEdicion" class="hidden"
                                        value="{{ $EdicionSeleccionada->id }}">
                                    <x-ui.button type="submit" variant="danger">Eliminar</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex justify-center">
        {{ $equipos->links() }}
    </div>
@endsection
