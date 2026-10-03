@extends('Panel.admin')

@section('contenido')
    @php
        $parametrosRegreso = $parametrosRegreso ?? [
            'idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion'),
            'idCategoria' => $idCategoria ?? request('idCategoria'),
        ];
        $fechaPorDefecto = old('idFechas', $fechaPorDefecto);
        $horarioPorDefecto = old('horario_datetime', $horarioPorDefecto);
    @endphp

    <x-ui.page-header title="Nuevo partido interzonal" />

    @include('Panel.fragment._errores-formulario')

    <div class="mb-4">
        <a href="{{ route('partido.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <span class="font-semibold text-amber-800">Partido interzonal</span>
        <span class="text-sm text-amber-700">Los equipos de diferentes grupos jugarán entre sí.</span>
    </div>

    <form action="{{ route('partido.store-interzonal') }}" method="POST" class="mt-6">
        @csrf
        <x-ui.form-card>
            <div class="space-y-5">
                @if (isset($CategoriaSeleccionada))
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge variant="neutral">Categoría: {{ $CategoriaSeleccionada->nombreCategoria }}</x-ui.badge>
                        <input type="hidden" name="idCategoria" value="{{ $CategoriaSeleccionada->id }}">
                    </div>
                @endif

                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idGrupo" value="{{ $parametrosRegreso['idGrupo'] ?? '' }}">
                <input type="hidden" name="retorno_idFecha" value="{{ $parametrosRegreso['idFecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">

                <div>
                    <x-ui.select label="Equipo local" name="idEquipoLocal" id="idEquipoLocal">
                        <option value="" disabled selected>-- Seleccionar equipo local --</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" data-grupo="{{ $equipo->grupo_id }}"
                                {{ (string) old('idEquipoLocal') === (string) $equipo->id ? 'selected' : '' }}>
                                {{ $equipo->nombre }} ({{ $equipo->grupo_nombre }})
                            </option>
                        @endforeach
                    </x-ui.select>
                    @error('idEquipoLocal')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.select label="Equipo visitante" name="idEquipoVisitante" id="idEquipoVisitante">
                        <option value="" disabled selected>-- Seleccionar equipo visitante --</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" data-grupo="{{ $equipo->grupo_id }}"
                                {{ (string) old('idEquipoVisitante') === (string) $equipo->id ? 'selected' : '' }}>
                                {{ $equipo->nombre }} ({{ $equipo->grupo_nombre }})
                            </option>
                        @endforeach
                    </x-ui.select>
                    @error('idEquipoVisitante')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.select label="Fecha" name="idFechas" id="idFechas">
                        @foreach ($fechas as $f)
                            <option value="{{ $f->id }}"
                                {{ (string) $fechaPorDefecto === (string) $f->id ? 'selected' : '' }}>
                                {{ $f->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                    @error('idFechas')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="horario_datetime" class="mb-1 block text-sm font-medium text-gray-700">
                        Horario
                    </label>
                    <input type="datetime-local" name="horario_datetime" id="horario_datetime"
                        class="panel-form-input"
                        value="{{ $horarioPorDefecto }}">
                    @error('horario_datetime')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="cancha" class="mb-1 block text-sm font-medium text-gray-700">
                        Cancha
                    </label>
                    <input type="text" name="cancha" id="cancha" value="{{ old('cancha') }}"
                        class="panel-form-input" />
                    @error('cancha')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Crear partido interzonal
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const localSel = document.getElementById('idEquipoLocal');
        const visitanteSel = document.getElementById('idEquipoVisitante');

        function syncDisabledOptions() {
            const l = localSel.value;
            const v = visitanteSel.value;
            // Habilitar todas primero
            [...localSel.options].forEach(o => o.disabled = false);
            [...visitanteSel.options].forEach(o => o.disabled = false);
            // Deshabilitar selección cruzada igual
            if (l) {
                const optV = [...visitanteSel.options].find(o => o.value === l);
                if (optV) optV.disabled = true;
            }
            if (v) {
                const optL = [...localSel.options].find(o => o.value === v);
                if (optL) optL.disabled = true;
            }
        }

        function validarEquiposDistintos() {
            const l = localSel.value;
            const v = visitanteSel.value;
            syncDisabledOptions();
            if (l && v && l === v) {
                alert('El equipo local y el visitante deben ser distintos.');
                return false;
            }
            return true;
        }

        // Inicializar estado
        syncDisabledOptions();
        localSel.addEventListener('change', validarEquiposDistintos);
        visitanteSel.addEventListener('change', validarEquiposDistintos);

        // Evitar submit si son iguales
        const form = localSel.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!validarEquiposDistintos()) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
@endsection
