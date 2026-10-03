@extends('Panel.admin')

@section('contenido')
    @php
        $parametrosRegreso = $parametrosRegreso ?? ['idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion')];
    @endphp

    <x-ui.page-header title="Nuevo partido" />

    @include('Panel.fragment._errores-formulario')

    <div class="mb-4">
        <a href="{{ route('partido.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <form action="{{ route('partido.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.partido._form')

                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idGrupo" value="{{ $parametrosRegreso['idGrupo'] ?? '' }}">
                <input type="hidden" name="retorno_idFecha" value="{{ $parametrosRegreso['idFecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear partido
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
