@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6">
        <x-ui.page-header :title="$nombreGrupo" subtitle="Equipos del grupo" class="mb-5">
            <x-slot name="actions">
                <x-ui.button href="{{ route('grupos.index', ['idEdicion' => $EdicionSeleccionada->id ?? $EdicionSeleccionada]) }}"
                    variant="secondary">
                    Volver
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        <x-ui.data-table :empty="count($equiposGrupo) === 0">
            <x-slot name="emptyState">
                <x-ui.empty-state
                    message="Todavía no hay equipos en este grupo"
                    description="Agregá equipos desde la pantalla de Grupos."
                >
                    <x-slot name="actions">
                        <x-ui.button
                            href="{{ route('equipogrupo.create', ['idEdicion' => $EdicionSeleccionada->id ?? null, 'idCategoria' => $idCategoria, 'idGrupo' => $idGrupo]) }}"
                            variant="primary" size="sm">
                            Agregar equipo a grupo
                        </x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </x-slot>

            <x-slot name="head">
                <tr>
                    <th scope="col">Equipos</th>
                    <th scope="col">Acción</th>
                </tr>
            </x-slot>

            <x-slot name="body">
                @foreach ($equiposGrupo as $eg)
                    <tr>
                        <td>{{ $eg->nombreEquipo }}</td>
                        <td>
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <x-ui.button
                                    href="{{ route('equipogrupo.edit', ['equipogrupo' => $eg->idEquipoGrupo, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria, 'idGrupo' => $idGrupo]) }}"
                                    variant="success" size="sm">
                                    Editar
                                </x-ui.button>
                                <x-ui.confirm-delete size="sm"
                                    action="{{ route('equipogrupo.destroy', $eg->idEquipoGrupo) }}"
                                    entity="a {{ $eg->nombreEquipo }} de este grupo"
                                    :fields="['idEdicion' => $EdicionSeleccionada->id]"
                                />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot>
        </x-ui.data-table>
    </div>
@endsection
