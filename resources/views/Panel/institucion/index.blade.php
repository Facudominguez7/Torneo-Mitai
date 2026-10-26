@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Gestión de Instituciones"
        subtitle="Visualiza y vincula equipos sin institución a una institución existente para resolver conflictos de validación de jugadores." />

    {{-- Contadores --}}
    <div class="mt-6 flex flex-wrap justify-center gap-4">
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-gray-900">{{ $totalInstituciones }}</span>
            <p class="text-xs font-medium text-gray-500">Instituciones</p>
        </div>
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-emerald-600">{{ $institucionesConEquipos }}</span>
            <p class="text-xs font-medium text-gray-500">Con equipos</p>
        </div>
        <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
            <span class="text-2xl font-bold text-amber-600">{{ $totalEquiposSinInstitucion }}</span>
            <p class="text-xs font-medium text-gray-500">Sin institución</p>
        </div>
    </div>

    {{-- Filtro de Edición --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
        <form action="{{ route('institucion.index') }}" method="GET" class="max-w-xs">
            <x-ui.select label="Filtrar por edición" name="idEdicion" onchange="this.form.submit()">
                <option value="">Todas las ediciones</option>
                @foreach ($ediciones as $edicion)
                    <option value="{{ $edicion->id }}" {{ $EdicionSeleccionada && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                        {{ $edicion->nombre }}
                    </option>
                @endforeach
            </x-ui.select>
        </form>
    </div>

    {{-- Crear Nueva Institución --}}
    <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
        <h3 class="mb-3 font-semibold text-blue-800">
            Crear Nueva Institución
        </h3>
        <form action="{{ route('institucion.store') }}" method="POST" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="min-w-64 flex-1">
                <label class="mb-1 block text-sm font-medium text-gray-700">Nombre de la institución:</label>
                <input type="text" name="nombre" required placeholder="Ej: Escuela San José"
                    class="w-full rounded-lg border-gray-300 px-3 py-2 focus:ring-2 focus:ring-brand-navy/30">
            </div>
            <x-ui.button type="submit" variant="primary">
                Crear Institución
            </x-ui.button>
        </form>
        @error('nombre')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Sección de Equipos Sin Institución --}}
    @if ($equiposSinInstitucion->count() > 0)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h3 class="mb-3 font-semibold text-amber-800">
                Equipos sin Institución ({{ $equiposSinInstitucion->count() }})
            </h3>
            <p class="mb-4 text-sm text-amber-700">
                Estos equipos no tienen una institución asignada. Vincúlalos a una institución existente para que sus
                jugadores puedan ser validados correctamente en otras categorías.
            </p>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($equiposSinInstitucion as $equipo)
                    <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-white p-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100">
                            @if ($equipo->foto)
                                <img src="{{ asset('fotos/equipos/'.$equipo->foto) }}" class="h-full w-full object-cover" alt="{{ $equipo->nombre }}">
                            @else
                                <span class="text-xs font-bold text-gray-600">{{ substr($equipo->nombre, 0, 2) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800">{{ $equipo->nombre }}</p>
                            @if ($EdicionSeleccionada)
                                @foreach ($equipo->equipoEdiciones as $ee)
                                    <p class="text-xs text-gray-500">{{ $ee->categoria->nombreCategoria ?? 'Sin categoría' }}</p>
                                @endforeach
                            @endif
                        </div>
                        {{-- Selector para asignar institución --}}
                        <form action="{{ route('institucion.cambiarEquipo') }}" method="POST" class="shrink-0">
                            @csrf
                            <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                            <select name="institucion_id"
                                onchange="if(confirm('¿Vincular este equipo a la institución seleccionada?')) this.form.submit();"
                                class="w-32 rounded border-gray-300 bg-white text-xs focus:border-brand-navy focus:ring-brand-navy/30">
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
        <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="text-sm text-green-700">
                ¡Excelente! Todos los equipos tienen una institución asignada.
            </p>
        </div>
    @endif

    {{-- Lista de Instituciones --}}
    <h2 class="mb-4 mt-6 text-lg font-semibold text-gray-800">Instituciones Registradas</h2>

    @if ($instituciones->isEmpty())
        <x-ui.empty-state message="No hay instituciones registradas" />
    @else
        <div class="space-y-4">
            @foreach ($instituciones as $institucion)
                @php
                    $equipos = $equiposPorInstitucion[$institucion->id] ?? collect();
                    $tieneEquipos = $equipos->count() > 0 || ! $EdicionSeleccionada;
                @endphp

                @if ($tieneEquipos || ! $EdicionSeleccionada)
                    <div class="overflow-hidden rounded-xl border border-gray-200 {{ $equipos->count() > 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <div class="flex items-center justify-between bg-gray-100 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.badge variant="info">ID: {{ $institucion->id }}</x-ui.badge>
                                <h3 class="font-semibold text-gray-800">{{ $institucion->nombre }}</h3>
                                @if ($EdicionSeleccionada)
                                    <span class="text-sm text-gray-500">
                                        ({{ $equipos->count() }} en esta edición / {{ $institucion->equipos_count }} total)
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500">({{ $institucion->equipos_count }} equipos total)</span>
                                @endif
                            </div>
                            <button type="button" onclick="toggleEdit(this.dataset.institucionId)" data-institucion-id="{{ $institucion->id }}"
                                class="text-sm font-medium text-brand-navy hover:underline">
                                Editar
                            </button>
                        </div>

                        {{-- Form de edición (oculto por defecto) --}}
                        <div id="edit-form-{{ $institucion->id }}" class="hidden border-b border-blue-100 bg-blue-50 px-4 py-2">
                            <form action="{{ route('institucion.update', $institucion) }}" method="POST"
                                class="flex items-center gap-4">
                                @csrf
                                @method('PUT')
                                <input type="text" name="nombre" value="{{ $institucion->nombre }}"
                                    class="flex-1 rounded-lg border-gray-300 px-3 py-1" required>
                                <x-ui.button type="submit" size="sm" variant="primary">
                                    Guardar
                                </x-ui.button>
                                <button type="button" onclick="toggleEdit(this.dataset.institucionId)" data-institucion-id="{{ $institucion->id }}" class="text-sm text-gray-600">
                                    Cancelar
                                </button>
                            </form>
                        </div>

                        {{-- Equipos de esta institución --}}
                        @if ($equipos->count() > 0)
                            <div class="px-4 py-3">
                                <p class="mb-2 text-sm font-medium text-gray-600">
                                    Equipos en {{ $EdicionSeleccionada->nombre ?? 'esta edición' }}:
                                </p>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($equipos as $equipo)
                                        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-2">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-200">
                                                @if ($equipo->foto)
                                                    <img src="{{ asset('fotos/equipos/'.$equipo->foto) }}" class="h-full w-full object-cover" alt="{{ $equipo->nombre }}">
                                                @else
                                                    <span class="text-xs text-gray-600">{{ substr($equipo->nombre, 0, 2) }}</span>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-gray-800">{{ $equipo->nombre }}</p>
                                                @foreach ($equipo->equipoEdiciones as $ee)
                                                    <p class="text-xs text-gray-500">{{ $ee->categoria->nombreCategoria ?? 'Sin categoría' }}</p>
                                                @endforeach
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1">
                                                {{-- Selector para cambiar institución --}}
                                                <form action="{{ route('institucion.cambiarEquipo') }}" method="POST" class="shrink-0">
                                                    @csrf
                                                    <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                                                    <select name="institucion_id"
                                                        onchange="if(confirm('¿Mover este equipo a otra institución?')) this.form.submit();"
                                                        class="w-20 rounded border-gray-200 bg-white text-xs">
                                                        @foreach ($instituciones as $inst)
                                                            <option value="{{ $inst->id }}" {{ $inst->id == $institucion->id ? 'selected' : '' }}>
                                                                {{ Str::limit($inst->nombre, 12) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                                {{--
                                                    institucion.desvincularEquipo está registrada solo como POST
                                                    (sin ruta DELETE equivalente), así que x-ui.confirm-delete
                                                    -que hace method spoofing a DELETE- rompería este borrado
                                                    con un 404. Se conserva el POST real con confirmación nativa
                                                    real (no decorativa), que ya cumple "toda acción destructiva
                                                    pide confirmación".
                                                --}}
                                                <form action="{{ route('institucion.desvincularEquipo') }}" method="POST"
                                                    class="shrink-0">
                                                    @csrf
                                                    <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                                                    <button type="submit"
                                                        onclick="return confirm('¿Desvincular este equipo de la institución?')"
                                                        class="p-1 text-red-500 hover:text-red-700" title="Desvincular de institución">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                        @elseif (! $EdicionSeleccionada)
                            <div class="px-4 py-2 text-sm italic text-gray-500">
                                Selecciona una edición para ver los equipos
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <script>
        function toggleEdit(id) {
            const form = document.getElementById('edit-form-' + id);
            form.classList.toggle('hidden');
        }
    </script>
@endsection
