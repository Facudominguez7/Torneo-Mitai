@csrf
@if (isset($listaEquiposVallas) && $listaEquiposVallas->count() > 0)
    <div class="mb-5">
        <label class="mb-3 block text-base font-medium text-white">
            💡 Equipos ordenados por goles en contra (menor a mayor)
        </label>
        <select id="selectValla"
            class="w-full rounded-md border border-blue-400/50 bg-brand-navy py-3 px-6 text-base font-medium text-white outline-none focus:border-blue-400 focus:shadow-md"
            style="color-scheme: dark;" onchange="if(this.value) document.getElementById('idEquipo').value = this.value;">
            <option value="" style="background-color: var(--color-primary); color: white;">-- Seleccionar equipo --</option>
            @foreach ($listaEquiposVallas as $eq)
                <option value="{{ $eq->idEquipo }}" style="background-color: var(--color-primary); color: white;">
                    {{ $eq->nombreEquipo }} - {{ $eq->golesContra ?? 0 }} goles en contra
                </option>
            @endforeach
        </select>
    </div>
    {{-- Input hidden para el equipo, se autocompleta al seleccionar del dropdown --}}
    <input type="hidden" name="idEquipo" id="idEquipo" value="{{ old('idEquipo', $valla->idEquipo) }}">
@endif
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre del Arquero
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $valla->nombre)}}"
        class="panel-form-input" />
</div>
@if (!isset($listaEquiposVallas) || $listaEquiposVallas->count() == 0)
    <div class="mb-5">
        <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
            Seleccionar Equipo
        </label>
        <select name="idEquipo" id="idEquipo"
            class="panel-form-input">
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->idEquipo }}" {{ old('idEquipo', $valla->idEquipo) == $equipo->idEquipo ? 'selected' : '' }}>
                    {{ $equipo->nombreEquipo }}
                </option>
            @endforeach
        </select>
    </div>
@endif
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <select name="idEdicion" id="idEdicion"
        class="panel-form-input">

        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </select>
</div>
@if (isset($CategoriaSeleccionada))
    <div class="mb-5">
        <label for="nombre" class="mb-3 block text-base font-medium text-white">
            Categoria
        </label>
        <select name="idCategoria" id="idCategoria"
            class="panel-form-input">

            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </select>
    </div>
@else
    <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
@endif