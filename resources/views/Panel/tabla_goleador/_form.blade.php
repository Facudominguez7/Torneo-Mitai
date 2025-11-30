@csrf
<div class="card bg-gray-800 text-white mb-5">
    <div class="card-body">
        @if (Route::currentRouteName() == 'tabla_goleador.edit')
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
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $goleador_t->nombre)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
</div>
<div class="mb-5">
    <label for="goles" class="mb-3 block text-base font-medium text-white">
        Goles
    </label>
    <input type="number" name="cantidadGoles" id="cantidadGoles" value="{{old('cantidadGoles', $goleador_t->cantidadGoles)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
</div>
<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo
    </label>
    <select name="idEquipo" id="idEquipo"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($equipos as $equipo)
        <option value="{{ $equipo->id }}"
            {{ old('idEquipo', $goleador_t->idEquipo) == $equipo->id ? 'selected' : '' }}>
            {{ $equipo->nombre }}
        </option>
        @endforeach
    </select>
</div>