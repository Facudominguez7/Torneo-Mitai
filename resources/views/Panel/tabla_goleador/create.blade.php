@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Nuevo goleador" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('tabla_goleador.store') }}" method="POST" class="mt-6">
        <x-ui.form-card>
            <div>
                @include('Panel.tabla_goleador._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Agregar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
