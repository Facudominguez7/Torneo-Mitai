@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar fase" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('fase.update', $fase->id) }}" method="POST" class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.fase._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Guardar cambios
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
