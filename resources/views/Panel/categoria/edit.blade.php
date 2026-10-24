@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar Categoría" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('categoria.update', $categoria->id) }}" method="POST" class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.categoria._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Editar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
