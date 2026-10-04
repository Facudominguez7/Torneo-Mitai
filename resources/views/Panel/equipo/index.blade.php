@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Equipos" subtitle="Listado de equipos inscriptos en la edición">
        @if (isset($EdicionSeleccionada))
            <x-slot name="actions">
                <x-ui.button href="{{ route('equipo.create', ['idEdicion' => $EdicionSeleccionada]) }}"
                    variant="primary">
                    Agregar Equipo
                </x-ui.button>
                <x-ui.button
                    :href="route('seleccionar-edicion', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'equipoedicion'])"
                    variant="secondary">
                    Importar de otra edición
                </x-ui.button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.data-table class="mt-6" :empty="$equipos->isEmpty()">
        <x-slot name="filters">
            <form action="{{ route('equipo.index') }}" method="GET"
                class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                <div class="min-w-[14rem] flex-1">
                    <x-ui.search label="Buscar equipo" name="nombreEquipo" :value="$nombreEquipo ?? ''"
                        placeholder="Buscar equipo por nombre" class="w-full" />
                </div>
                <x-ui.select label="Categoría" name="idCategoria">
                    <option value="">Todas las Categorías</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ $idCategoria == $categoria->id ? 'selected' : '' }}>
                            {{ $categoria->nombreCategoria }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" variant="primary">
                    Filtrar
                </x-ui.button>
                @if (!empty($nombreEquipo) || !empty($idCategoria))
                    <x-ui.button href="{{ route('equipo.index', ['idEdicion' => $idEdicion]) }}" variant="secondary">
                        Limpiar
                    </x-ui.button>
                @endif
            </form>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Logo</th>
                <th scope="col">Nombre</th>
                <th scope="col">Goles Recibidos</th>
                <th scope="col">Categoria</th>
                <th scope="col">Acción</th>
            </tr>
        </x-slot>

        <x-slot name="body">
            @foreach ($equipos as $e)
                <tr>
                    <td>{{ $e->id }}</td>
                    <td>
                        <img class="mx-auto h-10 w-10 rounded-full object-cover"
                            src="{{ asset('fotos/equipos/' . $e->foto) }}" alt="logo {{ $e->nombre }}">
                    </td>
                    <td>{{ $e->nombre }}</td>
                    <td>{{ $e->goles_en_contra }}</td>
                    <td>{{ $e->nombreCategoria }}</td>
                    <td>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <x-ui.button href="{{ route('equipo.edit', $e->id) }}" variant="success" size="sm">
                                Editar
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('equipo.show', ['equipo' => $e, 'idEdicion' => $idEdicion]) }}"
                                variant="primary" size="sm">
                                Detalle
                            </x-ui.button>
                            <x-ui.confirm-delete size="sm"
                                action="{{ route('equipo.destroy', $e->id) }}"
                                entity="el equipo {{ $e->nombre }}"
                                :fields="['idEdicion' => $EdicionSeleccionada->id]"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-center">
                {{ $equipos->links() }}
            </div>
        </x-slot>
    </x-ui.data-table>
@endsection
