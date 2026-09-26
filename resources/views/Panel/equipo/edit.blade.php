@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Editar Equipo" />

    @include('Panel.fragment._errores-formulario')

    <form action="{{ route('equipo.update', $equipo->id) }}" method="POST" enctype="multipart/form-data"
        class="mt-6">
        @method('PATCH')

        <x-ui.form-card>
            <div>
                @include('Panel.equipo._form')
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Editar
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
