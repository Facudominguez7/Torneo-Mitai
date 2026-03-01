@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    @php
        $parametrosRegreso = $parametrosRegreso ?? ['idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion')];
    @endphp

    <div class="mb-4 px-4 md:px-0">
        <a href="{{ route('partido.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>
    <div class="flex items-center justify-center px-4 md:px-0">
        <div class="mx-auto w-full max-w-[600px]">
            <form action="{{route('partido.store')}}" method="POST">
                @include('Panel.partido._form')
                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idGrupo" value="{{ $parametrosRegreso['idGrupo'] ?? '' }}">
                <input type="hidden" name="retorno_idFecha" value="{{ $parametrosRegreso['idFecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">
                <div>
                    <button type="submit"
                        class="hover:shadow-form rounded-md bg-[#6A64F1] py-3 px-8 text-base font-semibold text-white outline-none w-full md:w-auto">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
