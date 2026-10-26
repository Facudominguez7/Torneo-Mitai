@csrf

<input type="hidden" name="idEdicion" value="{{ old('idEdicion', $tablaPosicion->idEdicion ?? '') }}">
<input type="hidden" name="idCategoria" value="{{ old('idCategoria', $idCategoria ?? '') }}">
<input type="hidden" name="idGrupo" value="{{ old('idGrupo', $idGrupo ?? $tablaPosicion->idGrupo ?? '') }}">

<div class="space-y-5">
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Edición</label>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-700">
            {{ $EdicionSeleccionada?->nombre ?? 'Sin edición seleccionada' }}
        </div>
    </div>

    <x-ui.select label="Grupo" name="idGrupo" id="idGrupo">
        <option value="">Seleccionar grupo</option>
        @foreach ($grupos as $grupo)
            <option value="{{ $grupo->id }}" {{ old('idGrupo', $tablaPosicion->idGrupo ?? '') == $grupo->id ? 'selected' : '' }}>
                {{ $grupo->nombre }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Equipo" name="idEquipo" id="idEquipo">
        <option value="">Seleccionar equipo</option>
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}" {{ old('idEquipo', $tablaPosicion->idEquipo ?? '') == $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </x-ui.select>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Puntos</label>
            <input type="number" name="puntos" value="{{ old('puntos', $tablaPosicion->puntos ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">GF</label>
            <input type="number" name="golesFavor" value="{{ old('golesFavor', $tablaPosicion->golesFavor ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">GC</label>
            <input type="number" name="golesContra" value="{{ old('golesContra', $tablaPosicion->golesContra ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">DG</label>
            <input type="number" name="diferenciaGoles" value="{{ old('diferenciaGoles', $tablaPosicion->diferenciaGoles ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Jugado</label>
            <input type="number" name="jugado" value="{{ old('jugado', $tablaPosicion->jugado ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Ganado</label>
            <input type="number" name="ganado" value="{{ old('ganado', $tablaPosicion->ganado ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Perdido</label>
            <input type="number" name="perdido" value="{{ old('perdido', $tablaPosicion->perdido ?? 0) }}" class="panel-form-input">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Empatado</label>
            <input type="number" name="empatado" value="{{ old('empatado', $tablaPosicion->empatado ?? 0) }}" class="panel-form-input">
        </div>
    </div>
</div>
