@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Categorías" subtitle="Categorías de la edición seleccionada">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('categoria.create', ['idEdicion' => $EdicionSeleccionada]) }}"
                    variant="primary">
                    Agregar Categoria
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$categorias->isEmpty()">
        <x-slot name="head">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Nombre</th>
                <th scope="col">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($categorias as $c)
                <tr>
                    <td>{{ $c->id }}</td>
                    <td>{{ $c->nombreCategoria }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button href="{{ route('categoria.edit', $c) }}" variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('categoria.show', ['categorium' => $c, 'idEdicion' => $c->idEdicion]) }}"
                                variant="primary" size="sm">
                                Detalle
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('categoria.destroy', $c) }}"
                                entity="la categoría {{ $c->nombreCategoria }}"
                                :fields="['idEdicion' => $c->idEdicion]"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-center">
                {{ $categorias->links() }}
            </div>
        </x-slot>
    </x-ui.data-table>
@endsection
