@csrf
<div class="mb-5">
    <label class="mb-2 block text-sm font-semibold text-indigo-200">Contexto</label>
    <div class="text-xs text-gray-300 space-y-1 bg-gray-800 p-3 rounded">
        <p>Edición destino: <span class="font-semibold text-white">{{ $EdicionSeleccionada->nombre }} (ID {{ $EdicionSeleccionada->id }})</span></p>
        @if(request('edicionfiltro'))
            <p>Edición origen: <span class="font-semibold">{{ optional($ediciones->firstWhere('id', request('edicionfiltro')))->nombre }} (ID {{ request('edicionfiltro') }})</span></p>
        @endif
        @if(request('idCategoria'))
            <p>Categoría: <span class="font-semibold">{{ optional($CategoriaSeleccionada)->nombreCategoria }}</span></p>
        @endif
    </div>
</div>

<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo (solo uno)
    </label>
    <select name="idEquipo" id="idEquipo"
        class="w-full rounded-md border border-[#444] bg-white py-3 px-6 text-base font-medium text-[#374151] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <option value="">-- Elegir --</option>
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}" {{ (old('idEquipo', request('idEquipo')) == $equipo->id) ? 'selected' : '' }}>{{ $equipo->nombre }}</option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label class="mb-3 block text-base font-medium text-white">Edición destino</label>
    <input type="text" value="{{ $EdicionSeleccionada->nombre }}" class="w-full rounded-md border border-[#444] bg-gray-100 py-3 px-6 text-base font-medium text-gray-700" readonly>
    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    <input type="hidden" name="edicionfiltro" value="{{ request('edicionfiltro') }}">
    <input type="hidden" name="idCategoria" value="{{ request('idCategoria') }}">
</div>
