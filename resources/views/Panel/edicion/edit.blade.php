@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar edición" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('edicion.update', $edicion->id) }}" method="POST" class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.edicion._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Guardar cambios
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
