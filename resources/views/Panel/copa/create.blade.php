@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar copa" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('copa.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.copa._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear copa
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
