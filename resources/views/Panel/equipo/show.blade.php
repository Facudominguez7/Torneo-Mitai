@extends('Panel.admin')

@section('detalle')
    <div class="flex flex-row items-center w-full absolute top-0 left-0 mt-10 pl-72">
        <!-- Contenedor para la imagen -->
        <div class="flex-none">
            <img class="h-24 w-24" src="{{ asset('fotos/equipos/' . $equipo->foto) }}" alt="logo {{ $equipo->nombre }}">
        </div>
        <div class="flex-1 flex text-white text-4xl pl-96">
            <h1>
                {{ $equipo->nombre }} - {{ $equipo->categoria->nombreCategoria }}
            </h1>
        </div>
    </div>
    <div class="mx-auto w-full max-w-2xl mt-28 flex justify-center items-stretch pb-2 px-2 sm:px-6 lg:px-8">
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
                        Categoria
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    class="bg-white lg:hover:bg-gray-100 flex lg:table-row flex-row lg:flex-row flex-wrap lg:flex-no-wrap mb-10 lg:mb-0">
                    <td
                        class="w-full lg:w-auto p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                        <span class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Id
                        </span>
                        {{$equipo->id}}
                    </td>
                    <td
                        class="w-full lg:w-auto p-3 text-gray-800 flex justify-center border border-b  lg:table-cell relative lg:static">
                        <img class="h-24 w-24" src="{{ asset('fotos/equipos/' . $equipo->foto) }}"
                            alt="logo {{ $equipo->nombre }}">
                    </td>
                    <td
                        class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                        <span
                            class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">nombre
                        </span>
                        {{ $equipo->nombre }}
                    </td>
                    <td
                        class="w-full lg:w-1/3 p-3 text-gray-800 text-center border border-b block lg:table-cell relative lg:static">
                        <span
                            class="lg:hidden absolute top-0 left-0 bg-blue-200 px-2 py-1 text-xs font-bold uppercase">Categoria
                        </span>
                        {{ $equipo->categoria->nombreCategoria }}
                    </td>
                </tr>
            </tbody>
    </div>
@endsection
