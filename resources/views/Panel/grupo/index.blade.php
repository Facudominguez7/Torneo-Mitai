@extends('Panel.admin')

@section('contenido')
<div class="flex justify-center px-2 sm:px-4">
    @if (isset($EdicionSeleccionada))
        <div class="mb-2 grid w-full max-w-3xl grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="min-w-0">
            <a href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada, 'tipo' => $tipo]) }}">
                    <button
                        class="mt-2 mb-2 w-full rounded-lg bg-gray-800 px-4 py-2 text-sm text-white transition-all duration-300 hover:bg-gray-900 sm:text-base md:py-3 md:px-6">
                        Agregar Grupo
                    </button>
                </a>
            </div>
            <div class="min-w-0">
                <a href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada, 'tipo' => 'equipogrupo']) }}">
                    <button
                        class="mt-2 mb-2 w-full rounded-lg bg-gray-800 px-4 py-2 text-sm text-white transition-all duration-300 hover:bg-gray-900 sm:text-base md:py-3 md:px-6">
                        Agregar Equipo a Grupo
                    </button>
                </a>
            </div>
        </div>
    @endif
</div>
<div class="mx-auto flex w-full max-w-4xl justify-center items-stretch px-2 pb-2 sm:px-4 lg:px-6">
    <div class="w-full overflow-x-auto rounded-lg bg-white shadow-sm">
    <table class="mt-2 w-full min-w-[640px] border-collapse">
        <thead>
            <tr>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Id
                </th>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Nombre
                </th>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Categoria
                </th>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Accion
                </th>
            </tr>
        </thead>
        <tbody>
            @if ($grupos->isempty())
            <tr class="mb-4 flex flex-row flex-wrap bg-white lg:mb-0 lg:table-row lg:flex-row lg:flex-no-wrap lg:hover:bg-gray-100">
                <td colspan="5" class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    No existen registros
                </td>
            </tr>
            @endif
            @foreach ($grupos as $g)
            <tr class="mb-4 flex flex-row flex-wrap bg-white lg:mb-0 lg:table-row lg:flex-row lg:flex-no-wrap lg:hover:bg-gray-100">
                <td class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id
                    </span>
                    {{ $g->id }}
                </td>
                <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                    </span>
                    {{ $g->nombre }}
                </td>
                <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                    </span>
                    {{ $g->categoria->nombreCategoria }}
                </td>
                <td class="w-full lg:w-full p-3 text-gray-800 text-center border border-b  block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Accion
                    </span>
                    <div class="lg:flex lg:justify-center lg:space-x-4 lg:flex-row flex justify-center flex-col">
                        <button class="mb-1 lg:mb-0">
                            <a href="{{ route('grupos.edit', ['grupo' => $g, 'idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada]) }}" class="btn btn-editar">
                                Editar
                            </a>
                        </button>
                        <button class="mb-1 lg:mb-0">
                            <a href="{{ route('grupos.show', ['grupo' => $g, 'idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada]) }}"
                                class="btn btn-detalle">
                                Detalle
                            </a>
                        </button>
                        <form class="mb-0" action="{{ route('grupos.destroy', $g) }}" method="post">
                            @method('DELETE')
                            @csrf
                            <input type="number" id="idEdicion" name="idEdicion" class="hidden"
                                        value="{{ $EdicionSeleccionada->id }}">
                            <button class="btn btn-eliminar" type="submit">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
<div class="flex justify-center">
    {{ $grupos->links() }}
</div>
@endsection
