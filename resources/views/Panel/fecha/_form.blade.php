@csrf
<div class="space-y-5">
    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre de la Fecha
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $fecha->nombre) }}"
            class="panel-form-input" />
    </div>

    <x-ui.select label="Edición" name="idEdicion" id="idEdicion">
        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </x-ui.select>
</div>
