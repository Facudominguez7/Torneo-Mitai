@extends('Panel.admin')

@section('contenido')
    @php
        $esInstanciaFinal = isset($tipo) && $tipo === 'instancia_final';
        $contextoRegreso = $parametrosRegreso ?? [];
        $rutaRegreso = $rutaRegreso ?? ($esInstanciaFinal ? route('instancia_final.index', $contextoRegreso) : route('partido.index', $contextoRegreso));
        $horarioMostrado = null;

        if (!empty($partido?->horario)) {
            $horarioMostrado = $partido->horario . ' PM';
        } elseif (!empty($partido?->horario_datetime)) {
            $horarioMostrado = \Carbon\Carbon::parse($partido->horario_datetime)->format('H:i');
        }
    @endphp

    <x-ui.page-header title="Cargar resultado" />

    <div class="mb-4">
        <a href="{{ $rutaRegreso }}" class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <div class="mx-auto w-full max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white p-6">
        <div class="mb-4 flex flex-wrap items-center justify-center gap-2">
            @if ($esInstanciaFinal)
                <x-ui.badge variant="warning">Modo instancia final</x-ui.badge>
            @elseif (!empty($partido) && $partido->grupo)
                <x-ui.badge variant="neutral">Grupo: {{ $partido->grupo->nombre }}</x-ui.badge>
            @endif
        </div>

        @if (!empty($partido))
            <div class="mb-6 flex flex-col items-center justify-center gap-4 sm:flex-row sm:justify-around">
                <div class="flex flex-col items-center gap-2">
                    <img src="{{ asset('fotos/equipos/' . $partido->equipoLocal->foto) }}" width="48" height="48" alt="Escudo {{ $partido->equipoLocal->nombre }}"
                        onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';"
                        class="h-12 w-12 rounded-full object-cover" />
                    <span class="text-center font-semibold text-gray-900">{{ $partido->equipoLocal->nombre }}</span>
                </div>
                <div class="text-sm font-semibold text-gray-400">vs</div>
                <div class="flex flex-col items-center gap-2">
                    <img src="{{ asset('fotos/equipos/' . $partido->equipoVisitante->foto) }}" width="48" height="48" alt="Escudo {{ $partido->equipoVisitante->nombre }}"
                        onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';"
                        class="h-12 w-12 rounded-full object-cover" />
                    <span class="text-center font-semibold text-gray-900">{{ $partido->equipoVisitante->nombre }}</span>
                </div>
            </div>

            <div class="mb-6 flex flex-wrap items-center justify-center gap-6 text-sm text-gray-500">
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12zm0-10a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H7a1 1 0 110-2h3V7a1 1 0 011-1z" />
                    </svg>
                    {{ $horarioMostrado ?? '--:--' }}
                </span>
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12zm0-10a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H7a1 1 0 110-2h3V7a1 1 0 011-1z" />
                    </svg>
                    Cancha {{ $partido->cancha }}
                </span>
                @if ($esInstanciaFinal)
                    <span class="text-lg font-bold text-gray-900">
                        Penales: {{ $partido->penalesEquipoLocal ?? '-' }} - {{ $partido->penalesEquipoVisitante ?? '-' }}
                    </span>
                @endif
            </div>
        @endif

        @if (isset($partidoIda) && $partidoIda)
            <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-5 py-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-500">Resultado IDA</p>
                <div class="flex items-center justify-between gap-4">
                    <span class="font-medium text-gray-800">{{ $partidoIda->equipoLocal->nombre }}</span>
                    <span class="text-2xl font-bold text-blue-700">
                        {{ $partidoIda->golesEquipoLocal }} - {{ $partidoIda->golesEquipoVisitante }}
                    </span>
                    <span class="font-medium text-gray-800">{{ $partidoIda->equipoVisitante->nombre }}</span>
                </div>
                @if ($partidoIda->penalesEquipoLocal !== null && $partidoIda->penalesEquipoVisitante !== null)
                    <p class="mt-1 text-center text-xs text-blue-400">
                        Penales: {{ $partidoIda->penalesEquipoLocal }} - {{ $partidoIda->penalesEquipoVisitante }}
                    </p>
                @endif
            </div>
        @endif

        <form
            action="{{ route(($tipo ?? '') === 'instancia_final' ? 'cargar-resultado-instancia' : 'cargar-resultado', ['idPartido' => $partido->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
            method="POST">
            @csrf
            <div class="mb-6 grid grid-cols-2 gap-4">
                <div>
                    <label for="golesEquipoLocal" class="mb-2 block text-center text-sm font-medium text-gray-700">
                        Goles de <span class="font-semibold text-gray-900">{{ $partido->equipoLocal->nombre }}</span>
                    </label>
                    <input type="number" name="golesEquipoLocal" id="golesEquipoLocal" min="0"
                        value="{{ old('golesEquipoLocal', $partido->golesEquipoLocal) }}"
                        class="w-full rounded-md border-gray-300 text-center text-2xl font-bold text-gray-900 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30" />
                </div>
                <div>
                    <label for="golesEquipoVisitante" class="mb-2 block text-center text-sm font-medium text-gray-700">
                        Goles de <span class="font-semibold text-gray-900">{{ $partido->equipoVisitante->nombre }}</span>
                    </label>
                    <input type="number" name="golesEquipoVisitante" id="golesEquipoVisitante" min="0"
                        value="{{ old('golesEquipoVisitante', $partido->golesEquipoVisitante) }}"
                        class="w-full rounded-md border-gray-300 text-center text-2xl font-bold text-gray-900 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30" />
                </div>
            </div>
            @if (isset($tipo) && $tipo === 'instancia_final')
                <div class="mb-6">
                    <label for="resultadoGlobal" class="mb-2 block text-sm font-medium text-gray-700">
                        Resultado global
                    </label>
                    <input type="text" name="resultadoGlobal" id="resultadoGlobal"
                        placeholder="Ej: 3-2"
                        value="{{ old('resultadoGlobal', $partido->resultadoGlobal) }}"
                        class="panel-form-input" />
                </div>
                <div id="containerPenales" class="mb-6 grid grid-cols-2 gap-4" style="display: none;">
                    <div>
                        <label for="penalesEquipoLocal" class="mb-2 block text-center text-sm font-medium text-gray-700">
                            Penales de <span class="font-semibold text-gray-900">{{ $partido->equipoLocal->nombre }}</span>
                        </label>
                        <input type="number" name="penalesEquipoLocal" id="penalesEquipoLocal"
                            value="{{ old('penalesEquipoLocal', $partido->penalesEquipoLocal) }}"
                            class="w-full rounded-md border-gray-300 text-center text-lg font-semibold text-gray-900 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30" />
                    </div>
                    <div>
                        <label for="penalesEquipoVisitante" class="mb-2 block text-center text-sm font-medium text-gray-700">
                            Penales de <span class="font-semibold text-gray-900">{{ $partido->equipoVisitante->nombre }}</span>
                        </label>
                        <input type="number" name="penalesEquipoVisitante" id="penalesEquipoVisitante"
                            value="{{ old('penalesEquipoVisitante', $partido->penalesEquipoVisitante) }}"
                            class="w-full rounded-md border-gray-300 text-center text-lg font-semibold text-gray-900 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30" />
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const golesLocalInput = document.getElementById('golesEquipoLocal');
                        const golesVisitanteInput = document.getElementById('golesEquipoVisitante');
                        const containerPenales = document.getElementById('containerPenales');

                        // Inputs de penales para limpiarlos si no es empate
                        const penalesLocalInput = document.getElementById('penalesEquipoLocal');
                        const penalesVisitanteInput = document.getElementById('penalesEquipoVisitante');
                        const resultadoGlobalInput = document.getElementById('resultadoGlobal');

                        function globalEsEmpate() {
                            if (!resultadoGlobalInput) return false;
                            const partes = resultadoGlobalInput.value.trim().split('-');
                            if (partes.length !== 2) return false;
                            const a = parseInt(partes[0]);
                            const b = parseInt(partes[1]);
                            return !isNaN(a) && !isNaN(b) && a === b;
                        }

                        function checkPenales() {
                            if (!containerPenales) return;

                            const golesLocal = parseInt(golesLocalInput.value);
                            const golesVisitante = parseInt(golesVisitanteInput.value);

                            const partidoEmpatado = !isNaN(golesLocal) && !isNaN(golesVisitante) && golesLocal === golesVisitante;

                            if (partidoEmpatado || globalEsEmpate()) {
                                containerPenales.style.display = 'block';
                            } else {
                                containerPenales.style.display = 'none';
                            }
                        }

                        if (golesLocalInput && golesVisitanteInput) {
                            golesLocalInput.addEventListener('input', checkPenales);
                            golesLocalInput.addEventListener('change', checkPenales);
                            golesVisitanteInput.addEventListener('input', checkPenales);
                            golesVisitanteInput.addEventListener('change', checkPenales);
                            if (resultadoGlobalInput) {
                                resultadoGlobalInput.addEventListener('input', checkPenales);
                                resultadoGlobalInput.addEventListener('change', checkPenales);
                            }
                            checkPenales();
                        }
                    });
                </script>
            @endif
            <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="origen" value="{{ request('origen') }}">
            <input type="hidden" name="origen_planilla" value="{{ request('origen_planilla') }}">
            <input type="hidden" name="retorno_origen_planilla" value="{{ request('retorno_origen_planilla', request('origen_planilla')) }}">
            <input type="hidden" name="horario_planilla" value="{{ request('horario_planilla') }}">
            <input type="hidden" name="retorno_horario_planilla" value="{{ request('retorno_horario_planilla', request('horario_planilla')) }}">
            <input type="hidden" name="horario_filtro_planilla" value="{{ request('horario_filtro_planilla') }}">
            <input type="hidden" name="retorno_horario_filtro_planilla" value="{{ request('retorno_horario_filtro_planilla', request('horario_filtro_planilla')) }}">
            <input type="hidden" name="retorno_idCategoria" value="{{ $contextoRegreso['idCategoria'] ?? request('idCategoria') }}">
            <input type="hidden" name="retorno_idGrupo" value="{{ $contextoRegreso['idGrupo'] ?? request('idGrupo') }}">
            <input type="hidden" name="retorno_idFecha" value="{{ $contextoRegreso['idFecha'] ?? request('idFecha') }}">
            <input type="hidden" name="retorno_idFase" value="{{ $contextoRegreso['idFase'] ?? request('idFase') }}">
            <input type="hidden" name="retorno_idCopa" value="{{ $contextoRegreso['idCopa'] ?? request('idCopa') }}">
            <input type="hidden" name="retorno_estado" value="{{ $contextoRegreso['estado'] ?? request('estado') }}">
            <input type="hidden" name="retorno_busqueda" value="{{ $contextoRegreso['busqueda'] ?? request('busqueda') }}">
            <input type="hidden" name="retorno_fecha" value="{{ $contextoRegreso['fecha'] ?? request('fecha') }}">
            <input type="hidden" name="retorno_horario" value="{{ $contextoRegreso['horario'] ?? request('horario') }}">
            <div class="flex justify-center">
                <x-ui.button type="submit" variant="primary">
                    Cargar resultado
                </x-ui.button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[action*="cargar-resultado"]');

            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerText;
                    submitBtn.disabled = true;
                    submitBtn.innerText = 'Guardando...';

                    try {
                        // 1. Guardar resultado
                        const formData = new FormData(form);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        if (!response.ok) throw new Error('Error al guardar el resultado.');

                        const result = await response.json();

                        // 2. Mostrar éxito breve y redirigir
                        Swal.fire({
                            title: '¡Listo!',
                            text: 'Resultado guardado.',
                            icon: 'success',
                            timer: 1000,
                            showConfirmButton: false
                        });

                        // 3. Redirigir inmediatamente
                        setTimeout(() => {
                            window.location.href = @json($rutaRegreso);
                        }, 500);

                    } catch (error) {
                        console.error(error);
                        Swal.fire('Error', 'Hubo un problema: ' + error.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerText = originalBtnText;
                    }
                });
            }
        });
    </script>
@endsection
