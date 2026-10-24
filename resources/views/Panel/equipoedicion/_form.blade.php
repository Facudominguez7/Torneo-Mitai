@csrf
<div class="flex flex-wrap items-center gap-2">
    <x-ui.badge variant="neutral">Edición destino: {{ $EdicionSeleccionada->nombre }} (ID {{ $EdicionSeleccionada->id }})</x-ui.badge>
    @if(request('edicionfiltro'))
        <x-ui.badge variant="neutral">Edición origen: {{ optional($ediciones->firstWhere('id', request('edicionfiltro')))->nombre }} (ID {{ request('edicionfiltro') }})</x-ui.badge>
    @endif
    @if(request('idCategoria'))
        <x-ui.badge variant="neutral">Categoría: {{ optional($CategoriaSeleccionada)->nombreCategoria }}</x-ui.badge>
    @endif
</div>

<div>
    <x-ui.select label="Seleccionar equipo (solo uno)" name="idEquipo" id="idEquipo">
        <option value="">-- Elegir --</option>
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}" {{ (old('idEquipo', request('idEquipo')) == $equipo->id) ? 'selected' : '' }}>{{ $equipo->nombre }}</option>
        @endforeach
    </x-ui.select>
    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    <input type="hidden" name="edicionfiltro" value="{{ request('edicionfiltro') }}">
    <input type="hidden" name="idCategoria" value="{{ request('idCategoria') }}">
</div>
