@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar equipo de la edición" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('equipoedicion.update', $equipoedicion->id) }}" method="POST" class="mt-6">
        @method('PATCH')
        <x-ui.form-card>
            @include('Panel.equipoedicion._form')

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">Editar</x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
