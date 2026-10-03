@csrf
@php
    $fechaPorDefecto = old('idFechas', $partido->idFechas ?? ($fechaPorDefecto ?? optional($fechas->last())->id));

    $horarioPorDefecto = old(
        'horario_datetime',
        $partido->horario_datetime
            ? \Carbon\Carbon::parse($partido->horario_datetime)->format('Y-m-d\TH:i')
            : ($horarioPorDefecto ?? \Carbon\Carbon::now()->next(\Carbon\Carbon::SUNDAY)->startOfDay()->format('Y-m-d\TH:i'))
    );
@endphp

@if (isset($GrupoSeleccionado) || isset($CategoriaSeleccionada))
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @if (isset($GrupoSeleccionado))
            <x-ui.badge variant="neutral">Grupo: {{ $GrupoSeleccionado->nombre }}</x-ui.badge>
        @endif
        @if (isset($CategoriaSeleccionada))
            <x-ui.badge variant="neutral">Categoría: {{ $CategoriaSeleccionada->nombreCategoria }}</x-ui.badge>
        @endif
    </div>
@endif

@if (!$partido->exists)
    <input type="hidden" name="idGrupo" id="idGrupo" value="{{ $GrupoSeleccionado->id ?? $idGrupo }}">
    <input type="hidden" name="idCategoria" id="idCategoria" value="{{ $CategoriaSeleccionada->id ?? $idCategoria }}">
@endif

@error('idGrupo')
    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
@enderror
@error('idCategoria')
    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
@enderror

<div class="space-y-5">
    <div>
        <x-ui.select label="Equipo local" name="idEquipoLocal" id="idEquipoLocal">
            <option value="" disabled {{ (string) old('idEquipoLocal', $partido->idEquipoLocal ?? '') === '' ? 'selected' : '' }}>-- Seleccionar equipo local --</option>
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}"
                    {{ (string) old('idEquipoLocal', $partido->idEquipoLocal ?? '') === (string) $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
                </option>
            @endforeach
        </x-ui.select>
        @error('idEquipoLocal')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-ui.select label="Equipo visitante" name="idEquipoVisitante" id="idEquipoVisitante">
            <option value="" disabled {{ (string) old('idEquipoVisitante', $partido->idEquipoVisitante ?? '') === '' ? 'selected' : '' }}>-- Seleccionar equipo visitante --</option>
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}"
                    {{ (string) old('idEquipoVisitante', $partido->idEquipoVisitante ?? '') === (string) $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
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
        <input type="text" name="cancha" id="cancha" value="{{ old('cancha', $partido->cancha) }}"
            class="panel-form-input" />
        @error('cancha')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

@if (!$partido->exists)
    <input type="hidden" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
@endif

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
