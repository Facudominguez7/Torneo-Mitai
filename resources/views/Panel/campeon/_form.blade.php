@csrf
<div class="space-y-5">
    <x-ui.select label="Seleccionar equipo" name="idEquipo" id="idEquipo">
        {{-- create() pasa EquipoEdicion (idEquipo, nombreEquipo) y edit() pasa Equipo (id, nombre). --}}
        @foreach ($equipos as $equipo)
            @php($idOpcion = $equipo->idEquipo ?? $equipo->id)
            <option value="{{ $idOpcion }}"
                {{ old('idEquipo', $campeon->idEquipo) == $idOpcion ? 'selected' : '' }}>
                {{ $equipo->nombreEquipo ?? $equipo->nombre }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Seleccionar copa" name="idCopa" id="idCopa">
        @foreach ($copas as $copa)
            <option value="{{ $copa->id }}" {{ old('idCopa', $campeon->idCopa) == $copa->id ? 'selected' : '' }}>
                {{ $copa->nombre }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </x-ui.select>

    @if (isset($CategoriaSeleccionada))
        <x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </x-ui.select>
    @else
        <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
    @endif
</div>
