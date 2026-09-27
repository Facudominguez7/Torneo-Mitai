@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Revisión de jugadores"
        subtitle="Detecta duplicados y conflictos administrativos entre equipos." />

    {{-- Filtros y Búsqueda --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
        <form method="GET" action="{{ route('lista-buena-fe.conflictivos') }}" class="space-y-4">
            {{-- Tabs de Estado --}}
            <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-2 sm:gap-0 sm:pb-0">
                <button type="submit" name="estado" value="pendientes"
                    class="flex-1 rounded-md px-4 py-2 text-sm font-medium sm:flex-none sm:rounded-none sm:px-6 sm:py-3 sm:text-base {{ ($estado ?? 'pendientes') === 'pendientes' ? 'bg-brand-navy/10 text-brand-navy sm:border-b-2 sm:border-brand-navy sm:bg-transparent' : 'text-gray-500 hover:text-gray-700' }}">
                    Pendientes
                </button>
                <button type="submit" name="estado" value="resueltos"
                    class="flex-1 rounded-md px-4 py-2 text-sm font-medium sm:flex-none sm:rounded-none sm:px-6 sm:py-3 sm:text-base {{ ($estado ?? 'pendientes') === 'resueltos' ? 'bg-green-50 text-green-600 sm:border-b-2 sm:border-green-600 sm:bg-transparent' : 'text-gray-500 hover:text-gray-700' }}">
                    Resueltos
                </button>
            </div>

            {{-- Filtros --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <x-ui.search label="Buscar Jugador (DNI o Nombre)" name="search" :value="request('search')"
                    placeholder="Ej: 12345678 o Juan Pérez" />

                <x-ui.select label="Edición" name="idEdicion">
                    <option value="">Todas las ediciones</option>
                    @foreach ($ediciones as $edicion)
                        <option value="{{ $edicion->id }}" {{ request('idEdicion') == $edicion->id ? 'selected' : '' }}>
                            {{ $edicion->nombre }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Equipo" name="idEquipo">
                    <option value="">Todos los equipos</option>
                    @foreach ($equipos as $equipo)
                        <option value="{{ $equipo->id }}" {{ request('idEquipo') == $equipo->id ? 'selected' : '' }}>
                            {{ $equipo->nombre }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            {{-- Botones --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <x-ui.button href="{{ route('lista-buena-fe.conflictivos') }}" variant="secondary">
                    Limpiar Filtros
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    Aplicar Filtros
                </x-ui.button>
            </div>
        </form>
    </div>

    {{-- Tabla de Resultados --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white">
        <div class="border-b border-gray-200 p-6">
            <p class="text-gray-600">
                @if (($estado ?? 'pendientes') === 'resueltos')
                    Histórico de casos que han sido resueltos.
                @else
                    Listado de jugadores que fueron agregados a la Lista de Buena Fe con observaciones administrativas
                    pendientes de resolución.
                @endif
            </p>
        </div>

        @if ($jugadores->isEmpty())
            <x-ui.empty-state
                :message="($estado ?? 'pendientes') === 'resueltos' ? 'No hay casos resueltos para mostrar' : 'No hay jugadores marcados como conflictivos pendientes'" />
        @else
            <x-ui.data-table :empty="false">
                <x-slot name="head">
                    <tr>
                        <th scope="col">Jugador</th>
                        <th scope="col">Equipo / Categoría</th>
                        <th scope="col">Edición</th>
                        <th scope="col">Motivo de la Observación</th>
                        @if (($estado ?? 'pendientes') === 'resueltos')
                            <th scope="col">Resuelto Por</th>
                            <th scope="col">Fecha Resolución</th>
                        @else
                            <th scope="col">Fecha Alta</th>
                        @endif
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </x-slot>
                <x-slot name="body">
                    @foreach ($jugadores as $item)
                        <tr>
                            <td>
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $item->jugador->nombre }} {{ $item->jugador->apellido }}
                                </div>
                                <div class="text-sm text-gray-500">DNI: {{ $item->dni_jugador }}</div>
                            </td>
                            <td>
                                <div class="text-sm text-gray-900">{{ $item->equipo->nombre }}</div>
                                <div class="text-sm text-gray-500">{{ $item->categoria->nombreCategoria }}</div>
                            </td>
                            <td>
                                <x-ui.badge variant="info">{{ $item->edicion->nombre }}</x-ui.badge>
                            </td>
                            <td>
                                <div class="text-sm font-medium text-red-600">
                                    {{ $item->motivo_conflicto ?? 'No especificado' }}
                                </div>
                                @if (($estado ?? 'pendientes') === 'resueltos' && $item->notas_resolucion)
                                    <div class="mt-1 text-xs text-gray-500">
                                        <strong>Notas:</strong> {{ $item->notas_resolucion }}
                                    </div>
                                @endif
                            </td>
                            @if (($estado ?? 'pendientes') === 'resueltos')
                                <td class="text-sm text-gray-500">{{ $item->resuelto_por ?? '-' }}</td>
                                <td class="text-sm text-gray-500">
                                    {{ $item->fecha_resolucion ? $item->fecha_resolucion->format('d/m/Y H:i') : '-' }}
                                </td>
                            @else
                                <td class="text-sm text-gray-500">
                                    {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-' }}
                                </td>
                            @endif
                            <td class="text-center text-sm font-medium">
                                @if (($estado ?? 'pendientes') === 'pendientes')
                                    <button type="button"
                                        data-resolve-id="{{ $item->id }}"
                                        data-jugador-nombre="{{ $item->jugador->nombre }} {{ $item->jugador->apellido }}"
                                        data-motivo-conflicto="{{ $item->motivo_conflicto }}"
                                        onclick="openResolveModalFromButton(this)"
                                        class="mb-2 inline-block text-green-600 hover:text-green-900 sm:mb-0 sm:mr-3">
                                        Resolver
                                    </button>
                                @endif
                                <a href="{{ route('lista-buena-fe.show', ['idEquipo' => $item->idEquipo, 'idEdicion' => $item->idEdicion]) }}"
                                    class="text-brand-navy hover:underline">
                                    Ver Lista
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot>
            </x-ui.data-table>
        @endif
    </div>

    {{-- Modal para Resolver Conflicto --}}
    <div id="resolveModal" class="fixed inset-0 z-50 hidden h-full w-full overflow-y-auto bg-gray-600/50">
        <div class="relative top-20 mx-auto w-full max-w-md rounded-md border bg-white p-5 shadow-lg">
            <div class="mt-3">
                <h3 class="mb-4 text-lg font-medium leading-6 text-gray-900">Resolver Caso</h3>

                <div class="mb-4 rounded bg-gray-50 p-3">
                    <p class="mb-1 text-sm text-gray-600">Jugador:</p>
                    <p class="text-sm font-semibold" id="modalJugadorNombre"></p>
                </div>

                <div class="mb-4 rounded bg-yellow-50 p-3">
                    <p class="mb-1 text-sm text-gray-600">Motivo de la observación:</p>
                    <p class="text-sm font-semibold text-red-600" id="modalMotivoConflicto"></p>
                </div>

                <form id="resolveForm" method="POST" action="">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="notas_resolucion" class="mb-2 block text-sm font-medium text-gray-700">
                            Notas de resolución (opcional)
                        </label>
                        <textarea
                            id="notas_resolucion"
                            name="notas_resolucion"
                            rows="3"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Agregar notas sobre la resolución del caso..."
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" onclick="closeResolveModal()">
                            Cancelar
                        </x-ui.button>
                        <x-ui.button type="submit" variant="success">
                            Marcar como Resuelto
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openResolveModal(id, jugadorNombre, motivoConflicto) {
            document.getElementById('modalJugadorNombre').textContent = jugadorNombre;
            document.getElementById('modalMotivoConflicto').textContent = motivoConflicto || 'No especificado';
            document.getElementById('resolveForm').action = `/Panel/lista-buena-fe/resolver-conflicto/${id}`;
            document.getElementById('resolveModal').classList.remove('hidden');
        }

        function openResolveModalFromButton(button) {
            if (!button) {
                return;
            }

            const id = button.dataset.resolveId;
            const jugadorNombre = button.dataset.jugadorNombre;
            const motivoConflicto = button.dataset.motivoConflicto;

            openResolveModal(id, jugadorNombre, motivoConflicto);
        }

        function closeResolveModal() {
            document.getElementById('resolveModal').classList.add('hidden');
            document.getElementById('notas_resolucion').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('resolveModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeResolveModal();
            }
        });
    </script>
@endsection
