@extends('Panel.admin')

@section('contenido')
<div class="mx-auto w-full max-w-2xl flex justify-center items-stretch pb-2 px-2 sm:px-6 lg:px-8">
    <table class="border-collapse w-full mt-2">
        <thead>
            <tr>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Id
                </th>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Nombre
                </th>
                <th class="p-3 font-bold uppercase bg-gray-200 text-gray-600 border border-gray-300 hidden lg:table-cell">
                    Accion
                </th>
            </tr>
        </thead>
        <tbody>
            @if ($equiposGrupo->isempty())
            <tr class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                <td colspan="5" class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    No existen registros
                </td>
            </tr>
            @endif
            @foreach ($equiposGrupo as $eg)
            <tr class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                <td class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id
                    </span>
                    {{ $eg->idEquipo }}
                </td>
                <td class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                    <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                    </span>
                    {{ $eg->nombreEquipo }}
                </td>
              
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection