@csrf
<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo
    </label>
    <select name="idEquipo" id="idEquipo"
        class="panel-form-input">
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->idEquipo }}"
                {{ old('idEquipo', $campeon->idEquipo) == $equipo->idEquipo ? 'selected' : '' }}>
                {{ $equipo->nombreEquipo }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="idCopa" class="mb-3 block text-base font-medium text-white">
        Seleccionar Copa
    </label>
    <select name="idCopa" id="idCopa"
        class="panel-form-input">
        @foreach ($copas as $copa)
            <option value="{{ $copa->id }}" {{ old('idCopa', $campeon->idCopa) == $copa->id ? 'selected' : '' }}>
                {{ $copa->nombre }}
            </option>
        @endforeach
    </select>
</div>
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
