@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Grupos" subtitle="Grupos de la edición seleccionada">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button
                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada, 'tipo' => $tipo]) }}"
                    variant="primary">
                    Agregar grupo
                </x-ui.button>
                <x-ui.button
                    href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada, 'tipo' => 'equipogrupo']) }}"
                    variant="secondary">
                    Agregar equipo a grupo
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$grupos->isEmpty()">
        <x-slot name="emptyState">
            <x-ui.empty-state message="Todavía no hay grupos en esta edición">
                @if (isset($EdicionSeleccionada))
                    <x-slot name="actions">
                        <x-ui.button
                            href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada, 'tipo' => $tipo]) }}"
                            variant="primary" size="sm">
                            Agregar grupo
                        </x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nombre</th>
                <th scope="col">Categoría</th>
                <th scope="col">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($grupos as $g)
                <tr>
                    <td>{{ $g->id }}</td>
                    <td>{{ $g->nombre }}</td>
                    <td>{{ $g->categoria->nombreCategoria }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button
                                href="{{ route('grupos.edit', ['grupo' => $g, 'idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada]) }}"
                                variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('grupos.show', ['grupo' => $g, 'idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada]) }}"
                                variant="primary" size="sm">
                                Detalle
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('grupos.destroy', $g) }}"
                                entity="el grupo {{ $g->nombre }}"
                                :fields="['idEdicion' => $EdicionSeleccionada->id]"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-center">
                {{ $grupos->links() }}
            </div>
        </x-slot>
    </x-ui.data-table>
@endsection
