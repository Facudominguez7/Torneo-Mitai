@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar grupo" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('grupos.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.grupo._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear grupo
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
