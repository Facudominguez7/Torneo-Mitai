@csrf
<div class="space-y-5">
    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre de la Copa
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $copa->nombre) }}"
            class="panel-form-input" />
        <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    </div>
</div>
