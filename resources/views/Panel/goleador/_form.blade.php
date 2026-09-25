@csrf
@if (isset($listaGoleadores) && $listaGoleadores->count() > 0)
    <div class="mb-5">
        <label class="mb-3 block text-base font-medium text-white">
            💡 Seleccionar de la tabla de goleadores (ordenados por goles)
        </label>
        <select id="selectGoleador"
            class="w-full rounded-md border border-emerald-400/50 bg-brand-navy py-3 px-6 text-base font-medium text-white outline-none focus:border-emerald-400 focus:shadow-md"
            style="color-scheme: dark;"
            onchange="if(this.value) { document.getElementById('nombre').value = this.selectedOptions[0].dataset.nombre; document.getElementById('idEquipo').value = this.selectedOptions[0].dataset.equipo; }">
            <option value="" style="background-color: var(--color-primary); color: white;">-- Seleccionar jugador --</option>
            @foreach ($listaGoleadores as $g)
                <option value="{{ $g->id }}"
                    data-nombre="{{ $g->jugador ? $g->jugador->apellido . ' ' . $g->jugador->nombre : 'Desconocido' }}"
                    data-equipo="{{ $g->idEquipo }}" style="background-color: var(--color-primary); color: white;">
                    {{ $g->jugador ? $g->jugador->apellido . ' ' . $g->jugador->nombre : 'Desconocido' }}
                    ({{ $g->equipo->nombre ?? 'Equipo' }}) - {{ $g->cantidadGoles }} goles
                </option>
            @endforeach
        </select>
    </div>
    {{-- Input hidden para el equipo, se autocompleta al seleccionar goleador --}}
    <input type="hidden" name="idEquipo" id="idEquipo" value="{{ old('idEquipo', $goleador->idEquipo) }}">
@endif
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre del Jugador
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $goleador->nombre)}}"
        class="panel-form-input" />
</div>
@if (!isset($listaGoleadores) || $listaGoleadores->count() == 0)
    <div class="mb-5">
        <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
            Seleccionar Equipo
        </label>
        <select name="idEquipo" id="idEquipo"
            class="panel-form-input">
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->idEquipo }}" {{ old('idEquipo', $goleador->idEquipo) == $equipo->idEquipo ? 'selected' : '' }}>
                    {{ $equipo->nombreEquipo }}
                </option>
            @endforeach
        </select>
    </div>
@endif
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <select name="idEdicion" id="idEdicion"
        class="panel-form-input">

        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </select>
</div>
@if (isset($CategoriaSeleccionada))
    <div class="mb-5">
        <label for="nombre" class="mb-3 block text-base font-medium text-white">
            Categoria
        </label>
        <select name="idCategoria" id="idCategoria"
            class="panel-form-input">

            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </select>
    </div>
@else
    <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
@endif