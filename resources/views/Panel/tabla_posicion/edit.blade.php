@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar Posición" :subtitle="$EdicionSeleccionada?->nombre ?? 'Sin edición seleccionada'" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('tabla_posicion.update', $tablaPosicion) }}" method="POST" class="mt-6">
        @method('PUT')

        <x-ui.form-card>
            <div>
                @include('Panel.tabla_posicion._form')
            </div>

            <x-slot name="actions">
                <x-ui.button href="{{ route('tabla_posicion.index', $parametrosRegreso) }}" variant="secondary">
                    Volver
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    Guardar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
