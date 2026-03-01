@extends('Panel.admin')

@section('contenido')
    <div class="bg-white rounded-lg shadow-lg p-6">
        {{-- Encabezado --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Gestión de Instituciones</h1>
                <p class="text-gray-600 text-sm mt-1">
                    Visualiza y vincula equipos sin institución a una institución existente
                    para resolver conflictos de validación de jugadores.
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-4">
                <div class="bg-blue-100 rounded-lg px-4 py-2 text-center">
                    <span class="text-2xl font-bold text-blue-600">{{ $totalInstituciones }}</span>
                    <p class="text-xs text-blue-600">Instituciones</p>
                </div>
                <div class="bg-green-100 rounded-lg px-4 py-2 text-center">
                    <span class="text-2xl font-bold text-green-600">{{ $institucionesConEquipos }}</span>
                    <p class="text-xs text-green-600">Con equipos</p>
                </div>
                <div class="bg-orange-100 rounded-lg px-4 py-2 text-center">
                    <span class="text-2xl font-bold text-orange-600">{{ $totalEquiposSinInstitucion }}</span>
                    <p class="text-xs text-orange-600">Sin institución</p>
                </div>
            </div>
        </div>

        {{-- Filtro de Edición --}}
        <div class="mb-6">
            <form action="{{ route('institucion.index') }}" method="GET" class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700">Filtrar por edición:</label>
                <select name="idEdicion"
                    class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
                    onchange="this.form.submit()">
                    <option value="">Todas las ediciones</option>
                    @foreach ($ediciones as $edicion)
                        <option value="{{ $edicion->id }}" {{ $EdicionSeleccionada && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                            {{ $edicion->nombre }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Crear Nueva Institución --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-3">
                <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                    </path>
                </svg>
                Crear Nueva Institución
            </h3>
            <form action="{{ route('institucion.store') }}" method="POST" class="flex flex-wrap items-end gap-4">
                @csrf
                <div class="flex-1 min-w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la institución:</label>
                    <input type="text" name="nombre" required placeholder="Ej: Escuela San José"
                        class="border border-gray-300 rounded-lg px-3 py-2 w-full focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Crear Institución
                </button>
            </form>
            @error('nombre')
                <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
            @enderror
        </div>

        {{-- Sección de Equipos Sin Institución --}}
        @if ($equiposSinInstitucion->count() > 0)
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-orange-800 mb-3">
                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                    Equipos sin Institución ({{ $equiposSinInstitucion->count() }})
                </h3>
                <p class="text-sm text-orange-700 mb-4">
                    Estos equipos no tienen una institución asignada. Vincúlalos a una institución existente para que sus
                    jugadores puedan ser validados correctamente en otras categorías.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($equiposSinInstitucion as $equipo)
                        <div class="flex items-center gap-3 bg-white rounded-lg p-3 border border-orange-200">
                            @if ($equipo->foto)
                                <img src="{{ asset('fotos/equipos/' . $equipo->foto) }}" class="w-10 h-10 rounded-full object-cover"
                                    alt="{{ $equipo->nombre }}">
                            @else
                                <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                                    <span class="text-gray-600 text-xs font-bold">{{ substr($equipo->nombre, 0, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $equipo->nombre }}</p>
                                @if ($EdicionSeleccionada)
                                    @foreach ($equipo->equipoEdiciones as $ee)
                                        <p class="text-xs text-gray-500">{{ $ee->categoria->nombreCategoria ?? 'Sin categoría' }}</p>
                                    @endforeach
                                @endif
                            </div>
                            {{-- Selector para asignar institución --}}
                            <form action="{{ route('institucion.cambiarEquipo') }}" method="POST" class="flex-shrink-0">
                                @csrf
                                <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                                <select name="institucion_id"
                                    onchange="if(confirm('¿Vincular este equipo a la institución seleccionada?')) this.form.submit();"
                                    class="text-xs border border-gray-300 rounded px-2 py-1 w-32 bg-white">
                                    <option value="">Seleccionar...</option>
                                    @foreach ($instituciones as $inst)
                                        <option value="{{ $inst->id }}">
                                            {{ Str::limit($inst->nombre, 18) }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-700 text-sm">
                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    ¡Excelente! Todos los equipos tienen una institución asignada.
                </p>
            </div>
        @endif

        {{-- Lista de Instituciones --}}
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Instituciones Registradas</h2>
        <div class="space-y-4">
            @forelse ($instituciones as $institucion)
                @php
                    $equipos = $equiposPorInstitucion[$institucion->id] ?? collect();
                    $tieneEquipos = $equipos->count() > 0 || !$EdicionSeleccionada;
                @endphp

                @if ($tieneEquipos || !$EdicionSeleccionada)
                    <div
                        class="border border-gray-200 rounded-lg overflow-hidden {{ $equipos->count() > 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    ID: {{ $institucion->id }}
                                </span>
                                <h3 class="font-semibold text-gray-800">{{ $institucion->nombre }}</h3>
                                @if ($EdicionSeleccionada)
                                    <span class="text-sm text-gray-500">
                                        ({{ $equipos->count() }} en esta edición / {{ $institucion->equipos_count }} total)
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500">({{ $institucion->equipos_count }} equipos total)</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleEdit(this.dataset.institucionId)" data-institucion-id="{{ $institucion->id }}"
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                    Editar
                                </button>
                            </div>
                        </div>

                        {{-- Form de edición (oculto por defecto) --}}
                        <div id="edit-form-{{ $institucion->id }}" class="hidden px-4 py-2 bg-blue-50 border-b">
                            <form action="{{ route('institucion.update', $institucion) }}" method="POST"
                                class="flex items-center gap-4">
                                @csrf
                                @method('PUT')
                                <input type="text" name="nombre" value="{{ $institucion->nombre }}"
                                    class="border border-gray-300 rounded-lg px-3 py-1 flex-1" required>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                                    Guardar
                                </button>
                                <button type="button" onclick="toggleEdit(this.dataset.institucionId)" data-institucion-id="{{ $institucion->id }}" class="text-gray-600 text-sm">
                                    Cancelar
                                </button>
                            </form>
                        </div>

                        {{-- Equipos de esta institución --}}
                        @if ($equipos->count() > 0)
                            <div class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-600 mb-2">
                                    Equipos en {{ $EdicionSeleccionada->nombre ?? 'esta edición' }}:
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($equipos as $equipo)
                                        <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-2">
                                            @if ($equipo->foto)
                                                <img src="{{ asset('fotos/equipos/' . $equipo->foto) }}"
                                                    class="w-8 h-8 rounded-full object-cover" alt="{{ $equipo->nombre }}">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <span class="text-gray-600 text-xs">{{ substr($equipo->nombre, 0, 2) }}</span>
                                                </div>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ $equipo->nombre }}</p>
                                                @foreach ($equipo->equipoEdiciones as $ee)
                                                    <p class="text-xs text-gray-500">{{ $ee->categoria->nombreCategoria ?? 'Sin categoría' }}
                                                    </p>
                                                @endforeach
                                            </div>
                                            <div class="flex items-center gap-1">
                                                {{-- Selector para cambiar institución --}}
                                                <form action="{{ route('institucion.cambiarEquipo') }}" method="POST" class="flex-shrink-0">
                                                    @csrf
                                                    <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                                                    <select name="institucion_id"
                                                        onchange="if(confirm('¿Mover este equipo a otra institución?')) this.form.submit();"
                                                        class="text-xs border border-gray-200 rounded px-1 py-0.5 w-20">
                                                        @foreach ($instituciones as $inst)
                                                            <option value="{{ $inst->id }}" {{ $inst->id == $institucion->id ? 'selected' : '' }}>
                                                                {{ Str::limit($inst->nombre, 12) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                                {{-- Botón para desvincular --}}
                                                <form action="{{ route('institucion.desvincularEquipo') }}" method="POST"
                                                    class="flex-shrink-0">
                                                    @csrf
                                                    <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                                                    <button type="submit"
                                                        onclick="return confirm('¿Desvincular este equipo de la institución?')"
                                                        class="text-red-500 hover:text-red-700 p-1" title="Desvincular de institución">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @elseif (!$EdicionSeleccionada)
                            <div class="px-4 py-2 text-sm text-gray-500 italic">
                                Selecciona una edición para ver los equipos
                            </div>
                        @endif
                    </div>
                @endif
            @empty
                <div class="text-center py-8 text-gray-500">
                    No hay instituciones registradas.
                </div>
            @endforelse
        </div>
    </div>

    <script>
        function toggleEdit(id) {
            const form = document.getElementById('edit-form-' + id);
            form.classList.toggle('hidden');
        }
    </script>
@endsection
