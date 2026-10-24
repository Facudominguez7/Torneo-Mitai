@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar edición" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('edicion.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.edicion._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear edición
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
