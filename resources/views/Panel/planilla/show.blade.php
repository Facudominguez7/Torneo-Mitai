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

        @if (is_null($partido->golesEquipoLocal) && is_null($partido->golesEquipoVisitante))
            <div class="mb-4">
                <a href="{{ $rutaCargarResultado }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md">
                    Cargar resultado
                </a>
            </div>
        @endif

        <div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Planilla arbitral</p>
                    <h2 class="text-lg font-semibold text-gray-900">Dato oficial vs. dato reportado por arbitros</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Solo visual</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">No impacta estadísticas</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">La planilla oficial sigue siendo la fuente de verdad. Lo arbitral se guarda aparte, marca inconsistencias cuando una camiseta no existe o no puede vincularse y <span class="font-semibold text-gray-800">no modifica goles ni estadísticas oficiales de jugadores</span>.</p>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                @foreach ([
                    ['clave' => 'local', 'titulo' => $partido->equipoLocal->nombre, 'equipo' => $partido->idEquipoLocal, 'resumen' => $golesArbitrosResumen['local'], 'filas' => $filasArbitrosLocal, 'oficial' => $jugadoresLocalPlanilla->sum('goles'), 'candidatos' => $candidatosArbitrales['local'] ?? ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []], 'bloqueo' => $equipoBloqueado['local'] ?? ['bloqueado' => false, 'detalle' => '']],
                    ['clave' => 'visitante', 'titulo' => $partido->equipoVisitante->nombre, 'equipo' => $partido->idEquipoVisitante, 'resumen' => $golesArbitrosResumen['visitante'], 'filas' => $filasArbitrosVisitante, 'oficial' => $jugadoresVisitantePlanilla->sum('goles'), 'candidatos' => $candidatosArbitrales['visitante'] ?? ['aplicables' => [], 'ignorados' => [], 'ambiguos' => []], 'bloqueo' => $equipoBloqueado['visitante'] ?? ['bloqueado' => false, 'detalle' => '']],
                ] as $panelArbitral)
                    @php
                        $estadoPorReporte = [];
                        foreach ($panelArbitral['candidatos']['aplicables'] as $reporteAplicable) {
                            $estadoPorReporte[(int) $reporteAplicable['idReporte']] = ['label' => 'Aplicable', 'clase' => 'bg-emerald-100 text-emerald-700'];
                        }
                        foreach ($panelArbitral['candidatos']['ignorados'] as $reporteIgnorado) {
                            $estadoPorReporte[(int) $reporteIgnorado['idReporte']] = ['label' => 'Ignorado', 'clase' => 'bg-slate-100 text-slate-700'];
                        }
                        foreach ($panelArbitral['candidatos']['ambiguos'] as $reporteAmbiguo) {
                            $estadoPorReporte[(int) $reporteAmbiguo['idReporte']] = ['label' => 'Ambiguo', 'clase' => 'bg-amber-100 text-amber-700'];
                        }
                    @endphp
                    <details class="group rounded-lg border border-white bg-white shadow-sm" {{ $panelArbitral['resumen']['total_inconsistencias'] > 0 ? 'open' : '' }}>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-4 marker:hidden">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-gray-900">{{ $panelArbitral['titulo'] }}</h3>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Dato oficial: {{ $panelArbitral['oficial'] }} gol(es)</span>
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Reportado por arbitros: {{ $panelArbitral['resumen']['total_reportado'] }} gol(es)</span>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $panelArbitral['resumen']['total_inconsistencias'] > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">Inconsistencias: {{ $panelArbitral['resumen']['total_inconsistencias'] }}</span>
                            </div>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-amber-700">
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
                                        $estadoReporte = $estadoPorReporte[(int) ($reporteArbitral['id'] ?? 0)] ?? ['label' => 'Ignorado', 'clase' => 'bg-slate-100 text-slate-700'];
                                        if ($reporteArbitral['es_inconsistencia']) {
                                            $estadoReporte = ['label' => 'Inconsistencia', 'clase' => 'bg-red-100 text-red-700'];
                                        }
                                    @endphp
                                    <div class="rounded-md border px-3 py-2 {{ $reporteArbitral['es_inconsistencia'] ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="text-sm font-semibold text-gray-900">
                                                Camiseta {{ $reporteArbitral['numero_camiseta'] ?? '-' }} · {{ $reporteArbitral['goles_reportados'] }} gol(es)
                                            </div>
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $estadoReporte['clase'] }}">
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
                                    <div class="grid grid-cols-2 gap-2 px-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
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
                                    <button type="button" @click="filas.push({ numero_camiseta: '', goles_reportados: '' })" class="inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100">
                                        Agregar línea
                                    </button>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700">
                                            Guardar dato arbitral
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <form action="{{ route('planilla.aplicarGolesArbitrales') }}" method="POST" class="mt-3 flex justify-end">
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

                                <button type="submit" @disabled($panelArbitral['bloqueo']['bloqueado']) class="rounded-md border px-4 py-2 text-sm font-semibold transition {{ $panelArbitral['bloqueo']['bloqueado'] ? 'cursor-not-allowed border-red-200 bg-red-50 text-red-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                    Aplicar goles válidos
                                </button>
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

        <!-- Equipo Local -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoLocal->nombre }}</h2>
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
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between" data-player-search-panel="local">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <div class="w-full lg:w-[46rem] xl:w-[52rem]">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <div class="relative mt-1 flex items-center gap-2">
                    <input type="search" data-player-search-input="local" placeholder="Buscar por DNI"
                        class="block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3">
                    <div class="relative shrink-0">
                        <button type="button" data-player-filter-toggle="local"
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                            Filtros
                        </button>
                        <div data-player-filter-menu="local"
                            class="hidden absolute right-0 z-20 mt-2 w-56 rounded-md border border-gray-200 bg-white p-3 shadow-lg">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" data-player-filter-dni="local"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                DNI (predefinido)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span data-player-chip-dni="local"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                        <span>Filtro: DNI</span>
                        <button type="button" data-player-clear-dni="local" class="text-blue-700 hover:text-blue-900">x</button>
                    </span>
                    <span data-player-chip-field="local"
                        class="hidden inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span data-player-chip-field-text="local"></span>
                        <button type="button" data-player-clear-field="local" class="text-emerald-700 hover:text-emerald-900">x</button>
                    </span>
                    <button type="button" data-player-reset-search="local"
                        class="hidden rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                        Restablecer
                    </button>
                </div>
                <div data-player-search-suggestions="local"
                    class="hidden mt-2 rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Elegí por cuál buscar</p>
                    <div class="grid gap-1">
                        <button type="button" data-player-select-field="name" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="lastname" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="dni" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="number" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                    </div>
                </div>
            </div>
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
                <div class="sm:hidden space-y-4 mb-6">
                    @foreach ($jugadoresLocalPlanilla as $jugador)
                        <div class="p-3 border border-gray-200 rounded-lg shadow-sm bg-white"
                            data-player-item="local"
                            data-player-key="{{ $jugador->dni_jugador }}"
                            data-player-row="{{ $jugador->dni_jugador }}"
                            data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                            data-player-name="{{ $jugador->nombre }}"
                            data-player-lastname="{{ $jugador->apellido }}"
                            data-player-dni="{{ $jugador->dni_jugador }}"
                            data-player-number="{{ $jugador->numero_camiseta }}">
                            <div class="grid grid-cols-1 gap-3">
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]" value="{{ $jugador->nombre }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Apellido</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]" value="{{ $jugador->apellido }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de Nacimiento</label>
                                    <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                        value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">DNI</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][dni]" value="{{ $jugador->dni_jugador }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                    <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                        value="{{ $jugador->dni_jugador }}">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Número</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                        value="{{ $jugador->numero_camiseta }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goles</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                        value="{{ $jugador->goles }}" min="0"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Asistencia</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                        {{ $jugador->asistio ? 'checked' : '' }}
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between {{ $jugador->tarjeta_roja ? 'bg-red-50 p-2 rounded-md' : '' }}">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tarjeta Roja</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                        {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                        class="h-5 w-5 text-red-600 border-gray-300 rounded">
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600"
                                data-player-save-status="{{ $jugador->dni_jugador }}"
                                hidden>
                                <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                                <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                    class="hidden rounded-md bg-amber-600 px-3 py-1 text-white transition hover:bg-amber-700">
                                    Reintentar
                                </button>
                                <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                    class="hidden rounded-md bg-slate-700 px-3 py-1 text-white transition hover:bg-slate-800">
                                    Recargar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full min-w-[900px] bg-white border border-gray-200 mb-6">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="px-2 py-2 border-b text-left text-xs font-semibold">Nombre y Apellido</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">F. Nac.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">DNI</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">N°</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Goles</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Asist.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Roja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jugadoresLocalPlanilla as $jugador)
                                <tr data-player-item="local"
                                    data-player-key="{{ $jugador->dni_jugador }}"
                                    data-player-row="{{ $jugador->dni_jugador }}"
                                    data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                                    data-player-name="{{ $jugador->nombre }}"
                                    data-player-lastname="{{ $jugador->apellido }}"
                                    data-player-dni="{{ $jugador->dni_jugador }}"
                                    data-player-number="{{ $jugador->numero_camiseta }}">
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-left align-top md:align-middle text-[11px] md:text-sm break-words">
                                        <div class="flex w-full gap-1 md:gap-2 flex-col md:flex-row">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]"
                                                value="{{ $jugador->nombre }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]"
                                                value="{{ $jugador->apellido }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-600"
                                            data-player-save-status="{{ $jugador->dni_jugador }}"
                                            hidden>
                                            <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                                            <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                                class="hidden rounded-md bg-amber-600 px-3 py-1 text-white transition hover:bg-amber-700">
                                                Reintentar
                                            </button>
                                            <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                                class="hidden rounded-md bg-slate-700 px-3 py-1 text-white transition hover:bg-slate-800">
                                                Recargar
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}" readonly
                                            class="border-gray-300 bg-gray-100 rounded-md shadow-sm w-full min-w-0 h-9 text-xs md:text-sm cursor-not-allowed">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input name="jugadores[{{ $jugador->dni_jugador }}][dni]"
                                            value="{{ $jugador->dni_jugador }}" readonly
                                            class="w-full min-w-0 h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-xs md:text-sm cursor-not-allowed">
                                        <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                            value="{{ $jugador->dni_jugador }}">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                            value="{{ $jugador->numero_camiseta }}"
                                            class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                            value="{{ $jugador->goles }}" min="0" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                            {{ $jugador->asistio ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle {{ $jugador->tarjeta_roja ? 'bg-red-50' : '' }}">

                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                            {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                            class="h-4 w-4 text-red-600 border-gray-300 rounded">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($mostrarBotonCopiarLocal)
                    <button type="button" data-copy-previous-match="local" data-copy-previous-match-endpoint="{{ route('planilla.copiarPartidoAnterior') }}"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition duration-200">
                        Copiar local desde partido anterior
                    </button>
                @endif
                <span data-copy-previous-match-feedback="local" class="text-sm font-semibold text-gray-600"></span>
            </div>
        </form>
        <hr class="my-4 border-t-2 border-gray-500">

        <!-- Equipo Visitante -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoVisitante->nombre }}</h2>
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
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between" data-player-search-panel="visitante">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <div class="w-full lg:w-[46rem] xl:w-[52rem]">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <div class="relative mt-1 flex items-center gap-2">
                    <input type="search" data-player-search-input="visitante" placeholder="Buscar por DNI"
                        class="block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3">
                    <div class="relative shrink-0">
                        <button type="button" data-player-filter-toggle="visitante"
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                            Filtros
                        </button>
                        <div data-player-filter-menu="visitante"
                            class="hidden absolute right-0 z-20 mt-2 w-56 rounded-md border border-gray-200 bg-white p-3 shadow-lg">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" data-player-filter-dni="visitante"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                DNI (predefinido)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span data-player-chip-dni="visitante"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                        <span>Filtro: DNI</span>
                        <button type="button" data-player-clear-dni="visitante" class="text-blue-700 hover:text-blue-900">x</button>
                    </span>
                    <span data-player-chip-field="visitante"
                        class="hidden inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span data-player-chip-field-text="visitante"></span>
                        <button type="button" data-player-clear-field="visitante" class="text-emerald-700 hover:text-emerald-900">x</button>
                    </span>
                    <button type="button" data-player-reset-search="visitante"
                        class="hidden rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                        Restablecer
                    </button>
                </div>
                <div data-player-search-suggestions="visitante"
                    class="hidden mt-2 rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Elegí por cuál buscar</p>
                    <div class="grid gap-1">
                        <button type="button" data-player-select-field="name" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="lastname" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="dni" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="number" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                    </div>
                </div>
            </div>
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
                <div class="sm:hidden space-y-4 mb-6">
                    @foreach ($jugadoresVisitantePlanilla as $jugador)
                        <div class="p-3 border border-gray-200 rounded-lg shadow-sm bg-white"
                            data-player-item="visitante"
                            data-player-key="{{ $jugador->dni_jugador }}"
                            data-player-row="{{ $jugador->dni_jugador }}"
                            data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                            data-player-name="{{ $jugador->nombre }}"
                            data-player-lastname="{{ $jugador->apellido }}"
                            data-player-dni="{{ $jugador->dni_jugador }}"
                            data-player-number="{{ $jugador->numero_camiseta }}">
                            <div class="grid grid-cols-1 gap-3">
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]" value="{{ $jugador->nombre }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Apellido</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]" value="{{ $jugador->apellido }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de Nacimiento</label>
                                    <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                        value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">DNI</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][dni]" value="{{ $jugador->dni_jugador }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                    <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                        value="{{ $jugador->dni_jugador }}">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Número</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                        value="{{ $jugador->numero_camiseta }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goles</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                        value="{{ $jugador->goles }}" min="0"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Asistencia</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                        {{ $jugador->asistio ? 'checked' : '' }}
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between {{ $jugador->tarjeta_roja ? 'bg-red-50 p-2 rounded-md' : '' }}">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tarjeta Roja</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                        {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                        class="h-5 w-5 text-red-600 border-gray-300 rounded">
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600"
                                data-player-save-status="{{ $jugador->dni_jugador }}"
                                hidden>
                                <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                                <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                    class="hidden rounded-md bg-amber-600 px-3 py-1 text-white transition hover:bg-amber-700">
                                    Reintentar
                                </button>
                                <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                    class="hidden rounded-md bg-slate-700 px-3 py-1 text-white transition hover:bg-slate-800">
                                    Recargar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full min-w-[900px] bg-white border border-gray-200 mb-6">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="px-2 py-2 border-b text-left text-xs font-semibold">Nombre y Apellido</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">F. Nac.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">DNI</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">N°</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Goles</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Asist.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Roja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jugadoresVisitantePlanilla as $jugador)
                                <tr data-player-item="visitante"
                                    data-player-key="{{ $jugador->dni_jugador }}"
                                    data-player-row="{{ $jugador->dni_jugador }}"
                                    data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                                    data-player-name="{{ $jugador->nombre }}"
                                    data-player-lastname="{{ $jugador->apellido }}"
                                    data-player-dni="{{ $jugador->dni_jugador }}"
                                    data-player-number="{{ $jugador->numero_camiseta }}">
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-left align-top md:align-middle text-[11px] md:text-sm break-words">
                                        <div class="flex w-full gap-1 md:gap-2 flex-col md:flex-row">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]"
                                                value="{{ $jugador->nombre }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]"
                                                value="{{ $jugador->apellido }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-600"
                                            data-player-save-status="{{ $jugador->dni_jugador }}"
                                            hidden>
                                            <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                                            <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                                class="hidden rounded-md bg-amber-600 px-3 py-1 text-white transition hover:bg-amber-700">
                                                Reintentar
                                            </button>
                                            <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                                class="hidden rounded-md bg-slate-700 px-3 py-1 text-white transition hover:bg-slate-800">
                                                Recargar
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}" readonly
                                            class="border-gray-300 bg-gray-100 rounded-md shadow-sm w-full min-w-0 h-9 text-xs md:text-sm cursor-not-allowed">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input name="jugadores[{{ $jugador->dni_jugador }}][dni]"
                                            value="{{ $jugador->dni_jugador }}" readonly
                                            class="w-full min-w-0 h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-xs md:text-sm cursor-not-allowed">
                                        <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                            value="{{ $jugador->dni_jugador }}">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                            value="{{ $jugador->numero_camiseta }}"
                                            class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                            value="{{ $jugador->goles }}" min="0" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                            {{ $jugador->asistio ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle {{ $jugador->tarjeta_roja ? 'bg-red-50' : '' }}">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                            {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                            class="h-4 w-4 text-red-600 border-gray-300 rounded">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($mostrarBotonCopiarVisitante)
                    <button type="button" data-copy-previous-match="visitante" data-copy-previous-match-endpoint="{{ route('planilla.copiarPartidoAnterior') }}"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition duration-200">
                        Copiar visitante desde partido anterior
                    </button>
                @endif
                <span data-copy-previous-match-feedback="visitante" class="text-sm font-semibold text-gray-600"></span>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchPanels = document.querySelectorAll('[data-player-search-panel]');
            const searchStateByScope = new Map();
            const searchFieldLabels = {
                name: 'Nombre',
                lastname: 'Apellido',
                dni: 'DNI',
                number: 'Numero'
            };

            const isValidField = (field) => Object.prototype.hasOwnProperty.call(searchFieldLabels, field);

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

            const collectItemValues = (item) => {
                refreshItemDataset(item);

                return [
                    item.dataset.playerName,
                    item.dataset.playerLastname,
                    item.dataset.playerDni,
                    item.dataset.playerNumber
                ].filter(Boolean).join(' ');
            };

            const defaultState = () => ({
                query: '',
                dniPredefined: true,
                selectedField: null
            });

            const getScopeMode = (state) => {
                if (state.dniPredefined) {
                    return 'dni';
                }

                if (state.selectedField && isValidField(state.selectedField)) {
                    return state.selectedField;
                }

                return 'general';
            };

            const matchesTokens = (value, tokens) => tokens.every((token) => value.includes(token));

            const matchesByMode = (item, mode, tokens) => {
                if (!tokens.length) {
                    return true;
                }

                refreshItemDataset(item);

                const fieldValue = {
                    name: normalizeText(item.dataset.playerName ?? ''),
                    lastname: normalizeText(item.dataset.playerLastname ?? ''),
                    dni: normalizeText(item.dataset.playerDni ?? ''),
                    number: normalizeText(item.dataset.playerNumber ?? ''),
                    general: normalizeText(collectItemValues(item))
                };

                if (mode === 'name' || mode === 'lastname' || mode === 'dni' || mode === 'number') {
                    return matchesTokens(fieldValue[mode], tokens);
                }

                return matchesTokens(fieldValue.general, tokens);
            };

            const setItemVisibility = (item, isVisible) => {
                if (isVisible) {
                    item.style.removeProperty('visibility');
                    item.style.removeProperty('position');
                    item.style.removeProperty('height');
                    return;
                }

                item.style.visibility = 'collapse';
                item.style.position = 'absolute';
                item.style.height = '0';
            };

            const filterItems = (scope) => {
                const state = searchStateByScope.get(scope);
                if (!state) {
                    return;
                }

                const mode = getScopeMode(state);
                const tokens = normalizeText(state.query).split(/\s+/).filter(Boolean);
                const items = document.querySelectorAll(`[data-player-item="${scope}"]`);

                items.forEach((item) => {
                    setItemVisibility(item, matchesByMode(item, mode, tokens));
                });
            };

            const getPlaceholder = (state) => {
                if (state.dniPredefined) {
                    return 'Buscar por DNI';
                }

                if (state.selectedField && isValidField(state.selectedField)) {
                    return `Buscar por ${searchFieldLabels[state.selectedField].toLowerCase()}`;
                }

                return 'Buscar por nombre, apellido, DNI o numero';
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

            const renderScope = (scope) => {
                const panel = document.querySelector(`[data-player-search-panel="${scope}"]`);
                const state = searchStateByScope.get(scope);

                if (!panel || !state) {
                    return;
                }

                const input = panel.querySelector(`[data-player-search-input="${scope}"]`);
                const dniCheckbox = panel.querySelector(`[data-player-filter-dni="${scope}"]`);
                const dniChip = panel.querySelector(`[data-player-chip-dni="${scope}"]`);
                const fieldChip = panel.querySelector(`[data-player-chip-field="${scope}"]`);
                const fieldChipText = panel.querySelector(`[data-player-chip-field-text="${scope}"]`);
                const suggestions = panel.querySelector(`[data-player-search-suggestions="${scope}"]`);
                const suggestionButtons = panel.querySelectorAll(`[data-player-field-scope="${scope}"]`);
                const resetButton = panel.querySelector(`[data-player-reset-search="${scope}"]`);
                const showSuggestions = !state.dniPredefined && !state.selectedField && normalizeText(state.query).length > 0;
                const showReset = normalizeText(state.query).length > 0 || !state.dniPredefined || !!state.selectedField;

                if (input) {
                    if (input.value !== state.query) {
                        input.value = state.query;
                    }

                    input.placeholder = getPlaceholder(state);
                }

                if (dniCheckbox) {
                    dniCheckbox.checked = state.dniPredefined;
                }

                if (dniChip) {
                    dniChip.classList.toggle('hidden', !state.dniPredefined);
                }

                if (fieldChip && fieldChipText) {
                    const showFieldChip = !state.dniPredefined && !!state.selectedField && isValidField(state.selectedField);
                    fieldChip.classList.toggle('hidden', !showFieldChip);
                    if (showFieldChip) {
                        fieldChipText.textContent = `Campo: ${searchFieldLabels[state.selectedField]}`;
                    }
                }

                if (suggestions) {
                    suggestions.classList.toggle('hidden', !showSuggestions);
                }

                suggestionButtons.forEach((button) => {
                    const field = button.dataset.playerSelectField;
                    if (!isValidField(field)) {
                        return;
                    }

                    button.textContent = `Buscar ${searchFieldLabels[field]}: "${state.query}"`;
                });

                if (resetButton) {
                    resetButton.classList.toggle('hidden', !showReset);
                }

                filterItems(scope);
            };

            const updateScopeState = (scope, partialState) => {
                const currentState = searchStateByScope.get(scope);
                if (!currentState) {
                    return;
                }

                Object.assign(currentState, partialState);

                if (currentState.dniPredefined) {
                    currentState.selectedField = null;
                }

                if (currentState.selectedField && !isValidField(currentState.selectedField)) {
                    currentState.selectedField = null;
                }

                renderScope(scope);
            };

            const closeAllFilterMenus = () => {
                searchPanels.forEach((panel) => {
                    const menu = panel.querySelector('[data-player-filter-menu]');
                    if (menu) {
                        menu.classList.add('hidden');
                    }
                });
            };

            searchPanels.forEach((panel) => {
                const scope = panel.dataset.playerSearchPanel;
                if (!scope) {
                    return;
                }

                searchStateByScope.set(scope, defaultState());

                const input = panel.querySelector(`[data-player-search-input="${scope}"]`);
                const toggleButton = panel.querySelector(`[data-player-filter-toggle="${scope}"]`);
                const filterMenu = panel.querySelector(`[data-player-filter-menu="${scope}"]`);
                const dniCheckbox = panel.querySelector(`[data-player-filter-dni="${scope}"]`);
                const clearDniButton = panel.querySelector(`[data-player-clear-dni="${scope}"]`);
                const clearFieldButton = panel.querySelector(`[data-player-clear-field="${scope}"]`);
                const resetButton = panel.querySelector(`[data-player-reset-search="${scope}"]`);
                const suggestionButtons = panel.querySelectorAll(`[data-player-field-scope="${scope}"]`);

                renderScope(scope);

                if (toggleButton && filterMenu) {
                    toggleButton.addEventListener('click', (event) => {
                        event.stopPropagation();
                        const isClosed = filterMenu.classList.contains('hidden');
                        closeAllFilterMenus();
                        if (isClosed) {
                            filterMenu.classList.remove('hidden');
                        }
                    });

                    filterMenu.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });
                }

                if (input) {
                    input.addEventListener('input', () => {
                        updateScopeState(scope, { query: input.value });
                    });
                }

                if (dniCheckbox) {
                    dniCheckbox.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });

                    dniCheckbox.addEventListener('change', () => {
                        updateScopeState(scope, {
                            dniPredefined: dniCheckbox.checked,
                            selectedField: null
                        });
                    });
                }

                if (clearDniButton) {
                    clearDniButton.addEventListener('click', () => {
                        updateScopeState(scope, {
                            dniPredefined: false,
                            selectedField: null
                        });
                    });
                }

                if (clearFieldButton) {
                    clearFieldButton.addEventListener('click', () => {
                        updateScopeState(scope, { selectedField: null });
                    });
                }

                if (resetButton) {
                    resetButton.addEventListener('click', () => {
                        updateScopeState(scope, {
                            query: '',
                            dniPredefined: true,
                            selectedField: null
                        });
                    });
                }

                suggestionButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const field = button.dataset.playerSelectField;
                        if (!isValidField(field)) {
                            return;
                        }

                        updateScopeState(scope, {
                            dniPredefined: false,
                            selectedField: field
                        });
                    });
                });
            });

            document.addEventListener('click', () => {
                closeAllFilterMenus();
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

                statusNodes.forEach((node) => {
                    node.hidden = false;
                    node.dataset.playerSaveState = mode;
                });

                messageNodes.forEach((node) => {
                    node.textContent = message;
                });

                retryButtons.forEach((button) => {
                    button.hidden = mode !== 'error';
                });

                reloadButtons.forEach((button) => {
                    button.hidden = mode !== 'conflict';
                });

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

            autosaveForms.forEach((form) => {
                form.addEventListener('input', handleAutosaveTrigger);
                form.addEventListener('change', handleAutosaveTrigger);
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
@endsection
