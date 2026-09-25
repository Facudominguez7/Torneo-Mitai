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
<div class="mb-6">
    <div class="flex flex-wrap items-center gap-3 md:gap-4 bg-white/10 border border-white/10 rounded-lg p-3 md:p-4">
        @if (isset($GrupoSeleccionado))
            <div class="inline-flex items-center gap-2">
                <span class="text-sm text-gray-100">Grupo:</span>
                <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-800 px-3 py-1 text-sm font-semibold shadow-sm">
                    {{ $GrupoSeleccionado->nombre }}
                </span>
                @if (!$partido->exists)
                    <input type="hidden" name="idGrupo" id="idGrupo" value="{{ $GrupoSeleccionado->id }}">
                @endif
            </div>
        @else
            @if (!$partido->exists)
                <input class="hidden" type="number" name="idGrupo" id="idGrupo" value="{{ $idGrupo }}">
            @endif
        @endif

        @if (isset($CategoriaSeleccionada))
            <div class="inline-flex items-center gap-2">
                <span class="text-sm text-gray-100">Categoría:</span>
                <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-800 px-3 py-1 text-sm font-semibold shadow-sm">
                    {{ $CategoriaSeleccionada->nombreCategoria }}
                </span>
                @if (!$partido->exists)
                    <input type="hidden" name="idCategoria" id="idCategoria" value="{{ $CategoriaSeleccionada->id }}">
                @endif
            </div>
        @else
            @if (!$partido->exists)
                <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
            @endif
        @endif
    </div>
</div>
@error('idGrupo')
    <p class="text-red-600 text-sm mb-2">{{ $message }}</p>
@enderror
@error('idCategoria')
    <p class="text-red-600 text-sm mb-2">{{ $message }}</p>
@enderror
<div class="mb-5">
    <label for="idEquipo" class="mb-2 md:mb-3 block text-base font-medium text-white">
        Seleccionar Equipo Local
    </label>
    <select name="idEquipoLocal" id="idEquipoLocal"
        class="panel-form-input">
    <option value="" disabled {{ (string) old('idEquipoLocal', $partido->idEquipoLocal ?? '') === '' ? 'selected' : '' }}>-- Seleccionar equipo local --</option>
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ (string) old('idEquipoLocal', $partido->idEquipoLocal ?? '') === (string) $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </select>
    @error('idEquipoLocal')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
<div class="mb-5">
    <label for="idEquipo" class="mb-2 md:mb-3 block text-base font-medium text-white">
        Seleccionar Equipo Visitante
    </label>
    <select name="idEquipoVisitante" id="idEquipoVisitante"
        class="panel-form-input">
    <option value="" disabled {{ (string) old('idEquipoVisitante', $partido->idEquipoVisitante ?? '') === '' ? 'selected' : '' }}>-- Seleccionar equipo visitante --</option>
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ (string) old('idEquipoVisitante', $partido->idEquipoVisitante ?? '') === (string) $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </select>
    @error('idEquipoVisitante')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
<div class="mb-5">
    <label for="idFechas" class="mb-2 md:mb-3 block text-base font-medium text-white">
        Seleccionar Fecha
    </label>
    <select name="idFechas" id="idFechas"
        class="panel-form-input">
        @foreach ($fechas as $f)
            <option value="{{ $f->id }}"
                {{ (string) $fechaPorDefecto === (string) $f->id ? 'selected' : '' }}>
                {{ $f->nombre }}
            </option>
        @endforeach
    </select>
    @error('idFechas')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
<div class="mb-5">
    <label for="horario_datetime" class="mb-2 md:mb-3 block text-base font-medium text-white">
        Seleccionar Horario
    </label>
    <input type="datetime-local" name="horario_datetime" id="horario_datetime"
        class="panel-form-input"
        value="{{ $horarioPorDefecto }}">
    @error('horario_datetime')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
<div class="mb-5">
    <label for="cancha" class="mb-2 md:mb-3 block text-base font-medium text-white">
        Cancha
    </label>
    <input type="text" name="cancha" id="cancha" value="{{ old('cancha', $partido->cancha) }}"
        class="panel-form-input"
         />
    @error('cancha')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
@if (!$partido->exists)
    <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
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
