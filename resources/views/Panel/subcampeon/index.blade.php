@extends('Panel.admin')

@section('contenido')
    <div class="flex justify-center ">
        @if (isset($EdicionSeleccionada))
            <div class="flex flex-row justify-center mb-2">
                <div>
                <a href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => $tipo]) }}">
                        <button
                            class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                            Agregar SubCampeon
                        </button>
                    </a>
                </div>
            </div>
        @endif
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
                        Nombre
                    </th>
                    <th
                        class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                        Copa
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
                @if ($subcampeones->isempty())
                    <tr
                        class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                        <td colspan="5"
                            class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            No existen registros
                        </td>
                    </tr>
                @endif
                @foreach ($subcampeones as $s)
                    <tr
                        class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                        <td
                            class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id
                            </span>
                            {{ $s->idEquipo }}
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                            </span>
                            {{ $s->nombreEquipo }}
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Copa
                            </span>
                            {{ $s->nombreCopa }}
                        </td>
                        <td
                            class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Categoria
                            </span>
                            {{ $s->nombreCategoria }}
                        </td>
                        <td
                            class="w-full lg:w-full p-3 text-gray-800 text-center border border-b  block lg:table-cell relative lg:static">
                            <span
                                class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Accion
                            </span>
                            <div class="lg:flex lg:justify-center lg:space-x-4 lg:flex-row flex justify-center flex-col">
                                <button class="mb-1 lg:mb-0">
                                    <a href="{{ route('subcampeon.edit', $s) }}" class="btn btn-editar">
                                        Editar
                                    </a>
                                </button>
                                <form action="{{ route('subcampeon.destroy', $s) }}" method="post">
                                    @method('DELETE')
                                    @csrf
                                    <input type="number" id="idEdicion" name="idEdicion" class="hidden"
                                        value="{{ $s->idEdicion }}">
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
        {{ $subcampeones->links() }}
    </div>
@endsection
