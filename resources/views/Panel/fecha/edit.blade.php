@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar fecha" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('fecha.update', $fecha->id) }}" method="POST" class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.fecha._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Guardar cambios
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
