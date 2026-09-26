@csrf
<div class="space-y-5">
    <x-ui.select label="Seleccionar Equipo" name="idEquipo" id="idEquipo">
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ old('idEquipo', $equipo->id) == $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </x-ui.select>

    @if (isset($GrupoSeleccionado))
        <x-ui.select label="Grupo" name="idGrupo" id="idGrupo">
            <option value="{{ $GrupoSeleccionado->id }}" selected>{{ $GrupoSeleccionado->nombre }}</option>
        </x-ui.select>
    @else
        <input class="hidden" type="number" name="idGrupo" id="idGrupo" value="{{ $idGrupo ?? '' }}">
    @endif

    @if (isset($CategoriaSeleccionada))
        <x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </x-ui.select>
    @else
        <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria ?? '' }}">
    @endif
</div>
