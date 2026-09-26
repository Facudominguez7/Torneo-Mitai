@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Fechas" subtitle="Fechas registradas en la edición">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('fecha.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary">
                    Agregar fecha
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$fechas->isEmpty()">
        <x-slot name="emptyState">
            <x-ui.empty-state message="Todavía no hay fechas en esta edición">
                @if (isset($EdicionSeleccionada))
                    <x-slot name="actions">
                        <x-ui.button href="{{ route('fecha.create', ['idEdicion' => $EdicionSeleccionada]) }}" variant="primary" size="sm">
                            Agregar fecha
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
            @foreach ($fechas as $f)
                <tr>
                    <td>{{ $f->id }}</td>
                    <td>{{ $f->nombre }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button
                                href="{{ route('fecha.edit', ['fecha' => $f, 'idEdicion' => $EdicionSeleccionada]) }}"
                                variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('fecha.destroy', $f) }}"
                                entity="la fecha {{ $f->nombre }}"
                                :fields="['idEdicion' => $EdicionSeleccionada->id]"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-center">
                {{ $fechas->links() }}
            </div>
        </x-slot>
    </x-ui.data-table>
@endsection
