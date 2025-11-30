@csrf
<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo
    </label>
    <select name="idEquipo" id="idEquipo"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->idEquipo }}"
                {{ old('idEquipo', $campeon->idEquipo) == $equipo->idEquipo ? 'selected' : '' }}>
                {{ $equipo->nombreEquipo }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="idCopa" class="mb-3 block text-base font-medium text-white">
        Seleccionar Copa
    </label>
    <select name="idCopa" id="idCopa"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($copas as $copa)
            <option value="{{ $copa->id }}" {{ old('idCopa', $campeon->idCopa) == $copa->id ? 'selected' : '' }}>
                {{ $copa->nombre }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <select name="idEdicion" id="idEdicion"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">

        <option value="{{ $EdicionSeleccionada->id }}" selected>{{ $EdicionSeleccionada->nombre }}</option>
    </select>
</div>
@if (isset($CategoriaSeleccionada))
    <div class="mb-5">
        <label for="nombre" class="mb-3 block text-base font-medium text-white">
            Categoria
        </label>
        <select name="idCategoria" id="idCategoria"
            class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">

            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </select>
    </div>
@else
    <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
@endif
