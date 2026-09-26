@csrf
<div class="space-y-5">
    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre del Equipo
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $equipo->nombre) }}"
            class="panel-form-input" />
    </div>

    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </x-ui.select>

    <x-ui.select label="Categoría del Equipo" name="idCategoria" id="idCategoria">
        @foreach ($categorias as $id => $nombreCategoria)
            <option {{ old('idCategoria', $equipo->idCategoria) == $id ? 'selected' : '' }} value="{{ $id }}">
                {{ $nombreCategoria }}
            </option>
        @endforeach
    </x-ui.select>

    <div>
        <label for="foto" class="mb-1 block text-sm font-medium text-gray-700">
            Agregar Logo
        </label>
        <input type="file" id="foto" name="foto" class="panel-form-input">
    </div>
</div>
