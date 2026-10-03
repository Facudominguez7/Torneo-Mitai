@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar valla" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('valla.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.valla._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Guardar valla
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
