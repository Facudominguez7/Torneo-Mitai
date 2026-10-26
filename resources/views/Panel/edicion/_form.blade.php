@csrf
<div class="space-y-5">
    <div>
        <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">
            Nombre de la Edición
        </label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $edicion->nombre) }}"
            class="panel-form-input" />
        @if (request()->routeIs('edicion.create'))
            <p class="mt-2 text-xs text-gray-500">
                Los equipos de ediciones anteriores se agregan luego desde Equipos con el botón
                "Agregar Equipo de una Edición Anterior".
            </p>
        @endif
    </div>

    <div>
        <label for="jugadores_por_planilla" class="mb-1 block text-sm font-medium text-gray-700">
            Jugadores por Planilla
        </label>
        <input type="number" name="jugadores_por_planilla" id="jugadores_por_planilla"
            value="{{ old('jugadores_por_planilla', $edicion->jugadores_por_planilla ?? 18) }}" min="1" max="40"
            class="panel-form-input" />
        <p class="mt-2 text-xs text-gray-500">
            Este valor define cuántas filas tendrá la planilla de partido y también el límite de jugadores activos
            permitidos por equipo en esta edición.
        </p>
    </div>
</div>
