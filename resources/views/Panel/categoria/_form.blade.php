@csrf
<div class="mb-5">
    <label for="nombreCategoria" class="mb-3 block text-base font-medium text-white">
        Categoría
    </label>
    <input type="text" name="nombreCategoria" id="nombreCategoria" value="{{ old('nombreCategoria', $categoria->nombreCategoria) }}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
    <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
</div>

<div class="mb-5">
    <label for="jugadores_maximos" class="mb-3 block text-base font-medium text-white">
        Jugadores máximos permitidos
    </label>
    <input type="number" name="jugadores_maximos" id="jugadores_maximos"
        value="{{ old('jugadores_maximos', $categoria->jugadores_maximos) }}"
        min="1" max="100"
        placeholder="Por defecto de la edición: {{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
    <p class="mt-1 text-sm text-gray-300">Dejar vacío para usar el límite de la edición ({{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }} jugadores).</p>
</div>


