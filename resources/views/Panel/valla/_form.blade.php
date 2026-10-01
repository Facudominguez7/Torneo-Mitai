@csrf
<div class="space-y-5">
    @if (isset($listaEquiposVallas) && $listaEquiposVallas->count() > 0)
        <div>
            <label for="selectValla" class="mb-1 block text-sm font-medium text-gray-700">
                Equipos ordenados por goles en contra (menor a mayor)
            </label>
            <select id="selectValla" name="selectValla"
                class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm text-gray-700 shadow-sm focus:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-navy/30"
                onchange="if(this.value) document.getElementById('idEquipo').value = this.value;">
                <option value="">-- Seleccionar equipo --</option>
                @foreach ($listaEquiposVallas as $eq)
                    <option value="{{ $eq->idEquipo }}">
                        {{ $eq->nombreEquipo }} - {{ $eq->golesContra ?? 0 }} goles en contra
                    </option>
                @endforeach
            </select>
        </div>
        {{-- Input hidden para el equipo, se autocompleta al seleccionar del dropdown --}}
        <input type="hidden" name="idEquipo" id="idEquipo" value="{{ old('idEquipo', $valla->idEquipo) }}">
    @endif

    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre del arquero
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $valla->nombre) }}"
            class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm text-gray-700 shadow-sm focus:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-navy/30" />
    </div>

    @if (!isset($listaEquiposVallas) || $listaEquiposVallas->count() == 0)
        <x-ui.select label="Seleccionar equipo" name="idEquipo" id="idEquipo">
            {{-- create() pasa EquipoEdicion (idEquipo, nombreEquipo) y edit() pasa Equipo (id, nombre). --}}
            @foreach ($equipos as $equipo)
                @php($idOpcion = $equipo->idEquipo ?? $equipo->id)
                <option value="{{ $idOpcion }}"
                    {{ old('idEquipo', $valla->idEquipo) == $idOpcion ? 'selected' : '' }}>
                    {{ $equipo->nombreEquipo ?? $equipo->nombre }}
                </option>
            @endforeach
        </x-ui.select>
    @endif

    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </x-ui.select>

    @if (isset($CategoriaSeleccionada))
        <x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}</option>
        </x-ui.select>
    @else
        <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
    @endif
</div>
