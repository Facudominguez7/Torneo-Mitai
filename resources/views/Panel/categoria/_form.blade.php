@csrf
<div class="space-y-5">
    <div>
        <label for="nombreCategoria" class="mb-1 block text-sm font-medium text-gray-700">
            Categoría
        </label>
        <input type="text" name="nombreCategoria" id="nombreCategoria"
            value="{{ old('nombreCategoria', $categoria->nombreCategoria) }}"
            class="panel-form-input" />
        <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    </div>

    <div>
        <label for="jugadores_maximos" class="mb-1 block text-sm font-medium text-gray-700">
            Jugadores máximos permitidos
        </label>
        <input type="number" name="jugadores_maximos" id="jugadores_maximos"
            value="{{ old('jugadores_maximos', $categoria->jugadores_maximos) }}"
            min="1" max="100"
            placeholder="Por defecto de la edición: {{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }}"
            class="panel-form-input" />
        <p class="mt-1 text-sm text-gray-500">
            Dejar vacío para usar el límite de la edición ({{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }} jugadores).
        </p>
    </div>
</div>
