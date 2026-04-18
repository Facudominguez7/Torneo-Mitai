<form action="{{ $accion }}" method="POST" class="space-y-4 rounded-2xl bg-white p-6 shadow-lg">
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    <input type="hidden" name="idEdicion" value="{{ old('idEdicion', $tablaPosicion->idEdicion ?? '') }}">
    <input type="hidden" name="idCategoria" value="{{ old('idCategoria', $idCategoria ?? '') }}">
    <input type="hidden" name="idGrupo" value="{{ old('idGrupo', $idGrupo ?? $tablaPosicion->idGrupo ?? '') }}">

    <div>
        <label class="block text-sm font-semibold text-gray-700">Edición</label>
        <div class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-700">
            {{ $EdicionSeleccionada?->nombre ?? 'Sin edición seleccionada' }}
        </div>
    </div>

    <div>
        <label for="idGrupo" class="block text-sm font-semibold text-gray-700">Grupo</label>
        <select id="idGrupo" name="idGrupo" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
            <option value="">Seleccionar grupo</option>
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo->id }}" {{ old('idGrupo', $tablaPosicion->idGrupo ?? '') == $grupo->id ? 'selected' : '' }}>
                    {{ $grupo->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="idEquipo" class="block text-sm font-semibold text-gray-700">Equipo</label>
        <select id="idEquipo" name="idEquipo" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
            <option value="">Seleccionar equipo</option>
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}" {{ old('idEquipo', $tablaPosicion->idEquipo ?? '') == $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700">Puntos</label>
            <input type="number" name="puntos" value="{{ old('puntos', $tablaPosicion->puntos ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">GF</label>
            <input type="number" name="golesFavor" value="{{ old('golesFavor', $tablaPosicion->golesFavor ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">GC</label>
            <input type="number" name="golesContra" value="{{ old('golesContra', $tablaPosicion->golesContra ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">DG</label>
            <input type="number" name="diferenciaGoles" value="{{ old('diferenciaGoles', $tablaPosicion->diferenciaGoles ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">Jugado</label>
            <input type="number" name="jugado" value="{{ old('jugado', $tablaPosicion->jugado ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">Ganado</label>
            <input type="number" name="ganado" value="{{ old('ganado', $tablaPosicion->ganado ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">Perdido</label>
            <input type="number" name="perdido" value="{{ old('perdido', $tablaPosicion->perdido ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700">Empatado</label>
            <input type="number" name="empatado" value="{{ old('empatado', $tablaPosicion->empatado ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2">
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('tabla_posicion.index', $parametrosRegreso) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Volver</a>
        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
    </div>
</form>
