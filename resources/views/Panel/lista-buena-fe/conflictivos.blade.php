@extends('Panel.admin')

@section('contenido')
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-3xl font-bold mb-6 text-white">📋 Revisión de Jugadores</h1>

        <!-- Filtros y Búsqueda -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('lista-buena-fe.conflictivos') }}" class="space-y-4">
                    <!-- Tabs de Estado -->
                    <div class="flex border-b border-gray-200 mb-4">
                        <button type="submit" name="estado" value="pendientes"
                            class="px-6 py-3 font-medium {{ ($estado ?? 'pendientes') === 'pendientes' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                            <span class="flex items-center">
                                🔴 Pendientes
                            </span>
                        </button>
                        <button type="submit" name="estado" value="resueltos"
                            class="px-6 py-3 font-medium {{ ($estado ?? 'pendientes') === 'resueltos' ? 'border-b-2 border-green-600 text-green-600' : 'text-gray-500 hover:text-gray-700' }}">
                            <span class="flex items-center">
                                ✅ Resueltos
                            </span>
                        </button>
                    </div>

                    <!-- Filtros -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('lista-buena-fe.conflictivos') }}" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Limpiar Filtros
                        </a>
                        <button type="submit" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $item->jugador->nombre }} {{ $item->jugador->apellido }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    DNI: {{ $item->dni_jugador }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $item->equipo->nombre }}</div>
                                        <div class="text-sm text-gray-500">{{ $item->categoria->nombreCategoria }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $item->edicion->nombre }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $item->resuelto_por ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $item->fecha_resolucion ? $item->fecha_resolucion->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    @else
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    @endif
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        @if (($estado ?? 'pendientes') === 'pendientes')
                                            <button onclick="openResolveModal({{ $item->id }}, '{{ $item->jugador->nombre }} {{ $item->jugador->apellido }}', '{{ $item->motivo_conflicto }}')"
                                                class="text-green-600 hover:text-green-900 mr-3">
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

                    <div class="flex justify-end space-x-3">
                        <button 
                            type="button" 
                            onclick="closeResolveModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
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