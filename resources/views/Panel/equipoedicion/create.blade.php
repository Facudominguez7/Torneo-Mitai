@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar equipo de una edición anterior" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('equipoedicion.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            @include('Panel.equipoedicion._form')

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">Agregar</x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
