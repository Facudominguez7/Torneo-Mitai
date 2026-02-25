@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">Nombre de la Edición</label>
    <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $edicion->nombre) }}"
        class="w-full rounded-md border border-gray-300 bg-white py-2 px-4 text-base font-medium text-gray-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" />
    @if (request()->routeIs('edicion.create'))
        <p class="mt-2 text-xs text-indigo-200">
            Los equipos de ediciones anteriores se agregan luego desde Equipos con el botón
            "Agregar Equipo de una Edición Anterior".
        </p>
    @endif
</div>
