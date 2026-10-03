@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar Categoría" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('categoria.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.categoria._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Agregar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
