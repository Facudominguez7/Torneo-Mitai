@csrf
@php
    $esEdicionGoleador = Route::currentRouteName() === 'tabla_goleador.edit';
    $nombreJugadorMostrar = old('nombre');

    if ($nombreJugadorMostrar === null) {
        $nombreJugadorMostrar = $goleador_t->nombre_completo ?? null;
    }

    if (!$nombreJugadorMostrar) {
        $apellidoJugador = $goleador_t->jugador->apellido ?? '';
        $nombreJugador = $goleador_t->jugador->nombre ?? '';
        $nombreJugadorMostrar = trim($apellidoJugador . ' ' . $nombreJugador);
    }

    if (!$nombreJugadorMostrar) {
        $nombreJugadorMostrar = 'Sin jugador asociado';
    }
@endphp
<div class="p-6 rounded shadow-sm bg-gray-800 text-white mb-5">
    <div class="card-body">
        @if ($esEdicionGoleador)
        <p class="text-lg font-semibold">
            La información del jugador se va a actualizar en la
            <span class="text-yellow-300">
                "{{ isset($CategoriaSeleccionada) ? $CategoriaSeleccionada->nombreCategoria : $idCategoria }}"
            </span>
            de la
            <span class="text-yellow-300">
                "{{ $EdicionSeleccionada->nombre }}"
            </span>
        </p>
        @else
        <p class="text-lg font-semibold">
            El jugador se va a agregar en la
            <span class="text-yellow-300">
                "{{ isset($CategoriaSeleccionada) ? $CategoriaSeleccionada->nombreCategoria : $idCategoria }}"
            </span>
            de la
            <span class="text-yellow-300">
                "{{ $EdicionSeleccionada->nombre }}"
            </span>
        </p>
        @endif
    </div>
</div>
<input type="hidden" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
<input type="hidden" name="idCategoria" id="idCategoria" value="{{ $CategoriaSeleccionada->id}}">
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre del Jugador
    </label>
    <input type="text" name="nombre" id="nombre" value="{{ $nombreJugadorMostrar }}"
        {{ $esEdicionGoleador ? 'readonly' : '' }}
        class="panel-form-input" />
    @if ($esEdicionGoleador)
        <p class="mt-2 text-xs text-white/70">
            El nombre se obtiene desde el jugador asociado por DNI en la tabla de goleadores.
        </p>
    @endif
</div>
<div class="mb-5">
    <label for="goles" class="mb-3 block text-base font-medium text-white">
        Goles
    </label>
    <input type="number" name="cantidadGoles" id="cantidadGoles" value="{{old('cantidadGoles', $goleador_t->cantidadGoles)}}"
        class="panel-form-input" />
</div>
@if ($esEdicionGoleador)
    <div class="mb-5">
        <label class="mb-3 block text-base font-medium text-white">
            Equipo (automatico segun jugador)
        </label>
        <input type="text"
            value="{{ $goleador_t->equipo->nombre ?? 'Sin equipo asociado' }}"
            readonly
            class="panel-form-input-static" />
        <input type="hidden" name="idEquipo" id="idEquipo" value="{{ $goleador_t->idEquipo }}">
    </div>
@else
    <div class="mb-5">
        <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
            Seleccionar Equipo
        </label>
        <select name="idEquipo" id="idEquipo"
            class="panel-form-input">
            @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ old('idEquipo', $goleador_t->idEquipo) == $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
            @endforeach
        </select>
    </div>
@endif
