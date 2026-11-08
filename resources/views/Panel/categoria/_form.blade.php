@csrf
<div class="space-y-5">
    <div>
        <label for="nombreCategoria" class="mb-1 block text-sm font-medium text-pa-tinta-media">
            Categoría
        </label>
        <input type="text" name="nombreCategoria" id="nombreCategoria"
            value="{{ old('nombreCategoria', $categoria->nombreCategoria) }}"
            class="panel-form-input" />
        <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
    </div>

    <fieldset>
        <legend class="mb-1 block text-sm font-medium text-pa-tinta-media">
            Años de nacimiento permitidos
        </legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label for="anio_desde" class="mb-1 block text-sm text-pa-tinta-cuerpo">Desde</label>
                <input type="number" name="anio_desde" id="anio_desde"
                    value="{{ old('anio_desde', $categoria->anio_desde) }}"
                    min="2000" max="2099" placeholder="Ej. 2020"
                    class="panel-form-input" />
                @error('anio_desde')
                    <p class="mt-1 text-sm text-pa-peligro">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="anio_hasta" class="mb-1 block text-sm text-pa-tinta-cuerpo">Hasta (opcional)</label>
                <input type="number" name="anio_hasta" id="anio_hasta"
                    value="{{ old('anio_hasta', $categoria->anio_hasta) }}"
                    min="2000" max="2099" placeholder="Ej. 2021"
                    class="panel-form-input" />
                @error('anio_hasta')
                    <p class="mt-1 text-sm text-pa-peligro">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <p class="mt-1 text-sm text-pa-tinta-suave">
            No pueden jugar los nacidos antes del año desde. Los más chicos pueden subir de categoría.
            El año hasta es opcional: completalo solo en categorías de dos años, como 2020/2021.
            Si dejás los dos vacíos, se usa el año del nombre; en categorías sin año, como Sub 13, se valida por la edad del nombre.
        </p>
    </fieldset>

    <div>
        <label for="jugadores_maximos" class="mb-1 block text-sm font-medium text-pa-tinta-media">
            Jugadores máximos permitidos
        </label>
        <input type="number" name="jugadores_maximos" id="jugadores_maximos"
            value="{{ old('jugadores_maximos', $categoria->jugadores_maximos) }}"
            min="1" max="100"
            placeholder="Por defecto de la edición: {{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }}"
            class="panel-form-input" />
        <p class="mt-1 text-sm text-pa-tinta-suave">
            Dejar vacío para usar el límite de la edición ({{ $EdicionSeleccionada->jugadores_por_planilla ?? 18 }} jugadores).
        </p>
    </div>
</div>
