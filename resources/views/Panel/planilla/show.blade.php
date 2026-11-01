@extends('Panel.admin')

@section('detalle')
    @include('components.loader')

    @php
        $contextoNavegacion = $contextoNavegacion ?? [];
        $origen = $contextoNavegacion['origen'] ?? request('origen');

        $parametrosPartido = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'idCategoria' => $contextoNavegacion['idCategoria'] ?? request('idCategoria'),
            'idGrupo' => $contextoNavegacion['idGrupo'] ?? request('idGrupo'),
            'idFecha' => $contextoNavegacion['idFecha'] ?? request('idFecha'),
            'horario' => $contextoNavegacion['horario_filtro'] ?? request('horario_filtro'),
            'busqueda' => $contextoNavegacion['busqueda'] ?? request('busqueda'),
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $parametrosInstancia = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'idCategoria' => $contextoNavegacion['idCategoria'] ?? request('idCategoria'),
            'idFase' => $contextoNavegacion['idFase'] ?? request('idFase'),
            'idCopa' => $contextoNavegacion['idCopa'] ?? request('idCopa'),
            'estado' => $contextoNavegacion['estado'] ?? request('estado'),
            'busqueda' => $contextoNavegacion['busqueda'] ?? request('busqueda'),
            'fecha' => $contextoNavegacion['fecha'] ?? request('fecha'),
            'horario' => $contextoNavegacion['horario_filtro'] ?? request('horario_filtro'),
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $horarioAdminRegreso = $contextoNavegacion['horario_filtro'] ?? request('horario_filtro');

        if (($horarioAdminRegreso === null || $horarioAdminRegreso === '') && !empty($horarioSeleccionado) && $horarioSeleccionado !== '0') {
            $horarioAdminRegreso = $horarioSeleccionado;
        }

        $parametrosAdmin = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'horario' => $horarioAdminRegreso,
        ], static fn ($valor) => $valor !== null && $valor !== '');

        // Determinar la ruta de regreso según el origen
        $volverAInstancias = $tipoPartido === 'instanciaFinal' || $origen === 'instancia-final-index';
        $volverAPartidos = $origen === 'partido-index';

        if ($volverAInstancias) {
            $rutaVolver = route('instancia_final.index', $parametrosInstancia);
        } elseif ($volverAPartidos) {
            $rutaVolver = route('partido.index', array_merge([
                'idEdicion' => $EdicionSeleccionada->id,
            ], $parametrosPartido));
        } else {
            $rutaVolver = route('admin', $parametrosAdmin);
        }

        $rutaCargarResultado = route($tipoPartido === 'instanciaFinal' ? 'cargar-resultado-instancia' : 'cargar-resultado',
            array_merge([
                'idEdicion' => $EdicionSeleccionada->id,
                'idPartido' => $partido->id,
                'origen' => 'planilla',
                'origen_planilla' => $contextoNavegacion['origen'] ?? request('origen') ?? 'planilla',
                'horario_planilla' => \App\Constants\PartidoConstants::isInstanciaFinal($tipoPartido) ? 0 : $horarioSeleccionado,
                'horario_filtro_planilla' => $contextoNavegacion['horario_filtro'] ?? request('horario_filtro'),
            ], $volverAInstancias ? $parametrosInstancia : $parametrosPartido)
        );

        $golesConfirmados = $partido->goles_confirmados_at !== null;
        $resultadoCargado = $partido->golesEquipoLocal !== null && $partido->golesEquipoVisitante !== null;

        if ($golesConfirmados) {
            $estadoGolesClase = 'bg-emerald-100 text-emerald-700';
            $estadoGolesLabel = 'Goles confirmados';
            $estadoGolesDetalle = 'Confirmados el '.$partido->goles_confirmados_at->format('d/m/Y H:i');
        } elseif ($resultadoCargado) {
            $estadoGolesClase = 'bg-amber-100 text-amber-700';
            $estadoGolesLabel = 'Goles pendientes de carga';
            $estadoGolesDetalle = 'Resultado oficial cargado. Falta revisar y confirmar los goles individuales.';
        } else {
            $estadoGolesClase = 'bg-slate-100 text-slate-700';
            $estadoGolesLabel = 'Resultado pendiente de carga';
            $estadoGolesDetalle = 'Todavia no hay resultado oficial cargado para revisar goles.';
        }

        $golesArbitrosResumen = $golesArbitrosResumen ?? [
            'local' => ['total_reportado' => 0, 'total_inconsistencias' => 0, 'reportes' => []],
            'visitante' => ['total_reportado' => 0, 'total_inconsistencias' => 0, 'reportes' => []],
        ];

        $candidatosArbitrales = $candidatosArbitrales ?? [
            'local' => ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []],
            'visitante' => ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []],
        ];

        $equipoBloqueado = $equipoBloqueado ?? [
            'local' => ['bloqueado' => false, 'detalle' => ''],
            'visitante' => ['bloqueado' => false, 'detalle' => ''],
        ];

        $filasBaseArbitros = 4;

        $construirFilasArbitros = static function (array $reportes, int $cantidadMinima) {
            $filas = array_map(static function (array $reporte): array {
                return [
                    'numero_camiseta' => $reporte['numero_camiseta'],
                    'goles_reportados' => $reporte['goles_reportados'],
                ];
            }, $reportes);

            while (count($filas) < $cantidadMinima) {
                $filas[] = ['numero_camiseta' => null, 'goles_reportados' => null];
            }

            return $filas;
        };

        $filasArbitrosLocal = $construirFilasArbitros($golesArbitrosResumen['local']['reportes'] ?? [], $filasBaseArbitros);
        $filasArbitrosVisitante = $construirFilasArbitros($golesArbitrosResumen['visitante']['reportes'] ?? [], $filasBaseArbitros);
    @endphp

    <div class="w-full p-6 bg-white rounded-lg shadow-md">
        <!-- Botón de Volver -->
        <a href="{{ $rutaVolver }}"
            class="inline-block mb-4 text-blue-500 hover:text-blue-700">
            <svg class="w-6 h-6 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Volver
        </a>
        <h1 class="text-2xl font-bold mb-4">Planilla de Jugadores - Partido {{ $partido->id }}</h1>

        <div class="mb-4">
            <p class="text-gray-700"> {{ $partido->categoria->nombreCategoria }}</p>
            <p class="text-gray-700"><strong>Horario:</strong>
                {{ $partido->horario ? $partido->horario : \Carbon\Carbon::parse($partido->horario_datetime)->format('d-m-Y H:i') }}
            </p>
            <p class="text-gray-700"><strong>Cancha:</strong> {{ $partido->cancha }}</p>
        </div>

        <div class="mb-6 flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Estado de goles</p>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $estadoGolesClase }}">
                        {{ $estadoGolesLabel }}
                    </span>
                    <span class="text-sm text-gray-600">
                        {{ $estadoGolesDetalle }}
                    </span>
                </div>
            </div>

            <form action="{{ route($golesConfirmados ? 'planilla.quitarConfirmacionGoles' : 'planilla.confirmarGoles') }}" method="POST" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="hidden" name="partido_id" value="{{ $partido->id }}">
                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
                <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
                <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
                <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
                <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
                <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
                <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
                <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
                <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
                <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
                <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
                <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

                <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold text-white {{ $golesConfirmados ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700' }} transition duration-200">
                    {{ $golesConfirmados ? 'Quitar confirmacion' : 'Confirmar goles' }}
                </button>
            </form>
        </div>

        {{-- Compact debt bar: zero-height sticky wrapper, shown on desktop once the strip scrolls out of view. --}}
        <div class="sticky top-0 z-30 -mx-6 hidden h-0 lg:block" data-franja-resumen-ancla>
            <div data-franja-resumen aria-hidden="true"
                class="pointer-events-none flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-gray-200 bg-white px-6 py-2 text-sm text-brand-navy opacity-0 shadow-sm transition-opacity duration-150 motion-reduce:transition-none">
            </div>
        </div>
        <section class="-mx-6 mb-6 border-y border-gray-200 bg-white" aria-labelledby="cobro-planilla-titulo" data-franja-deudas>
            <h2 id="cobro-planilla-titulo" class="sr-only">Cobro de planilla y deudas</h2>
            <div data-cobros-seccion aria-live="polite"
                data-url="{{ route('planilla.deudas', ['idEdicion' => $partido->idEdicion, 'tipoPartido' => $tipoPartido, 'partidoId' => $partido->id]) }}">
                @include('Panel.cobros.franja')
            </div>
            @if (auth()->user()->isAdmin())
                @include('Panel.cobros.modal', ['configuracion' => $configuracionCobros])
            @endif
        </section>

        @if (is_null($partido->golesEquipoLocal) && is_null($partido->golesEquipoVisitante))
            <div class="mb-4">
                <a href="{{ $rutaCargarResultado }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md">
                    Cargar resultado
                </a>
            </div>
        @endif

        <div class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-4" data-planilla-arbitral>
            <div class="mb-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Planilla arbitral</h2>
                    <p class="text-sm text-gray-500">Dato oficial contra lo reportado por los árbitros.</p>
                    <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-700">Aplicación al guardar · Solo goles válidos.</span> Los goles arbitrales vinculados a jugadores presentes <span class="font-semibold text-gray-800">se aplican al guardar y actualizan sus estadísticas</span>, siempre que el equipo no tenga goles oficiales cargados. Los registros sin vinculación válida quedan como inconsistencias.</p>
                </div>
            </div>

            <div class="grid gap-3 xl:grid-cols-2">
                @foreach ([
                    ['clave' => 'local', 'titulo' => $partido->equipoLocal->nombre, 'equipo' => $partido->idEquipoLocal, 'resumen' => $golesArbitrosResumen['local'], 'filas' => $filasArbitrosLocal, 'oficial' => $jugadoresLocalPlanilla->sum('goles'), 'candidatos' => $candidatosArbitrales['local'] ?? ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []], 'bloqueo' => $equipoBloqueado['local'] ?? ['bloqueado' => false, 'detalle' => '']],
                    ['clave' => 'visitante', 'titulo' => $partido->equipoVisitante->nombre, 'equipo' => $partido->idEquipoVisitante, 'resumen' => $golesArbitrosResumen['visitante'], 'filas' => $filasArbitrosVisitante, 'oficial' => $jugadoresVisitantePlanilla->sum('goles'), 'candidatos' => $candidatosArbitrales['visitante'] ?? ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []], 'bloqueo' => $equipoBloqueado['visitante'] ?? ['bloqueado' => false, 'detalle' => '']],
                ] as $panelArbitral)
                    @php
                        $estadoPorReporte = [];
                        foreach ($panelArbitral['candidatos']['aplicables'] as $reporteAplicable) {
                            $estadoPorReporte[(int) $reporteAplicable['idReporte']] = ['label' => 'Aplicable', 'clase' => 'text-gray-700'];
                        }
                        foreach ($panelArbitral['candidatos']['ignorados'] as $reporteIgnorado) {
                            $estadoPorReporte[(int) $reporteIgnorado['idReporte']] = ['label' => 'Ignorado', 'clase' => 'text-gray-500'];
                        }
                        foreach ($panelArbitral['candidatos']['ambiguos'] as $reporteAmbiguo) {
                            $estadoPorReporte[(int) $reporteAmbiguo['idReporte']] = ['label' => 'Ambiguo', 'clase' => 'text-gray-700'];
                        }
                    @endphp
                    <details class="group rounded-md border border-gray-200 bg-white" {{ $panelArbitral['resumen']['total_inconsistencias'] > 0 ? 'open' : '' }}>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 p-3 marker:hidden">
                            <div class="grid min-w-0 gap-1 text-sm text-gray-700">
                                <h3 class="font-bold text-gray-900">{{ $panelArbitral['titulo'] }}</h3>
                                <span>Oficial {{ $panelArbitral['oficial'] }} · Árbitros {{ $panelArbitral['resumen']['total_reportado'] }} ·
                                    @if ($panelArbitral['resumen']['total_inconsistencias'] > 0)
                                        <span class="font-semibold text-red-700">{{ $panelArbitral['resumen']['total_inconsistencias'] }} {{ $panelArbitral['resumen']['total_inconsistencias'] === 1 ? 'inconsistencia' : 'inconsistencias' }}</span>
                                    @else
                                        Sin inconsistencias
                                    @endif
                                </span>
                            </div>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-brand-teal">
                                <span>Ver detalle arbitral</span>
                                <svg class="h-4 w-4 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </summary>

                        <div class="border-t border-gray-100 px-4 pb-4 pt-1">
                            <div class="mt-4 space-y-3">
                                @forelse ($panelArbitral['resumen']['reportes'] as $reporteArbitral)
                                    @php
                                        $estadoReporte = $estadoPorReporte[(int) ($reporteArbitral['id'] ?? 0)] ?? ['label' => 'Ignorado', 'clase' => 'text-gray-500'];
                                        if ($reporteArbitral['es_inconsistencia']) {
                                            $estadoReporte = ['label' => 'Inconsistencia', 'clase' => 'text-red-700'];
                                        }
                                    @endphp
                                    <div class="rounded-md border px-3 py-2 {{ $reporteArbitral['es_inconsistencia'] ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="text-sm font-semibold text-gray-900">
                                                Camiseta {{ $reporteArbitral['numero_camiseta'] ?? '-' }} · {{ $reporteArbitral['goles_reportados'] }} gol(es)
                                            </div>
                                            <span class="text-xs font-semibold {{ $estadoReporte['clase'] }}">
                                                {{ $estadoReporte['label'] }}
                                            </span>
                                        </div>

                                        @if ($reporteArbitral['jugador_oficial'])
                                            <p class="mt-1 text-sm text-gray-600">Jugador oficial vinculado: {{ $reporteArbitral['jugador_oficial'] }}</p>
                                        @endif

                                        @if ($reporteArbitral['es_inconsistencia'])
                                            <p class="mt-1 text-sm text-red-700">{{ $reporteArbitral['motivo_inconsistencia'] }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-md border border-dashed border-gray-200 px-3 py-4 text-sm text-gray-500">Todavia no hay goles cargados desde la planilla arbitral.</p>
                                @endforelse
                            </div>

                            <form action="{{ route('planilla.actualizarGolesArbitros') }}" method="POST" class="mt-4 space-y-3" x-data="{ filas: {{ \Illuminate\Support\Js::from($panelArbitral['filas']) }} }">
                                @csrf
                                <input type="hidden" name="partido_id" value="{{ $partido->id }}">
                                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
                                <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
                                <input type="hidden" name="idEquipo" value="{{ $panelArbitral['equipo'] }}">
                                <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
                                <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
                                <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
                                <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
                                <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
                                <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
                                <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
                                <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
                                <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
                                <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

                                <div class="grid gap-2">
                                    <div class="grid grid-cols-2 gap-2 px-2 text-xs font-semibold text-gray-700">
                                        <span>Camiseta reportada</span>
                                        <span>Goles reportados</span>
                                    </div>

                                    <template x-for="(filaArbitral, indice) in filas" :key="indice">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="number" :name="`reportes[${indice}][numero_camiseta]`" x-model="filaArbitral.numero_camiseta" min="0" max="999" class="h-10 rounded-md border-gray-300 text-sm" placeholder="Ej: 10">
                                            <input type="number" :name="`reportes[${indice}][goles_reportados]`" x-model="filaArbitral.goles_reportados" min="0" max="99" class="h-10 rounded-md border-gray-300 text-sm" placeholder="Ej: 2">
                                        </div>
                                    </template>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <button type="button" @click="filas.push({ numero_camiseta: '', goles_reportados: '' })" class="inline-flex min-h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                        Agregar línea
                                    </button>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <button type="submit" class="min-h-10 rounded-md bg-brand-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-navy-deep">
                                            Guardar y aplicar goles válidos
                                        </button>
                                    </div>
                                </div>
                            </form>

                            @if ($panelArbitral['bloqueo']['bloqueado'])
                                <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                    <p class="font-semibold">Equipo bloqueado para aplicar goles arbitrales.</p>
                                    <p class="mt-1">Ya hay goles oficiales cargados en la planilla: {{ $panelArbitral['bloqueo']['detalle'] }}.</p>
                                    <p class="mt-1">Mientras exista ese registro oficial, no se aplican goles arbitrales de este equipo.</p>
                                </div>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        </div>

        <div class="grid items-start gap-6 2xl:grid-cols-2 2xl:gap-8">
        <section class="min-w-0" aria-label="{{ $partido->equipoLocal->nombre }}">
        <!-- Equipo Local -->
        <h2 class="mb-2 flex items-center justify-center gap-3 text-xl font-semibold" data-equipo-titulo>
            <img src="{{ $partido->equipoLocal->foto ? asset('fotos/equipos/'.$partido->equipoLocal->foto) : asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="" width="44" height="44" loading="lazy"
                onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';"
                class="h-11 w-11 shrink-0 rounded-full border border-gray-200 bg-white object-contain p-1">
            <span>{{ $partido->equipoLocal->nombre }}</span>
        </h2>
        @php $localCount = $jugadoresLocalPlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $localCount > 18 ? 'text-red-600' : ($localCount == 18 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $localCount }} / 18
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button data-print-url="{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoLocal, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                onclick="printExternal(this.dataset.printUrl)"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Locales -->
        <div class="mb-3 grid gap-1.5" data-player-search-panel="local">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-lg font-semibold">Lista de Buena Fe</h3>
                <p class="text-sm tabular-nums text-gray-600" data-player-attendance-count="local"></p>
            </div>
            <label for="buscar-jugador-local" class="text-sm font-semibold text-gray-700">Buscar por DNI o nombre</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg>
                <input type="search" id="buscar-jugador-local" data-player-search-input="local" placeholder="DNI sin puntos o nombre" autocomplete="off"
                    class="block h-12 w-full rounded-lg border-gray-300 pl-10 text-lg font-semibold text-gray-900 placeholder:font-medium placeholder:text-gray-500 focus:border-brand-teal focus:ring-brand-teal">
            </div>
            <p class="min-h-[1.25rem] text-sm text-gray-600 data-[tono=error]:font-semibold data-[tono=error]:text-red-700 data-[tono=ok]:font-semibold data-[tono=ok]:text-brand-teal" data-player-search-eco="local" aria-live="polite">Enter marca la asistencia del jugador encontrado y pasa a su número.</p>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="local" data-player-autosave-endpoint="{{ route('planilla.actualizarJugadores') }}">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
            <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
            <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
            <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
            <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
            <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
            <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
            <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
            <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
            <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
            <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

            @if ($jugadoresLocalPlanilla->isEmpty())
                <p class="text-center">No hay jugadores en el equipo local.</p>
            @else
                @include('Panel.planilla.tabla-jugadores', ['jugadores' => $jugadoresLocalPlanilla, 'scope' => 'local'])
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($mostrarBotonCopiarLocal)
                    <button type="button" data-copy-previous-match="local" data-copy-previous-match-endpoint="{{ route('planilla.copiarPartidoAnterior') }}"
                        class="px-4 py-2 bg-brand-teal text-white rounded-md hover:bg-brand-teal/90 transition duration-200">
                        Copiar local desde partido anterior
                    </button>
                @endif
                <span data-copy-previous-match-feedback="local" class="text-sm font-semibold text-gray-600"></span>
            </div>
        </form>
        </section>
        <section class="min-w-0 border-t-2 border-gray-500 pt-4 2xl:border-t-0 2xl:pt-0" aria-label="{{ $partido->equipoVisitante->nombre }}">

        <!-- Equipo Visitante -->
        <h2 class="mb-2 flex items-center justify-center gap-3 text-xl font-semibold" data-equipo-titulo>
            <img src="{{ $partido->equipoVisitante->foto ? asset('fotos/equipos/'.$partido->equipoVisitante->foto) : asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="" width="44" height="44" loading="lazy"
                onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';"
                class="h-11 w-11 shrink-0 rounded-full border border-gray-200 bg-white object-contain p-1">
            <span>{{ $partido->equipoVisitante->nombre }}</span>
        </h2>
        @php $visitanteCount = $jugadoresVisitantePlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $visitanteCount > 18 ? 'text-red-600' : ($visitanteCount == 18 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $visitanteCount }} / 18
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button data-print-url="{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoVisitante, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                onclick="printExternal(this.dataset.printUrl)"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Visitantes -->
        <div class="mb-3 grid gap-1.5" data-player-search-panel="visitante">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-lg font-semibold">Lista de Buena Fe</h3>
                <p class="text-sm tabular-nums text-gray-600" data-player-attendance-count="visitante"></p>
            </div>
            <label for="buscar-jugador-visitante" class="text-sm font-semibold text-gray-700">Buscar por DNI o nombre</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg>
                <input type="search" id="buscar-jugador-visitante" data-player-search-input="visitante" placeholder="DNI sin puntos o nombre" autocomplete="off"
                    class="block h-12 w-full rounded-lg border-gray-300 pl-10 text-lg font-semibold text-gray-900 placeholder:font-medium placeholder:text-gray-500 focus:border-brand-teal focus:ring-brand-teal">
            </div>
            <p class="min-h-[1.25rem] text-sm text-gray-600 data-[tono=error]:font-semibold data-[tono=error]:text-red-700 data-[tono=ok]:font-semibold data-[tono=ok]:text-brand-teal" data-player-search-eco="visitante" aria-live="polite">Enter marca la asistencia del jugador encontrado y pasa a su número.</p>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="visitante" data-player-autosave-endpoint="{{ route('planilla.actualizarJugadores') }}">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
            <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
            <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
            <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
            <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
            <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
            <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
            <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
            <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
            <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
            <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

            @if ($jugadoresVisitantePlanilla->isEmpty())
                <p class="text-center">No hay jugadores en el equipo visitante.</p>
            @else
                @include('Panel.planilla.tabla-jugadores', ['jugadores' => $jugadoresVisitantePlanilla, 'scope' => 'visitante'])
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($mostrarBotonCopiarVisitante)
                    <button type="button" data-copy-previous-match="visitante" data-copy-previous-match-endpoint="{{ route('planilla.copiarPartidoAnterior') }}"
                        class="px-4 py-2 bg-brand-teal text-white rounded-md hover:bg-brand-teal/90 transition duration-200">
                        Copiar visitante desde partido anterior
                    </button>
                @endif
                <span data-copy-previous-match-feedback="visitante" class="text-sm font-semibold text-gray-600"></span>
            </div>
        </form>
        </section>
        </div>
    </div>
    <div data-autosave-alerta role="alert" aria-live="assertive" hidden
        class="fixed inset-x-0 bottom-0 z-40 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-red-200 bg-red-50 px-4 py-3 text-sm text-gray-800 shadow-[0_-6px_18px_-8px_rgba(8,47,59,0.25)] sm:px-6 [&[hidden]]:hidden">
        <svg class="h-5 w-5 shrink-0 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4.5M12 17.2v.1"/></svg>
        <p class="font-bold text-red-700" data-autosave-alerta-titulo></p>
        <ul class="flex min-w-0 flex-1 flex-wrap gap-x-3 gap-y-1" data-autosave-alerta-lista></ul>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-autosave-reintentar-todos class="min-h-10 rounded-md bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800 [&[hidden]]:hidden">Reintentar</button>
            <button type="button" data-autosave-recargar class="min-h-10 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 [&[hidden]]:hidden">Recargar la planilla</button>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const normalizeText = (value) => {
                if (!value) {
                    return '';
                }

                return value
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            };

            const refreshItemDataset = (item) => {
                const nameInput = item.querySelector('input[name$="[nombre]"]');
                const lastnameInput = item.querySelector('input[name$="[apellido]"]');
                const dniInput = item.querySelector('input[name$="[dni]"]');
                const numberInput = item.querySelector('input[name$="[numero_camiseta]"]');

                if (nameInput) item.dataset.playerName = nameInput.value ?? '';
                if (lastnameInput) item.dataset.playerLastname = lastnameInput.value ?? '';
                if (dniInput) item.dataset.playerDni = dniInput.value ?? '';
                if (numberInput) item.dataset.playerNumber = numberInput.value ?? '';
            };

            const syncGroupValues = (scope) => {
                const items = document.querySelectorAll(`[data-player-item="${scope}"]`);
                const groups = new Map();

                items.forEach((item) => {
                    const key = item.dataset.playerKey;
                    if (!key) {
                        return;
                    }

                    if (!groups.has(key)) {
                        groups.set(key, []);
                    }

                    groups.get(key).push(item);
                });

                groups.forEach((groupItems) => {
                    if (groupItems.length < 2) {
                        refreshItemDataset(groupItems[0]);
                        return;
                    }

                    const visible = groupItems.find((item) => item.offsetParent !== null) ?? groupItems[0];
                    refreshItemDataset(visible);

                    const sourceInputs = Array.from(visible.querySelectorAll('input, select, textarea'))
                        .filter((input) => input.name && !input.disabled);

                    groupItems.forEach((item) => {
                        if (item === visible) {
                            return;
                        }

                        sourceInputs.forEach((sourceInput) => {
                            const target = item.querySelector(`[name="${sourceInput.name}"]`);

                            if (!target) {
                                return;
                            }

                            if (['checkbox', 'radio'].includes(sourceInput.type)) {
                                target.checked = sourceInput.checked;
                            } else {
                                target.value = sourceInput.value;
                            }
                        });

                        refreshItemDataset(item);
                    });
                });
            };

            // Per-team search: digits search the DNI (dots ignored), anything else searches the name.
            const soloDigitos = (valor) => (valor ?? '').toString().replace(/\D/g, '');
            const getScopeItems = (scope) => Array.from(document.querySelectorAll(`[data-player-item="${scope}"]`));

            const coincideBusqueda = (item, consulta) => {
                const texto = (consulta ?? '').trim();

                if (!texto) {
                    return true;
                }

                refreshItemDataset(item);

                if (/^[\d.\s]+$/.test(texto)) {
                    return soloDigitos(item.dataset.playerDni).includes(soloDigitos(texto));
                }

                const nombre = normalizeText(`${item.dataset.playerName ?? ''} ${item.dataset.playerLastname ?? ''}`);

                return normalizeText(texto).split(/\s+/).every((parte) => nombre.includes(parte));
            };

            const nombreDeItem = (item) => [item?.dataset.playerName, item?.dataset.playerLastname].filter(Boolean).join(' ');

            const setSearchEco = (scope, texto, tono = '') => {
                const eco = document.querySelector(`[data-player-search-eco="${scope}"]`);

                if (!eco) {
                    return;
                }

                eco.textContent = texto;

                if (tono) {
                    eco.dataset.tono = tono;
                } else {
                    delete eco.dataset.tono;
                }
            };

            const buscarEnEquipo = (scope) => {
                const input = document.querySelector(`[data-player-search-input="${scope}"]`);
                const consulta = input?.value ?? '';
                const items = getScopeItems(scope);
                const hallados = items.filter((item) => coincideBusqueda(item, consulta));
                const activa = consulta.trim() !== '';

                items.forEach((item) => {
                    const coincide = hallados.includes(item);
                    item.toggleAttribute('data-search-oculto', activa && !coincide);
                    item.toggleAttribute('data-search-match', activa && coincide && hallados.length === 1);
                });

                return { activa, hallados };
            };

            const renderBusqueda = (scope) => {
                const { activa, hallados } = buscarEnEquipo(scope);

                if (!activa) {
                    setSearchEco(scope, 'Enter marca la asistencia del jugador encontrado y pasa a su número.');
                    return;
                }

                if (hallados.length === 0) {
                    setSearchEco(scope, 'No hay ningún jugador de este equipo con ese DNI o nombre.', 'error');
                    return;
                }

                if (hallados.length > 1) {
                    setSearchEco(scope, `${hallados.length} jugadores coinciden. Seguí escribiendo el DNI.`);
                    return;
                }

                const asistencia = hallados[0].querySelector('input[name$="[asistencia]"]');
                setSearchEco(scope, asistencia?.checked
                    ? `${nombreDeItem(hallados[0])} ya tiene asistencia. Enter va a su número.`
                    : `${nombreDeItem(hallados[0])}: Enter marca la asistencia.`, 'ok');
                hallados[0].scrollIntoView({ block: 'nearest' });
            };

            // Attendance counter and repeated shirt numbers (0 means no number yet).
            const actualizarResumenEquipo = (scope) => {
                const items = getScopeItems(scope);
                const contador = document.querySelector(`[data-player-attendance-count="${scope}"]`);
                const presentes = items.filter((item) => item.querySelector('input[name$="[asistencia]"]')?.checked).length;
                const cantidades = new Map();

                items.forEach((item) => {
                    const numero = (item.querySelector('input[name$="[numero_camiseta]"]')?.value ?? '').trim();

                    if (numero !== '' && Number(numero) !== 0) {
                        cantidades.set(String(Number(numero)), (cantidades.get(String(Number(numero))) ?? 0) + 1);
                    }
                });

                items.forEach((item) => {
                    const campoNumero = item.querySelector('input[name$="[numero_camiseta]"]');
                    const nota = item.querySelector('[data-player-repeated-note]');
                    const numero = String(Number((campoNumero?.value ?? '').trim()));
                    const repetido = (campoNumero?.value ?? '').trim() !== '' && (cantidades.get(numero) ?? 0) > 1;

                    campoNumero?.toggleAttribute('data-numero-repetido', repetido);

                    if (nota) {
                        nota.textContent = repetido ? ` · N° ${numero} repetido` : '';
                    }
                });

                if (contador) {
                    contador.innerHTML = `<b class="font-bold text-brand-teal">${presentes}</b> de ${items.length} con asistencia`;
                }
            };

            document.querySelectorAll('[data-player-search-input]').forEach((input) => {
                const scope = input.dataset.playerSearchInput;

                input.addEventListener('input', () => renderBusqueda(scope));
                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    event.preventDefault();
                    const { activa, hallados } = buscarEnEquipo(scope);

                    if (!activa || hallados.length !== 1) {
                        return;
                    }

                    const item = hallados[0];
                    const asistencia = item.querySelector('input[name$="[asistencia]"]');
                    const numero = item.querySelector('input[name$="[numero_camiseta]"]');
                    const yaEstaba = !!asistencia?.checked;

                    if (asistencia && !yaEstaba) {
                        asistencia.checked = true;
                        asistencia.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    input.value = '';
                    buscarEnEquipo(scope);
                    setSearchEco(scope, yaEstaba
                        ? `${nombreDeItem(item)} ya tenía asistencia. Escribí su número y Enter.`
                        : `Asistencia de ${nombreDeItem(item)}. Escribí su número y Enter.`, 'ok');
                    numero?.focus();
                    numero?.select();
                });

                actualizarResumenEquipo(scope);
            });

            const autosaveFieldPattern = /\[(numero_camiseta|goles|asistencia|tarjeta_roja)\]$/;
            const autosaveStateByRow = new Map();

            const getAutosaveRowItems = (scope, playerKey) => Array.from(
                document.querySelectorAll(`[data-player-item="${scope}"][data-player-key="${playerKey}"]`)
            );

            const getPrimaryAutosaveRowItem = (scope, playerKey) => {
                const rowItems = getAutosaveRowItems(scope, playerKey);

                return rowItems.find((item) => item.offsetParent !== null) ?? rowItems[0] ?? null;
            };

            const getAutosaveState = (scope, playerKey) => {
                const stateKey = `${scope}:${playerKey}`;

                if (!autosaveStateByRow.has(stateKey)) {
                    autosaveStateByRow.set(stateKey, {
                        inFlight: false,
                        queued: false,
                        paused: false,
                        timerId: null,
                        statusTimerId: null
                    });
                }

                return autosaveStateByRow.get(stateKey);
            };

            const getAutosaveMessageNodes = (playerKey) => document.querySelectorAll(`[data-player-save-message="${playerKey}"]`);
            const getAutosaveStatusNodes = (playerKey) => document.querySelectorAll(`[data-player-save-status="${playerKey}"]`);
            const getAutosaveRetryButtons = (playerKey) => document.querySelectorAll(`[data-player-retry-save="${playerKey}"]`);
            const getAutosaveReloadButtons = (playerKey) => document.querySelectorAll(`[data-player-reload-row="${playerKey}"]`);

            // Rows whose last save failed, so the operator is told even when the row is off screen.
            const problemasGuardado = new Map();
            const alertaGuardado = document.querySelector('[data-autosave-alerta]');

            const nombreJugador = (scope, playerKey) => {
                const item = getAutosaveRowItems(scope, playerKey)[0];
                const nombre = [item?.dataset.playerName, item?.dataset.playerLastname].filter(Boolean).join(' ');

                return nombre || `DNI ${playerKey}`;
            };

            const enfocarJugador = (scope, playerKey) => {
                const item = getPrimaryAutosaveRowItem(scope, playerKey);
                const campo = item?.querySelector('input[name$="[numero_camiseta]"]');

                item?.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                campo?.focus({ preventScroll: true });
            };

            const renderProblemasGuardado = () => {
                if (!alertaGuardado) {
                    return;
                }

                const problemas = [...problemasGuardado.values()];
                alertaGuardado.hidden = problemas.length === 0;

                if (problemas.length === 0) {
                    return;
                }

                alertaGuardado.querySelector('[data-autosave-alerta-titulo]').textContent = problemas.length === 1
                    ? 'No se guardó 1 jugador'
                    : `No se guardaron ${problemas.length} jugadores`;

                const lista = alertaGuardado.querySelector('[data-autosave-alerta-lista]');
                lista.replaceChildren(...problemas.map(({ scope, playerKey, message }) => {
                    const fila = document.createElement('li');
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'rounded px-1 py-0.5 text-left underline-offset-2 hover:underline';
                    boton.innerHTML = '<span class="font-semibold"></span> <span class="text-gray-600"></span>';
                    boton.children[0].textContent = nombreJugador(scope, playerKey);
                    boton.children[1].textContent = message;
                    boton.addEventListener('click', () => enfocarJugador(scope, playerKey));
                    fila.append(boton);

                    return fila;
                }));

                alertaGuardado.querySelector('[data-autosave-reintentar-todos]').hidden = !problemas.some(({ mode }) => mode === 'error');
                alertaGuardado.querySelector('[data-autosave-recargar]').hidden = !problemas.some(({ mode }) => mode === 'conflict');
            };

            const reintentarProblemas = () => {
                problemasGuardado.forEach(({ scope, playerKey, mode }) => {
                    const form = getPrimaryAutosaveRowItem(scope, playerKey)?.closest('form[data-player-scope][data-player-autosave-endpoint]');

                    if (mode !== 'error' || !form) {
                        return;
                    }

                    getAutosaveState(scope, playerKey).paused = false;
                    flushAutosaveRow(form, scope, playerKey);
                });
            };

            alertaGuardado?.querySelector('[data-autosave-reintentar-todos]').addEventListener('click', reintentarProblemas);
            alertaGuardado?.querySelector('[data-autosave-recargar]').addEventListener('click', () => window.location.reload());
            window.addEventListener('online', reintentarProblemas);

            window.addEventListener('beforeunload', (event) => {
                const pendiente = [...autosaveStateByRow.values()].some((state) => state.inFlight || state.timerId);

                if (pendiente || problemasGuardado.size > 0) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            const setAutosaveStatus = (scope, playerKey, mode, message) => {
                const statusNodes = getAutosaveStatusNodes(playerKey);
                const messageNodes = getAutosaveMessageNodes(playerKey);
                const retryButtons = getAutosaveRetryButtons(playerKey);
                const reloadButtons = getAutosaveReloadButtons(playerKey);
                const state = getAutosaveState(scope, playerKey);

                if (state.statusTimerId) {
                    clearTimeout(state.statusTimerId);
                    state.statusTimerId = null;
                }

                // Saving and saved are brief, fixed labels; problems keep the server's explanation.
                statusNodes.forEach((node) => {
                    node.hidden = false;
                    node.dataset.playerSaveState = mode;
                });

                messageNodes.forEach((node) => {
                    node.textContent = mode === 'saving' ? 'Guardando…' : mode === 'ok' ? 'Guardado' : message;
                });

                retryButtons.forEach((button) => {
                    button.hidden = mode !== 'error';
                });

                reloadButtons.forEach((button) => {
                    button.hidden = mode !== 'conflict';
                });

                if (mode !== 'saving') {
                    const tieneProblema = mode === 'error' || mode === 'conflict';
                    getAutosaveRowItems(scope, playerKey).forEach((item) => item.toggleAttribute('data-save-error', tieneProblema));

                    if (tieneProblema) {
                        problemasGuardado.set(`${scope}:${playerKey}`, { scope, playerKey, mode, message });
                    } else {
                        problemasGuardado.delete(`${scope}:${playerKey}`);
                    }

                    renderProblemasGuardado();
                }

                if (mode === 'ok') {
                    state.statusTimerId = window.setTimeout(() => {
                        getAutosaveStatusNodes(playerKey).forEach((node) => {
                            node.hidden = true;
                            node.removeAttribute('data-player-save-state');
                        });

                        getAutosaveMessageNodes(playerKey).forEach((node) => {
                            node.textContent = '';
                        });
                    }, 1600);
                }
            };

            const refreshAutosaveUpdatedAt = (scope, playerKey, updatedAt) => {
                getAutosaveRowItems(scope, playerKey).forEach((item) => {
                    item.dataset.playerUpdatedAt = updatedAt ?? '';
                });
            };

            const getCopyFeedbackNode = (scope) => document.querySelector(`[data-copy-previous-match-feedback="${scope}"]`);

            const setCopyFeedback = (scope, tone, message) => {
                const feedback = getCopyFeedbackNode(scope);

                if (!feedback) {
                    return;
                }

                feedback.textContent = message;
                feedback.classList.remove('text-emerald-700', 'text-red-700', 'text-gray-600');
                feedback.classList.add(tone === 'error' ? 'text-red-700' : tone === 'ok' ? 'text-emerald-700' : 'text-gray-600');
            };

            const refreshCopiedRow = (scope, filaActualizada) => {
                const rowItems = getAutosaveRowItems(scope, filaActualizada.dni);

                rowItems.forEach((rowItem) => {
                    const numeroInput = rowItem.querySelector('input[name$="[numero_camiseta]"]');
                    const asistenciaInput = rowItem.querySelector('input[name$="[asistencia]"]');

                    if (numeroInput) {
                        numeroInput.value = filaActualizada.numero_camiseta ?? '';
                    }

                    if (asistenciaInput) {
                        asistenciaInput.checked = !!filaActualizada.asistencia;
                    }

                    rowItem.dataset.playerUpdatedAt = filaActualizada.updated_at ?? '';
                    refreshItemDataset(rowItem);
                });

                syncGroupValues(scope);
                actualizarResumenEquipo(scope);
            };

            const buildCopyPayload = (form, scope) => {
                const payload = new FormData();
                const contextInputs = form.querySelectorAll('input[name]:not([name^="jugadores["])');

                contextInputs.forEach((input) => {
                    if (input.name === '_token') {
                        payload.append(input.name, input.value);
                        return;
                    }

                    if (input.type === 'checkbox') {
                        if (input.checked) {
                            payload.append(input.name, input.value || '1');
                        }

                        return;
                    }

                    payload.append(input.name, input.value ?? '');
                });

                const firstRow = form.querySelector(`[data-player-item="${scope}"][data-player-key]`);

                payload.append('scope', scope);
                payload.append('updated_at', firstRow?.dataset.playerUpdatedAt ?? '');

                return payload;
            };

            const handleCopyPreviousMatch = async (button) => {
                const scope = button.dataset.copyPreviousMatch;
                const endpoint = button.dataset.copyPreviousMatchEndpoint;
                const form = button.closest('form[data-player-scope]');

                if (!scope || !endpoint || !form) {
                    return;
                }

                setCopyFeedback(scope, 'neutral', 'Copiando...');
                button.disabled = true;

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: buildCopyPayload(form, scope)
                    });

                    const payload = await parseAutosaveMessage(response);

                    if (!response.ok || payload?.status !== 'ok') {
                        setCopyFeedback(scope, 'error', payload?.message ?? 'No se pudo copiar la planilla.');
                        return;
                    }

                    (payload?.data?.filas_actualizadas ?? []).forEach((filaActualizada) => {
                        refreshCopiedRow(scope, filaActualizada);
                    });

                    const resumen = payload?.data?.resumen ?? { copiados: 0, omitidos: 0, conflictos: 0 };
                    const mensaje = `Se copiaron ${resumen.copiados} fila(s). Omitidas: ${resumen.omitidos}. Conflictos: ${resumen.conflictos}.`;

                    setCopyFeedback(scope, 'ok', payload?.data?.confirmacion_invalidada ? `${mensaje} Se invalidó la confirmación de goles.` : mensaje);
                } catch (error) {
                    setCopyFeedback(scope, 'error', 'No se pudo conectar con el servidor.');
                } finally {
                    button.disabled = false;
                }
            };

            const buildAutosavePayload = (form, scope, playerKey, rowItem) => {
                const payload = new FormData();
                const contextInputs = form.querySelectorAll('input[name]:not([name^="jugadores["])');

                contextInputs.forEach((input) => {
                    if (input.name === '_token') {
                        payload.append(input.name, input.value);
                        return;
                    }

                    if (input.type === 'checkbox') {
                        if (input.checked) {
                            payload.append(input.name, input.value || '1');
                        }

                        return;
                    }

                    payload.append(input.name, input.value ?? '');
                });

                const numeroCamiseta = rowItem.querySelector('input[name$="[numero_camiseta]"]');
                const goles = rowItem.querySelector('input[name$="[goles]"]');
                const asistencia = rowItem.querySelector('input[name$="[asistencia]"]');
                const tarjetaRoja = rowItem.querySelector('input[name$="[tarjeta_roja]"]');
                const updatedAt = rowItem.dataset.playerUpdatedAt ?? '';

                payload.append(`jugadores[${playerKey}][numero_camiseta]`, numeroCamiseta ? (numeroCamiseta.value ?? '') : '');
                payload.append(`jugadores[${playerKey}][goles]`, goles ? (goles.value ?? '') : '');
                payload.append(`jugadores[${playerKey}][asistencia]`, asistencia && asistencia.checked ? '1' : '0');
                payload.append(`jugadores[${playerKey}][tarjeta_roja]`, tarjetaRoja && tarjetaRoja.checked ? '1' : '0');
                payload.append(`jugadores[${playerKey}][updated_at]`, updatedAt);

                return payload;
            };

            const parseAutosaveMessage = async (response) => {
                try {
                    return await response.json();
                } catch (error) {
                    return null;
                }
            };

            const extractAutosaveErrorMessage = (payload) => {
                if (payload?.message) {
                    return payload.message;
                }

                if (payload?.errors) {
                    const errores = Object.values(payload.errors).flat();

                    if (errores.length > 0) {
                        return errores[0];
                    }
                }

                return 'No se pudo guardar la fila.';
            };

            const flushAutosaveRow = async (form, scope, playerKey) => {
                const state = getAutosaveState(scope, playerKey);

                if (state.paused) {
                    return;
                }

                if (state.inFlight) {
                    state.queued = true;
                    return;
                }

                const rowItem = getPrimaryAutosaveRowItem(scope, playerKey);

                if (!rowItem) {
                    return;
                }

                state.inFlight = true;
                setAutosaveStatus(scope, playerKey, 'saving', 'Guardando...');

                try {
                    const response = await fetch(form.dataset.playerAutosaveEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: buildAutosavePayload(form, scope, playerKey, rowItem)
                    });

                    const payload = await parseAutosaveMessage(response);

                    if (!response.ok) {
                        if (response.status === 409 || payload?.status === 'conflict') {
                            state.paused = true;
                            setAutosaveStatus(scope, playerKey, 'conflict', payload?.message ?? 'Conflicto detectado. Recargá la planilla.');
                            return;
                        }

                        setAutosaveStatus(scope, playerKey, 'error', extractAutosaveErrorMessage(payload));
                        return;
                    }

                    if (payload?.status === 'conflict') {
                        state.paused = true;
                        setAutosaveStatus(scope, playerKey, 'conflict', payload?.message ?? 'Conflicto detectado. Recargá la planilla.');
                        return;
                    }

                    if (payload?.status === 'error') {
                        setAutosaveStatus(scope, playerKey, 'error', extractAutosaveErrorMessage(payload));
                        return;
                    }

                    const updatedAt = payload?.data?.updated_at ?? rowItem.dataset.playerUpdatedAt ?? '';
                    refreshAutosaveUpdatedAt(scope, playerKey, updatedAt);
                    syncGroupValues(scope);
                    setAutosaveStatus(scope, playerKey, 'ok', payload?.message ?? 'Guardado');
                } catch (error) {
                    setAutosaveStatus(scope, playerKey, 'error', 'No se pudo conectar con el servidor.');
                } finally {
                    state.inFlight = false;

                    if (state.queued) {
                        state.queued = false;
                        flushAutosaveRow(form, scope, playerKey);
                    }
                }
            };

            const scheduleAutosaveRow = (form, scope, playerKey) => {
                const state = getAutosaveState(scope, playerKey);

                if (state.paused) {
                    setAutosaveStatus(scope, playerKey, 'conflict', 'Conflicto pendiente. Recargá la planilla para seguir editando.');
                    return;
                }

                if (state.timerId) {
                    clearTimeout(state.timerId);
                }

                state.timerId = window.setTimeout(() => {
                    state.timerId = null;
                    flushAutosaveRow(form, scope, playerKey);
                }, 350);
            };

            const handleAutosaveTrigger = (event) => {
                const input = event.target;

                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                if (!autosaveFieldPattern.test(input.name ?? '')) {
                    return;
                }

                if (event.type === 'input' && input.type === 'checkbox') {
                    return;
                }

                if (event.type === 'change' && input.type !== 'checkbox' && input.type !== 'number') {
                    return;
                }

                const form = input.closest('form[data-player-scope][data-player-autosave-endpoint]');
                const rowItem = input.closest('[data-player-item][data-player-key]');

                if (!form || !rowItem) {
                    return;
                }

                const scope = form.dataset.playerScope;
                const playerKey = rowItem.dataset.playerKey;

                if (!scope || !playerKey) {
                    return;
                }

                syncGroupValues(scope);

                if (input.type === 'checkbox' || input.name.endsWith('[asistencia]') || input.name.endsWith('[tarjeta_roja]')) {
                    flushAutosaveRow(form, scope, playerKey);
                    return;
                }

                scheduleAutosaveRow(form, scope, playerKey);
            };

            const autosaveForms = document.querySelectorAll('form[data-player-scope][data-player-autosave-endpoint]');

            // Column-wise entry: Enter or arrows move to the same field of the next or previous player.
            const moverEntreJugadores = (event) => {
                const campo = event.target;
                const sufijo = campo instanceof HTMLInputElement ? (campo.name ?? '').match(autosaveFieldPattern)?.[0] : null;
                const avanza = event.key === 'Enter' ? !event.shiftKey : event.key === 'ArrowDown';

                if (!sufijo || !['Enter', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
                    return;
                }

                const item = campo.closest('[data-player-item][data-player-key]');
                const buscador = document.querySelector(`[data-player-search-input="${item?.dataset.playerItem}"]`);

                if (event.key === 'Enter' && !event.shiftKey && sufijo === '[numero_camiseta]' && buscador) {
                    event.preventDefault();
                    setSearchEco(item.dataset.playerItem, `${nombreDeItem(item)} lleva el N° ${campo.value}.`, 'ok');
                    buscador.focus();
                    return;
                }

                const items = Array.from(document.querySelectorAll(`[data-player-item="${item?.dataset.playerItem}"]`))
                    .filter((candidato) => candidato.offsetParent !== null);
                const destino = items[items.indexOf(item) + (avanza ? 1 : -1)]?.querySelector(`input[name$="${sufijo}"]`);

                event.preventDefault();

                if (destino) {
                    destino.focus();
                    destino.select?.();
                } else if (event.key === 'Enter') {
                    campo.blur();
                }
            };

            autosaveForms.forEach((form) => {
                form.addEventListener('input', handleAutosaveTrigger);
                form.addEventListener('change', handleAutosaveTrigger);
                form.addEventListener('keydown', moverEntreJugadores);
                form.addEventListener('input', () => actualizarResumenEquipo(form.dataset.playerScope));
                form.addEventListener('change', () => actualizarResumenEquipo(form.dataset.playerScope));
            });

            document.addEventListener('click', (event) => {
                const retryButton = event.target.closest('[data-player-retry-save]');
                const reloadButton = event.target.closest('[data-player-reload-row]');

                if (retryButton) {
                    const playerKey = retryButton.dataset.playerRetrySave;
                    const rowItem = retryButton.closest('[data-player-item][data-player-key]');

                    if (!playerKey || !rowItem) {
                        return;
                    }

                    const scope = rowItem.dataset.playerItem;
                    const form = rowItem.closest('form[data-player-scope][data-player-autosave-endpoint]');

                    if (!scope || !form) {
                        return;
                    }

                    const state = getAutosaveState(scope, playerKey);
                    state.paused = false;
                    flushAutosaveRow(form, scope, playerKey);
                }

                if (reloadButton) {
                    window.location.reload();
                }
            });

            const updateForms = document.querySelectorAll('form[data-player-scope]');

            const copyPreviousMatchButtons = document.querySelectorAll('[data-copy-previous-match]');

            copyPreviousMatchButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    handleCopyPreviousMatch(button);
                });
            });

            updateForms.forEach((form) => {
                form.addEventListener('submit', () => {
                    const scope = form.dataset.playerScope;

                    if (!scope) {
                        return;
                    }

                    const items = form.querySelectorAll(`[data-player-item="${scope}"]`);
                    items.forEach((item) => {
                        item.style.removeProperty('visibility');
                        item.style.removeProperty('position');
                        item.style.removeProperty('height');
                    });

                    syncGroupValues(scope);

                    items.forEach((item) => {
                        refreshItemDataset(item);
                    });
                });
            });
        });
    </script>
@endsection

@section('scripts')
<script>
    function printExternal(url) {
        var printFrame = document.getElementById('printFrame');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'printFrame';
            printFrame.style.display = 'none';
            document.body.appendChild(printFrame);
        }
        printFrame.src = url;
    }
</script>
<script>{!! file_get_contents(resource_path('js/cobros.js')) !!}</script>
<script>
    (() => {
        const franja = document.querySelector('[data-franja-deudas]');
        const resumen = document.querySelector('[data-franja-resumen]');
        if (!franja || !resumen) return;

        // Mirrors each team's name and balance from the strip, so polling keeps the bar current.
        const actualizar = () => {
            const filas = [...franja.querySelectorAll('[data-equipo-franja]')].map((equipo) => {
                const fila = document.createElement('p');
                fila.className = 'min-w-0';
                const nombre = document.createElement('span');
                nombre.className = 'font-semibold';
                nombre.textContent = equipo.querySelector('h3')?.firstChild?.textContent.trim() ?? '';
                const estado = document.createElement('span');
                estado.className = 'text-gray-700';
                estado.textContent = ` · ${equipo.querySelector('strong')?.textContent.trim() ?? ''}`;
                fila.append(nombre, estado);
                return fila;
            });
            resumen.replaceChildren(...filas);
        };
        actualizar();
        new MutationObserver(actualizar).observe(franja, { childList: true, subtree: true, characterData: true });

        new IntersectionObserver(([entrada]) => {
            const visible = !entrada.isIntersecting && entrada.boundingClientRect.top < 0;
            resumen.classList.toggle('opacity-0', !visible);
            resumen.dataset.visible = visible ? 'true' : 'false';
        }).observe(franja);
    })();
</script>
@endsection
