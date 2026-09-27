@csrf
@php
    $horarioPorDefecto = old(
        'horario',
        isset($horario) && $horario
            ? $horario
            : ($partido->horario
                ? \Carbon\Carbon::parse($partido->horario)->format('Y-m-d\TH:i')
                : \Carbon\Carbon::now()->next(\Carbon\Carbon::SUNDAY)->startOfDay()->format('Y-m-d\TH:i'))
    );
@endphp

<div class="space-y-5">
    @if (isset($CategoriaSeleccionada))
        <x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
            <option value="{{ $CategoriaSeleccionada->id }}" selected>{{ $CategoriaSeleccionada->nombreCategoria }}</option>
        </x-ui.select>
    @else
        <input class="hidden" type="number" name="idCategoria" id="idCategoria" value="{{ $idCategoria }}">
    @endif

    <div>
        <x-ui.select label="Equipo local" name="idEquipoLocal" id="idEquipoLocal">
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}"
                    {{ old('idEquipoLocal', $partido->idEquipoLocal) == $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
                </option>
            @endforeach
        </x-ui.select>
        @error('idEquipoLocal')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-ui.select label="Equipo visitante" name="idEquipoVisitante" id="idEquipoVisitante">
            @foreach ($equipos as $equipo)
                <option value="{{ $equipo->id }}"
                    {{ old('idEquipoVisitante', $partido->idEquipoVisitante) == $equipo->id ? 'selected' : '' }}>
                    {{ $equipo->nombre }}
                </option>
            @endforeach
        </x-ui.select>
        @error('idEquipoVisitante')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-ui.select label="Fase" name="idFase" id="idFase">
            @foreach ($fases as $f)
                <option value="{{ $f->id }}"
                    {{ old('idFase', $partido->idFase) == $f->id ? 'selected' : '' }}>
                    {{ $f->nombre }}
                </option>
            @endforeach
        </x-ui.select>
        @error('idFase')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-ui.select label="Copa" name="idCopa" id="idCopa">
            <option value="">Ninguna copa</option>
            @foreach ($copas as $copa)
                <option value="{{ $copa->id }}"
                    {{ old('idCopa', $partido->idCopa) == $copa->id ? 'selected' : '' }}>
                    {{ $copa->nombre }}
                </option>
            @endforeach
        </x-ui.select>
        @error('idCopa')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="horario" class="mb-1 block text-sm font-medium text-gray-700">
            Fecha y hora del partido
        </label>
        <input type="datetime-local" name="horario" id="horario" class="panel-form-input" value="{{ $horarioPorDefecto }}">
        @error('horario')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="cancha" class="mb-1 block text-sm font-medium text-gray-700">
            Cancha
        </label>
        <input type="text" name="cancha" id="cancha" value="{{ old('cancha', $partido->cancha) }}" class="panel-form-input">
        @error('cancha')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
