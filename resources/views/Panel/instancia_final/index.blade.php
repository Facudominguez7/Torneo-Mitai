@extends('Panel.admin')

@section('contenido')
    @if (isset($EdicionSeleccionada))
        <div class="flex flex-col items-center gap-3 mb-4">
            {{-- Fila de botones principales --}}
            <div class="flex flex-row flex-wrap justify-center gap-2">
                <a href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'instancia_final']) }}">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded-lg transition-all duration-300 md:py-3 md:px-6">
                        + Agregar Partido
                    </button>
                </a>
                <button onclick="imprimirEnIframe('{{ route('admin.imprimir-fixture-instancias-finales', ['idEdicion' => $EdicionSeleccionada->id ?? '']) }}')"
                    class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-all duration-300 md:py-3 md:px-6 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir Fixture
                </button>
                <button onclick="imprimirEnIframe('{{ route('admin.imprimir-fixture-arbitros', ['idEdicion' => $EdicionSeleccionada->id ?? '']) }}')"
                    class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-all duration-300 md:py-3 md:px-6 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir Planilla Árbitros
                </button>
            </div>
            
            {{-- Filtro de horario --}}
            @php
                $horariosInstanciasFinales = \App\Models\InstanciaFinal::where('idEdicion', $EdicionSeleccionada->id ?? 0)
                    ->whereNotNull('horario')
                    ->pluck('horario')
                    ->map(function ($datetime) {
                        return \Carbon\Carbon::parse($datetime)->format('H:i');
                    })
                    ->unique()
                    ->sort()
                    ->values();
            @endphp
            
            @if($horariosInstanciasFinales->count() > 0)
                <div class="mt-2 text-center">
                    <div class="inline-flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg border border-gray-200">
                        <label for="filtro-horario" class="text-sm font-medium text-gray-700">Filtrar tabla por horario:</label>
                        <select id="filtro-horario"
                            class="bg-white border border-gray-300 text-gray-700 py-1 px-3 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                            <option value="">Todos</option>
                            @foreach ($horariosInstanciasFinales as $horario)
                                <option value="{{ $horario }}" {{ request('horario') == $horario ? 'selected' : '' }}>
                                    {{ $horario }} hs
                                </option>
                            @endforeach
                        </select>
                        @if(request('horario'))
                            <a href="{{ route('instancia_final.index', array_merge(request()->except('horario'), ['horario' => null])) }}" 
                               class="text-red-500 hover:text-red-700 ml-2" title="Quitar filtro">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
    <div class="bg-white p-6">
        <h1 class="text-2xl font-bold">Instancias Finales</h1>
        <div class="flex items-center justify-center flex-row mt-5 mb-0">
            @if (isset($nombreFase))
                <div class="mr-5">
                    <h1 class="text-4xl font-bold">{{ $nombreFase }}</h1>
                </div>
            @endif
            @if (isset($nombreCategoria))
                <div class="mr-5">
                    <h1 class="text-4xl font-bold">{{ $nombreCategoria }}</h1>
                </div>
            @endif
        </div>

        {{-- Estadísticas resumen --}}
        @php
            $totalPartidos = $partidos->count();
            $partidosJugados = $partidos->filter(fn($p) => $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null)->count();
            $partidosPendientes = $totalPartidos - $partidosJugados;
            $partidosAgrupados = $partidos->groupBy(fn($p) => $p->categoria->nombreCategoria ?? 'Sin categoría');
        @endphp

        <div class="bg-white p-6 border-t">
            <div class="flex flex-wrap gap-4 justify-center mb-4">
                <div class="bg-blue-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-blue-600">{{ $totalPartidos }}</span>
                    <p class="text-xs text-blue-600 font-medium">Total Partidos</p>
                </div>
                <div class="bg-green-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-green-600">{{ $partidosJugados }}</span>
                    <p class="text-xs text-green-600 font-medium">Jugados</p>
                </div>
                <div class="bg-yellow-100 rounded-lg px-6 py-3 text-center min-w-[120px]">
                    <span class="text-2xl font-bold text-yellow-600">{{ $partidosPendientes }}</span>
                    <p class="text-xs text-yellow-600 font-medium">Pendientes</p>
                </div>
            </div>


        </div>
    </div>
    <div class="bg-white p-6">
        @foreach ($partidos as $p)
            <div class="flex justify-center mb-5">
                <div class="grid gap-6 p-1 bg-gray-50 w-full md:w-2/3">
                    <div class="rounded-lg shadow-lg overflow-x-auto md:overflow-x-visible">
                        {{-- Header con categoría, fase y copa --}}
                        <div class="bg-gray-100 px-6 py-3 border-b">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    @if (!$idCategoria || isset($idFase))
                                        <span class="text-lg font-bold text-gray-800">{{ $p->categoria->nombreCategoria }}</span>
                                        <span class="text-gray-400">•</span>
                                    @endif
                                    <span class="text-lg font-semibold text-[--color-primary]">{{ $p->fase->nombre }}</span>
                                    @if (isset($p->copa->nombre))
                                        <span class="bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-1 rounded-full">🏆 {{ $p->copa->nombre }}</span>
                                    @endif
                                </div>
                                {{-- Badge de estado --}}
                                @if ($p->golesEquipoLocal === null && $p->golesEquipoVisitante === null)
                                    <span class="bg-yellow-100 text-yellow-700 text-xs font-semibold px-3 py-1 rounded-full">🕐 Pendiente</span>
                                @else
                                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">✅ Jugado</span>
                                @endif
                            </div>
                        </div>

                        {{-- Contenido del partido --}}
                        <div class="flex flex-col md:flex-row items-center justify-between md:px-6 py-4 border-b">
                            <div class="flex items-center gap-4">
                                <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" width="40" height="40"
                                    alt="{{ $p->equipoLocal->nombre }}" class="object-cover" />
                                <div class="font-medium text-lg">{{ $p->equipoLocal->nombre }}</div>
                                <div class="text-gray-500">vs</div>
                                <div class="font-medium text-lg">{{ $p->equipoVisitante->nombre }}</div>
                                <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="40" height="40"
                                    alt="{{ $p->equipoVisitante->nombre }}" class="object-cover" />
                            </div>
                            <div class="flex flex-col text-center mt-4 md:mt-0">
                                <div class="text-2xl font-bold text-[--color-primary]">
                                    {{ $p->golesEquipoLocal ?? '-' }} - {{ $p->golesEquipoVisitante ?? '-' }}
                                </div>
                                @if (
                                        $p->golesEquipoLocal !== null &&
                                        $p->golesEquipoVisitante !== null &&
                                        $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                        $p->penalesEquipoLocal !== null &&
                                        $p->penalesEquipoVisitante !== null
                                    )
                                    <span class="text-sm text-gray-600">(Pen: {{ $p->penalesEquipoLocal }} - {{ $p->penalesEquipoVisitante }})</span>
                                @endif
                                @if (!is_null($p->resultadoGlobal))
                                    <span class="text-sm font-semibold text-[--color-primary]">Global: {{ $p->resultadoGlobal }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Fecha, hora y cancha --}}
                        <div class="flex flex-col md:flex-row items-center justify-between px-6 py-2 text-muted-foreground">
                            <div class="flex items-center gap-2 mb-2 md:mb-0">
                                <span class="text-xl">{{ \Carbon\Carbon::parse($p->horario)->format('d-m-Y H:i') }}</span>
                            </div>
                            <div class="flex flex-col items-center gap-2">
                                <span>Cancha {{ $p->cancha }}</span>
                            </div>
                        </div>

                        {{-- Botones de acción --}}
                        <div class="flex flex-wrap justify-center gap-2 px-6 py-4 bg-gray-50">
                            @if ($p->golesEquipoLocal === null && $p->golesEquipoVisitante === null)
                                <a href="{{ route('cargar-resultado-instancia', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final', 'fecha' => request('fecha')]) }}">
                                    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                        Cargar Resultado
                                    </button>
                                </a>
                            @endif
                            <a href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0, 'fecha' => request('fecha')]) }}">
                                <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Planillas
                                </button>
                            </a>
                            <a href="{{ route('instancia_final.edit', ['instancia_final' => $p->id, 'fecha' => request('fecha')]) }}">
                                <button class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                    Editar
                                </button>
                            </a>
                            <form
                                action="{{ route('instancia_final.destroy', ['instancia_final' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'fecha' => request('fecha')]) }}"
                                method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este partido?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
            var horario = new URLSearchParams(window.location.search).get('horario') || '';

            var url = "{{ route('instancia_final.index') }}";

            // ===== PROCESAR NOTIFICACIONES PENDIENTES =====
            (async function procesarNotificacionesPendientes() {
                const pending = localStorage.getItem('pendingNotification');
                if (!pending) return;
                
                try {
                    const data = JSON.parse(pending);
                    // Verificar que no sea muy viejo (máximo 5 minutos)
                    if (Date.now() - data.timestamp > 5 * 60 * 1000) {
                        localStorage.removeItem('pendingNotification');
                        return;
                    }
                    
                    // Limpiar inmediatamente para evitar doble procesamiento
                    localStorage.removeItem('pendingNotification');
                    
                    const endpoint = data.es_instancia_final 
                        ? '/Panel/push/resultado-instancia/enviar-lote' 
                        : '/Panel/push/resultado/enviar-lote';
                    
                    console.log(`Enviando notificaciones para partido ${data.partido_id}...`);
                    
                    let offset = 0;
                    const LIMITE = 50;
                    let continuar = true;
                    let totalEnviados = 0;
                    
                    while (continuar) {
                        try {
                            const response = await fetch(endpoint, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    partido_id: data.partido_id,
                                    offset: offset,
                                    limit: LIMITE
                                })
                            });
                            
                            if (!response.ok) {
                                console.error('Error en lote:', offset);
                                continuar = false;
                                break;
                            }
                            
                            const result = await response.json();
                            totalEnviados += result.sent || 0;
                            
                            if (result.done) {
                                continuar = false;
                            } else {
                                offset += LIMITE;
                            }
                        } catch (err) {
                            console.error('Error enviando notificaciones:', err);
                            continuar = false;
                        }
                    }
                    
                    console.log(`Notificaciones completadas: ${totalEnviados} enviadas`);
                } catch (e) {
                    console.error('Error procesando notificación pendiente:', e);
                    localStorage.removeItem('pendingNotification');
                }
            })();
            // ===== FIN NOTIFICACIONES PENDIENTES =====
            
            // Actualizar la URL con los filtros seleccionados
            function updateUrl(params) {
                var newUrl = new URL(url, window.location.origin);
                // Aseguramos que idEdicion se mantenga
                if(idEdicion) newUrl.searchParams.set('idEdicion', idEdicion);
                
                Object.keys(params).forEach(function (key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.location.href = newUrl;
            }

            // Filtro por Horario (select)
            var selectHorario = document.getElementById('filtro-horario');
            if (selectHorario) {
                selectHorario.addEventListener('change', function () {
                    horario = this.value;
                    updateUrl({ horario: horario });
                });
            }
        });
    </script>
@endsection