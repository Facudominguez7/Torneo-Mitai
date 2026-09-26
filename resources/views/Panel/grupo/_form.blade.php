@csrf
<div class="space-y-5">
    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre del Grupo
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $grupo->nombre) }}"
            class="panel-form-input" />
    </div>

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
