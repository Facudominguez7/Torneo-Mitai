@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Copas" subtitle="Copas del torneo">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('copa.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary">
                    Agregar copa
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$copas->isEmpty()">
        <x-slot name="emptyState">
            <x-ui.empty-state message="Todavía no hay copas registradas">
                @if (isset($EdicionSeleccionada))
                    <x-slot name="actions">
                        <x-ui.button href="{{ route('copa.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary" size="sm">
                            Agregar copa
                        </x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nombre</th>
                <th scope="col">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($copas as $c)
                <tr>
                    <td>{{ $c->id }}</td>
                    <td>{{ $c->nombre }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button
                                href="{{ route('copa.edit', ['copa' => $c, 'idEdicion' => $EdicionSeleccionada]) }}"
                                variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('copa.destroy', $c) }}"
                                entity="la copa {{ $c->nombre }}"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>
    </x-ui.data-table>
@endsection
