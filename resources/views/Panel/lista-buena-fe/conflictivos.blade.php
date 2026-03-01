@extends('Panel.admin')

@section('contenido')
    <div class="container mx-auto px-3 py-4 sm:px-4 sm:py-6">
        <h1 class="mb-6 text-2xl font-bold text-white sm:text-3xl">📋 Revisión de Jugadores</h1>

        <!-- Filtros y Búsqueda -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('lista-buena-fe.conflictivos') }}" class="space-y-4">
                    <!-- Tabs de Estado -->
                    <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-2 sm:gap-0 sm:pb-0">
                        <button type="submit" name="estado" value="pendientes"
                            class="flex-1 rounded-md px-4 py-2 text-sm font-medium sm:flex-none sm:rounded-none sm:px-6 sm:py-3 sm:text-base {{ ($estado ?? 'pendientes') === 'pendientes' ? 'bg-indigo-50 text-indigo-600 sm:border-b-2 sm:border-indigo-600 sm:bg-transparent' : 'text-gray-500 hover:text-gray-700' }}">
                            <span class="flex items-center">
                                🔴 Pendientes
                            </span>
                        </button>
                        <button type="submit" name="estado" value="resueltos"
                            class="flex-1 rounded-md px-4 py-2 text-sm font-medium sm:flex-none sm:rounded-none sm:px-6 sm:py-3 sm:text-base {{ ($estado ?? 'pendientes') === 'resueltos' ? 'bg-green-50 text-green-600 sm:border-b-2 sm:border-green-600 sm:bg-transparent' : 'text-gray-500 hover:text-gray-700' }}">
                            <span class="flex items-center">
                                ✅ Resueltos
                            </span>
                        </button>
                    </div>

                    <!-- Filtros -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Búsqueda por Jugador -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Buscar Jugador (DNI o Nombre)
                            </label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Ej: 12345678 o Juan Pérez"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <!-- Filtro por Edición -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Edición
                            </label>
                            <select name="idEdicion" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Todas las ediciones</option>
                                @foreach ($ediciones as $edicion)
                                    <option value="{{ $edicion->id }}" 
                                        {{ request('idEdicion') == $edicion->id ? 'selected' : '' }}>
                                        {{ $edicion->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filtro por Equipo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Equipo
                            </label>
                            <select name="idEquipo" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Todos los equipos</option>
                                @foreach ($equipos as $equipo)
                                    <option value="{{ $equipo->id }}" 
                                        {{ request('idEquipo') == $equipo->id ? 'selected' : '' }}>
                                        {{ $equipo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <a href="{{ route('lista-buena-fe.conflictivos') }}" 
                            class="w-full rounded-md bg-gray-300 px-4 py-2 text-center text-gray-700 hover:bg-gray-400 sm:w-auto">
                            Limpiar Filtros
                        </a>
                        <button type="submit" 
                            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 sm:w-auto">
                            Aplicar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Resultados -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
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
                <div class="p-8 text-center">
                    <p class="text-green-600 text-lg">
                        @if (($estado ?? 'pendientes') === 'resueltos')
                            📋 No hay casos resueltos para mostrar.
                        @else
                            ✅ No hay jugadores marcados como conflictivos pendientes.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jugador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Equipo / Categoría</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Edición</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Motivo de la Observación</th>
                                @if (($estado ?? 'pendientes') === 'resueltos')
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Resuelto Por</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Resolución</th>
                                @else
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Alta</th>
                                @endif
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($jugadores as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="flex items-center">
                                            <div class="min-w-0 sm:ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $item->jugador->nombre }} {{ $item->jugador->apellido }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    DNI: {{ $item->dni_jugador }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="text-sm text-gray-900">{{ $item->equipo->nombre }}</div>
                                        <div class="text-sm text-gray-500">{{ $item->categoria->nombreCategoria }}</div>
                                    </td>
                                    <td class="px-4 py-4 sm:px-6">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $item->edicion->nombre }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="text-sm text-red-600 font-medium">
                                            {{ $item->motivo_conflicto ?? 'No especificado' }}
                                        </div>
                                        @if (($estado ?? 'pendientes') === 'resueltos' && $item->notas_resolucion)
                                            <div class="text-xs text-gray-500 mt-1">
                                                <strong>Notas:</strong> {{ $item->notas_resolucion }}
                                            </div>
                                        @endif
                                    </td>
                                    @if (($estado ?? 'pendientes') === 'resueltos')
                                        <td class="px-4 py-4 text-sm text-gray-500 sm:px-6">
                                            {{ $item->resuelto_por ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 sm:px-6">
                                            {{ $item->fecha_resolucion ? $item->fecha_resolucion->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    @else
                                        <td class="px-4 py-4 text-sm text-gray-500 sm:px-6">
                                            {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-4 text-center text-sm font-medium sm:px-6">
                                        @if (($estado ?? 'pendientes') === 'pendientes')
                                            <button
                                                type="button"
                                                data-resolve-id="{{ $item->id }}"
                                                data-jugador-nombre="{{ $item->jugador->nombre }} {{ $item->jugador->apellido }}"
                                                data-motivo-conflicto="{{ $item->motivo_conflicto }}"
                                                onclick="openResolveModalFromButton(this)"
                                                class="mb-2 inline-block text-green-600 hover:text-green-900 sm:mb-0 sm:mr-3">
                                                Resolver
                                            </button>
                                        @endif
                                        <a href="{{ route('lista-buena-fe.show', ['idEquipo' => $item->idEquipo, 'idEdicion' => $item->idEdicion]) }}"
                                            class="text-indigo-600 hover:text-indigo-900">
                                            Ver Lista
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal para Resolver Conflicto -->
    <div id="resolveModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Resolver Caso</h3>
                
                <div class="mb-4 p-3 bg-gray-50 rounded">
                    <p class="text-sm text-gray-600 mb-1">Jugador:</p>
                    <p class="text-sm font-semibold" id="modalJugadorNombre"></p>
                </div>

                <div class="mb-4 p-3 bg-yellow-50 rounded">
                    <p class="text-sm text-gray-600 mb-1">Motivo de la observación:</p>
                    <p class="text-sm font-semibold text-red-600" id="modalMotivoConflicto"></p>
                </div>

                <form id="resolveForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="notas_resolucion" class="block text-sm font-medium text-gray-700 mb-2">
                            Notas de resolución (opcional)
                        </label>
                        <textarea 
                            id="notas_resolucion" 
                            name="notas_resolucion" 
                            rows="3" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Agregar notas sobre la resolución del caso..."
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button 
                            type="button" 
                            onclick="closeResolveModal()" 
                            class="w-full rounded-md bg-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-400 sm:w-auto"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            class="w-full rounded-md bg-green-600 px-4 py-2 text-white hover:bg-green-700 sm:w-auto"
                        >
                            Marcar como Resuelto
                        </button>
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
