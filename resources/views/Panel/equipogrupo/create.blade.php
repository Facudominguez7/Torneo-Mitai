@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    @php
        $rutaVolver = route('seleccionar-categoria', array_filter([
            'idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion'),
            'idCategoria' => $CategoriaSeleccionada->id ?? request('idCategoria'),
            'idGrupo' => $GrupoSeleccionado->id ?? request('idGrupo'),
            'tipo' => 'equipogrupo',
        ], static fn ($valor) => $valor !== null && $valor !== ''));
    @endphp

    <div class="flex items-center justify-center">
        <div class="mx-auto w-full max-w-[550px]">
            <a href="{{ $rutaVolver }}"
                class="mb-4 inline-flex items-center gap-2 rounded-md border border-white/30 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver
            </a>

            <form action="{{route('equipogrupo.store')}}" method="POST">
                @include('Panel.equipogrupo._form')
                <div>
                    <button type="submit"
                        class="panel-form-submit">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
