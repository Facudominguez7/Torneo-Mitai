@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar goleador" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('tabla_goleador.update', $goleador_t->id) }}" method="POST" class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.tabla_goleador._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Editar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
