@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar fecha" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('fecha.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.fecha._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear fecha
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
