@csrf
@if (isset($CategoriaSeleccionada))
    <div class="mb-5">
        <label for="categoría" class="mb-3 block text-base font-medium text-white">
            Categoría
        </label>
        <select id="idCategoria" name="idCategoria"
            class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">

            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}
            </option>
        </select>
    </div>
@else
    <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
@endif
<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo Local
    </label>
    <select name="idEquipoLocal" id="idEquipoLocal"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ old('idEquipo', $equipo->id) == $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="idEquipo" class="mb-3 block text-base font-medium text-white">
        Seleccionar Equipo Visitante
    </label>
    <select name="idEquipoVisitante" id="idEquipoVisitante"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($equipos as $equipo)
            <option value="{{ $equipo->id }}"
                {{ old('idEquipo', $equipo->id) == $equipo->id ? 'selected' : '' }}>
                {{ $equipo->nombre }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="idFase" class="mb-3 block text-base font-medium text-white">
        Seleccionar Fase
    </label>
    <select name="idFase" id="idFase"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
        @foreach ($fases as $f)
            <option value="{{ $f->id }}"
                {{ old('idFase', $f->id) == $f->id ? 'selected' : '' }}>
                {{ $f->nombre }}
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
        <option value="">Ninguna copa</option> <!-- Opción para ninguna copa -->
        @foreach ($copas as $copa)
            <option value="{{ $copa->id }}"
                {{ old('idCopa', $copa->id) == $copa->id ? 'selected' : '' }}>
                {{ $copa->nombre }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="horario" class="mb-3 block text-base font-medium text-white">
        Fecha y Hora del partido
    </label>
    <input type="datetime-local" name="horario" id="horario"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
        value="{{ old('horario', isset($horario) ? $horario : '') }}">
</div>
<div class="mb-5">
    <label for="cancha" class="mb-3 block text-base font-medium text-white">
        Cancha
    </label>
    <input type="text" name="cancha" id="cancha" value="{{old('cancha', $partido->cancha)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
         />
</div>
<input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">

