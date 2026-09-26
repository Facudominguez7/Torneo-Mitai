@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Ediciones" subtitle="Ediciones registradas en el torneo">
        <x-slot name="actions">
            <x-ui.button href="{{ route('edicion.create') }}" variant="primary">
                Agregar edición
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$ediciones->isEmpty()">
        <x-slot name="emptyState">
            <x-ui.empty-state message="Todavía no hay ediciones registradas">
                <x-slot name="actions">
                    <x-ui.button href="{{ route('edicion.create') }}" variant="primary" size="sm">
                        Agregar edición
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nombre</th>
                <th scope="col">Jugadores por Planilla</th>
                <th scope="col">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($ediciones as $e)
                <tr>
                    <td>{{ $e->id }}</td>
                    <td>{{ $e->nombre }}</td>
                    <td>{{ $e->jugadores_por_planilla ?? 18 }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button href="{{ route('edicion.edit', $e) }}" variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('edicion.destroy', $e) }}"
                                entity="la edición {{ $e->nombre }}"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>
    </x-ui.data-table>
@endsection
