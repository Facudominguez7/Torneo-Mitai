@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    @php
        $parametrosRegreso = $parametrosRegreso ?? [
            'idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion'),
            'idCategoria' => $idCategoria ?? request('idCategoria'),
        ];
        $fechaPorDefecto = old('idFechas', $fechaPorDefecto);
        $horarioPorDefecto = old('horario_datetime', $horarioPorDefecto);
    @endphp

    <div class="mb-4 px-4 md:px-0">
        <a href="{{ route('partido.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <div class="flex items-center justify-center px-4 md:px-0">
        <div class="mx-auto w-full max-w-[600px]">
            <div class="mb-6">
                <div class="flex flex-wrap items-center gap-3 md:gap-4 bg-amber-500/20 border border-amber-500/40 rounded-lg p-3 md:p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span class="text-amber-400 font-semibold">Partido Interzonal</span>
                    <span class="text-gray-200 text-sm">Los equipos de diferentes grupos jugarán entre sí.</span>
                </div>
            </div>
            
            <form action="{{ route('partido.store-interzonal') }}" method="POST">
                @csrf
                <div class="mb-6">
                    <div class="flex flex-wrap items-center gap-3 md:gap-4 bg-white/10 border border-white/10 rounded-lg p-3 md:p-4">
                        @if (isset($CategoriaSeleccionada))
                            <div class="inline-flex items-center gap-2">
                                <span class="text-sm text-gray-100">Categoría:</span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-800 px-3 py-1 text-sm font-semibold shadow-sm">
                                    {{ $CategoriaSeleccionada->nombreCategoria }}
                                </span>
                                <input type="hidden" name="idCategoria" value="{{ $CategoriaSeleccionada->id }}">
                            </div>
                        @endif
                    </div>
                </div>
                
                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idGrupo" value="{{ $parametrosRegreso['idGrupo'] ?? '' }}">
                <input type="hidden" name="retorno_idFecha" value="{{ $parametrosRegreso['idFecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">
                
                <div class="mb-5">
                    <label for="idEquipoLocal" class="mb-2 md:mb-3 block text-base font-medium text-white">
                        Seleccionar Equipo Local
                    </label>
                    <select name="idEquipoLocal" id="idEquipoLocal"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
                        <option value="" disabled selected>-- Seleccionar equipo local --</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" data-grupo="{{ $equipo->grupo_id }}"
                                {{ (string) old('idEquipoLocal') === (string) $equipo->id ? 'selected' : '' }}>
                                {{ $equipo->nombre }} ({{ $equipo->grupo_nombre }})
                            </option>
                        @endforeach
                    </select>
                    @error('idEquipoLocal')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="mb-5">
                    <label for="idEquipoVisitante" class="mb-2 md:mb-3 block text-base font-medium text-white">
                        Seleccionar Equipo Visitante
                    </label>
                    <select name="idEquipoVisitante" id="idEquipoVisitante"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
                        <option value="" disabled selected>-- Seleccionar equipo visitante --</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" data-grupo="{{ $equipo->grupo_id }}"
                                {{ (string) old('idEquipoVisitante') === (string) $equipo->id ? 'selected' : '' }}>
                                {{ $equipo->nombre }} ({{ $equipo->grupo_nombre }})
                            </option>
                        @endforeach
                    </select>
                    @error('idEquipoVisitante')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="mb-5">
                    <label for="idFechas" class="mb-2 md:mb-3 block text-base font-medium text-white">
                        Seleccionar Fecha
                    </label>
                    <select name="idFechas" id="idFechas"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">
                        @foreach ($fechas as $f)
                            <option value="{{ $f->id }}"
                                {{ (string) $fechaPorDefecto === (string) $f->id ? 'selected' : '' }}>
                                {{ $f->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('idFechas')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="mb-5">
                    <label for="horario_datetime" class="mb-2 md:mb-3 block text-base font-medium text-white">
                        Seleccionar Horario
                    </label>
                    <input type="datetime-local" name="horario_datetime" id="horario_datetime"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
                        value="{{ $horarioPorDefecto }}">
                    @error('horario_datetime')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="mb-5">
                    <label for="cancha" class="mb-2 md:mb-3 block text-base font-medium text-white">
                        Cancha
                    </label>
                    <input type="text" name="cancha" id="cancha" value="{{ old('cancha') }}"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
                    @error('cancha')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <button type="submit"
                        class="hover:shadow-form rounded-md bg-amber-500 hover:bg-amber-600 py-3 px-8 text-base font-semibold text-white outline-none w-full md:w-auto">
                        Crear Partido Interzonal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const localSel = document.getElementById('idEquipoLocal');
        const visitanteSel = document.getElementById('idEquipoVisitante');

        function syncDisabledOptions() {
            const l = localSel.value;
            const v = visitanteSel.value;
            // Habilitar todas primero
            [...localSel.options].forEach(o => o.disabled = false);
            [...visitanteSel.options].forEach(o => o.disabled = false);
            // Deshabilitar selección cruzada igual
            if (l) {
                const optV = [...visitanteSel.options].find(o => o.value === l);
                if (optV) optV.disabled = true;
            }
            if (v) {
                const optL = [...localSel.options].find(o => o.value === v);
                if (optL) optL.disabled = true;
            }
        }

        function validarEquiposDistintos() {
            const l = localSel.value;
            const v = visitanteSel.value;
            syncDisabledOptions();
            if (l && v && l === v) {
                alert('El equipo local y el visitante deben ser distintos.');
                return false;
            }
            return true;
        }

        // Inicializar estado
        syncDisabledOptions();
        localSel.addEventListener('change', validarEquiposDistintos);
        visitanteSel.addEventListener('change', validarEquiposDistintos);

        // Evitar submit si son iguales
        const form = localSel.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!validarEquiposDistintos()) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
@endsection
