@extends('Panel.admin')

@section('contenido')
    @if (isset($tipo) && $tipo === 'instancia_final')
        <span class="bg-yellow-100 text-yellow-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Modo Instancia Final</span>
    @endif
    @if (isset($tipo) && $tipo != 'instancia_final')
        <div class="mb-5">
            <label for="nombre" class="mb-3 block text-base font-medium text-white">
                Grupo
            </label>
            <select name="idGrupo" id="idGrupo"
                class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md">

                <option value="{{ $partido->idGrupo }}" selected>{{ $partido->grupo->nombre }}</option>
                </option>
            </select>
        </div>
    @endif
    @if (!empty($partido))
        <div class="grid gap-6 p-1 md:p-6 bg-gray-50 w-full md:w-auto mb-5">
            <div class="bg-white rounded-lg shadow-lg overflow-x-auto md:overflow-x-visible">
                <div class="flex flex-col md:flex-row items-center justify-between md:px-6 py-4 border-b">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('fotos/equipos/' . $partido->equipoLocal->foto) }}" width="40" height="40" alt=""
                            class="rounded-full object-cover" />
                        <div class="font-medium text-lg">{{ $partido->equipoLocal->nombre }}</div>
                        <div class="text-gray-500">vs</div>
                        <img src="{{ asset('fotos/equipos/' . $partido->equipoVisitante->foto) }}" width="40" height="40" alt=""
                            class="rounded-full object-cover" />
                        <div class="font-medium text-lg">{{ $partido->equipoVisitante->nombre }}</div>
                    </div>
                    <div class="text-2xl font-bold mt-4 md:mt-0 text-center text-[--color-primary]">
                        {{ $partido->golesEquipoLocal ?? '-' }} - {{ $partido->golesEquipoVisitante ?? '-' }}
                    </div>
                    @if (isset($tipo) && $tipo === 'instancia_final')
                        <div class="text-2xl font-bold mt-4 md:mt-0 text-center text-[--color-primary]">
                            {{ $partido->penalesEquipoLocal }} - {{ $partido->penalesEquipoVisitante }}
                        </div>
                    @endif
                </div>
                <div class="flex flex-col md:flex-row items-center justify-between px-6 py-2 text-gray-600">
                    <div class="flex items-center gap-2 mb-2 md:mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12zm0-10a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H7a1 1 0 110-2h3V7a1 1 0 011-1z" />
                        </svg>
                        <span class="whitespace-nowrap">{{ $partido->horario }} PM</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12zm0-10a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H7a1 1 0 110-2h3V7a1-1z" />
                        </svg>
                        <span class="whitespace-nowrap">Cancha {{ $partido->cancha }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form
        action="{{ route(($tipo ?? '') === 'instancia_final' ? 'cargar-resultado-instancia' : 'cargar-resultado', ['idPartido' => $partido->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
        method="POST">
        @csrf
        <div class="mb-5">
            <label for="golesEquipoLocal" class="mb-3 block text-base font-medium text-white">
                Goles anotados de <span class="font-bold">{{ $partido->equipoLocal->nombre }}</span>
            </label>
            <input type="number" name="golesEquipoLocal" id="golesEquipoLocal" min="0"
                value="{{ old('golesEquipoLocal', $partido->golesEquipoLocal) }}"
                class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
        </div>
        <div class="mb-5">
            <label for="golesEquipoVisitante" class="mb-3 block text-base font-medium text-white">
                Goles anotados de <span class="font-bold">{{ $partido->equipoVisitante->nombre }}</span>
            </label>
            <input type="number" name="golesEquipoVisitante" id="golesEquipoVisitante" min="0"
                value="{{ old('golesEquipoVisitante', $partido->golesEquipoVisitante) }}"
                class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
        </div>
        @if (isset($tipo) && $tipo === 'instancia_final')
            <div id="containerPenales" style="display: none;">
                <div class="mb-5">
                    <label for="penalesEquipoLocal" class="mb-3 block text-base font-medium text-white">
                        Penales anotados de <span class="font-bold">{{ $partido->equipoLocal->nombre }}</span>
                    </label>
                    <input type="number" name="penalesEquipoLocal" id="penalesEquipoLocal"
                        value="{{ old('penalesEquipoLocal', $partido->penalesEquipoLocal) }}"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
                </div>
                <div class="mb-5">
                    <label for="penalesEquipoVisitante" class="mb-3 block text-base font-medium text-white">
                        Penales anotados de <span class="font-bold">{{ $partido->equipoVisitante->nombre }}</span>
                    </label>
                    <input type="number" name="penalesEquipoVisitante" id="penalesEquipoVisitante"
                        value="{{ old('penalesEquipoVisitante', $partido->penalesEquipoVisitante) }}"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
                </div>
                <div class="mb-5">
                    <label for="resultadoGlobal" class="mb-3 block text-base font-medium text-white">
                        Resultado Global
                    </label>
                    <input type="text" name="resultadoGlobal" id="resultadoGlobal"
                        value="{{ old('resultadoGlobal', $partido->resultadoGlobal) }}"
                        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    console.log('Script de penales cargado');
                    const golesLocalInput = document.getElementById('golesEquipoLocal');
                    const golesVisitanteInput = document.getElementById('golesEquipoVisitante');
                    const containerPenales = document.getElementById('containerPenales');

                    console.log('Elementos encontrados:', {
                        golesLocalInput: golesLocalInput,
                        golesVisitanteInput: golesVisitanteInput,
                        containerPenales: containerPenales
                    });

                    // Inputs de penales para limpiarlos si no es empate
                    const penalesLocalInput = document.getElementById('penalesEquipoLocal');
                    const penalesVisitanteInput = document.getElementById('penalesEquipoVisitante');
                    const resultadoGlobalInput = document.getElementById('resultadoGlobal');

                    function checkPenales() {
                        if (!containerPenales) return;

                        const golesLocal = parseInt(golesLocalInput.value);
                        const golesVisitante = parseInt(golesVisitanteInput.value);

                        console.log('Verificando penales:', {
                            golesLocal: golesLocal,
                            golesVisitante: golesVisitante,
                            sonIguales: golesLocal === golesVisitante
                        });

                        if (!isNaN(golesLocal) && !isNaN(golesVisitante) && golesLocal === golesVisitante) {
                            containerPenales.style.display = 'block';
                            console.log('Mostrando container de penales');
                        } else {
                            containerPenales.style.display = 'none';
                            console.log('Ocultando container de penales');
                            // Opcional: limpiar valores si se oculta
                            // penalesLocalInput.value = '';
                            // penalesVisitanteInput.value = '';
                            // resultadoGlobalInput.value = '';
                        }
                    }

                    if (golesLocalInput && golesVisitanteInput) {
                        golesLocalInput.addEventListener('input', checkPenales);
                        golesLocalInput.addEventListener('change', checkPenales);
                        golesVisitanteInput.addEventListener('input', checkPenales);
                        golesVisitanteInput.addEventListener('change', checkPenales);
                        // Ejecutar al cargar por si ya hay valores
                        checkPenales();
                        console.log('Event listeners agregados');
                    }
                });
            </script>
        @endif
        <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
        <div class="px-6 py-4 flex justify-center">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full">
                Cargar Resultado
            </button>
        </div>
    </form>
@endsection