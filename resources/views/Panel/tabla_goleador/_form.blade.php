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

<div class="space-y-5">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
        @if ($esEdicionGoleador)
            La información del jugador se va a actualizar en la
            <span class="font-semibold text-brand-navy">{{ isset($CategoriaSeleccionada) ? $CategoriaSeleccionada->nombreCategoria : $idCategoria }}</span>
            de la
            <span class="font-semibold text-brand-navy">{{ $EdicionSeleccionada->nombre }}</span>
        @else
            El jugador se va a agregar en la
            <span class="font-semibold text-brand-navy">{{ isset($CategoriaSeleccionada) ? $CategoriaSeleccionada->nombreCategoria : $idCategoria }}</span>
            de la
            <span class="font-semibold text-brand-navy">{{ $EdicionSeleccionada->nombre }}</span>
        @endif
    </div>

    <input type="hidden" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    <input type="hidden" name="idCategoria" id="idCategoria" value="{{ $CategoriaSeleccionada->id }}">

    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre del Jugador
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ $nombreJugadorMostrar }}"
            {{ $esEdicionGoleador ? 'readonly' : '' }}
            class="panel-form-input">
        @if ($esEdicionGoleador)
            <p class="mt-1 text-xs text-gray-500">
                El nombre se obtiene desde el jugador asociado por DNI en la tabla de goleadores.
            </p>
        @endif
    </div>

    <div>
        <label for="cantidadGoles" class="mb-1 block text-sm font-medium text-gray-700">
            Goles
        </label>
        <input type="number" name="cantidadGoles" id="cantidadGoles" value="{{ old('cantidadGoles', $goleador_t->cantidadGoles) }}"
            class="panel-form-input">
    </div>

    @if ($esEdicionGoleador)
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
                Equipo (automático según jugador)
            </label>
            <input type="text" value="{{ $goleador_t->equipo->nombre ?? 'Sin equipo asociado' }}" readonly class="panel-form-input-static">
            <input type="hidden" name="idEquipo" id="idEquipo" value="{{ $goleador_t->idEquipo }}">
        </div>
    @else
        <x-ui.select label="Seleccionar Equipo" name="idEquipo" id="idEquipo">
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}" {{ old('idEquipo', $goleador_t->idEquipo) == $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
                </option>
            @endforeach
        </x-ui.select>
    @endif
</div>
