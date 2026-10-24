@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Fases" subtitle="Fases del torneo">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('fase.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary">
                    Agregar fase
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$fases->isEmpty()">
        <x-slot name="emptyState">
            <x-ui.empty-state message="Todavía no hay fases registradas">
                @if (isset($EdicionSeleccionada))
                    <x-slot name="actions">
                        <x-ui.button href="{{ route('fase.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary" size="sm">
                            Agregar fase
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
            @foreach ($fases as $f)
                <tr>
                    <td>{{ $f->id }}</td>
                    <td>{{ $f->nombre }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button
                                href="{{ route('fase.edit', ['fase' => $f, 'idEdicion' => $EdicionSeleccionada]) }}"
                                variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('fase.destroy', $f) }}"
                                entity="la fase {{ $f->nombre }}"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>
    </x-ui.data-table>
@endsection
