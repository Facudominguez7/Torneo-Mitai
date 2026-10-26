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

    <x-ui.page-header title="Agregar equipo a grupo">
        <x-slot name="actions">
            <x-ui.button href="{{ $rutaVolver }}" variant="secondary">
                Volver
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <form action="{{ route('equipogrupo.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.equipogrupo._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Agregar equipo a grupo
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
