<!DOCTYPE html>
<html lang="es">
@include('head')
@include('components.loader')

<body x-data="{ sidebarAbierto: false }" :class="{ 'overflow-hidden': sidebarAbierto }"
    @keydown.escape.window="sidebarAbierto = false"
    @resize.window="if (window.matchMedia('(min-width: 1024px)').matches) sidebarAbierto = false"
    class="bg-gray-100 text-gray-900 font-sans">
    @php
        /**
         * Contexto de edición activo para mantener la navegación consistente.
         */
        $parametrosEdicionActual = $idEdicionActual ? ['idEdicion' => $idEdicionActual] : [];
    @endphp
    <div id="sidebar-backdrop" x-show="sidebarAbierto" x-cloak x-transition.opacity
        @click="sidebarAbierto = false" aria-hidden="true"
        class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>
    <aside id="sidebar-multi-level-sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-80 -translate-x-full flex-col overflow-y-auto bg-white shadow-xl transition-transform duration-200 lg:w-64 lg:shadow-none lg:translate-x-0"
        :class="{ 'translate-x-0': sidebarAbierto, '-translate-x-full': !sidebarAbierto }"
        aria-label="Navegación del panel de administración">
        @include('Panel.partials.sidebar')
    </aside>
    @php
        /**
         * Header persistente: muestra la edición activa o un estado vacío accesible.
         * El selector navega a `admin?idEdicion` exclusivamente para reiniciar filtros.
         */
        $edicionActivaHeader = $EdicionSeleccionada ?? null;
    @endphp
    <header data-panel-edicion-header
        class="z-30 flex w-full flex-col justify-center gap-2 border-b border-slate-200 bg-white/90 px-3 py-2 text-slate-700 shadow-sm backdrop-blur-sm sm:px-4 lg:ml-64 lg:h-16 lg:w-auto lg:flex-row lg:items-center lg:gap-5 lg:px-6 lg:py-0">
        <div data-panel-header-primary-row class="flex w-full min-w-0 items-center justify-between gap-2 lg:w-auto lg:flex-1">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <button data-drawer-target="sidebar-multi-level-sidebar" data-drawer-toggle="sidebar-multi-level-sidebar"
                    aria-controls="sidebar-multi-level-sidebar" type="button" aria-label="Abrir menú"
                    :aria-expanded="sidebarAbierto.toString()" @click="sidebarAbierto = true"
                    class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg text-sm text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300 lg:hidden">
                    <svg class="h-6 w-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd"
                            d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z">
                        </path>
                    </svg>
                </button>
                <span class="whitespace-nowrap text-sm font-bold tracking-wide text-slate-800 sm:text-base">Mitaí Admin</span>
                {{-- Buscador de jugador por DNI: sin JS hace el mismo POST que Consulta de jugador; con JS muestra el resultado en un modal. --}}
                <form data-buscador-jugador data-url-rapida="{{ route('jugador.consulta-rapida') }}" action="{{ route('jugador.buscar') }}" method="POST" role="search"
                    class="ml-1 flex min-w-0 flex-1 items-center gap-1 sm:ml-3 lg:max-w-xs">
                    @csrf
                    <input type="hidden" name="idEdicion" value="{{ $edicionActivaHeader?->id }}">
                    <label for="buscador-jugador-dni" class="sr-only">Buscar jugador por DNI</label>
                    <input id="buscador-jugador-dni" name="dni" type="text" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required
                        placeholder="DNI del jugador" title="Ingresá el DNI solo con números"
                        class="h-10 w-full min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-navy/30">
                    <button type="submit" aria-label="Buscar jugador"
                        class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        <svg class="h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <form data-panel-header-edition-row data-panel-edicion-form action="{{ route('admin') }}" method="GET"
            class="flex w-full min-w-0 items-center gap-2 lg:w-auto lg:flex-shrink-0">
            <label for="panel-edicion-select" class="whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-slate-500">Edición</label>
            <select name="idEdicion" id="panel-edicion-select"
                class="h-10 min-w-0 max-w-full flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-brand-navy shadow-sm outline-none focus:border-brand-navy focus:ring-2 focus:ring-brand-navy/40 lg:w-72 lg:flex-none">
                @if (! $edicionActivaHeader)
                    <option value="" selected>Sin edición seleccionada</option>
                @endif
                @foreach ($ediciones as $edicionHeader)
                    <option value="{{ $edicionHeader->id }}" {{ $edicionActivaHeader?->id === $edicionHeader->id ? 'selected' : '' }}>
                        {{ $edicionHeader->nombre }}
                    </option>
                @endforeach
            </select>
            <button type="submit"
                class="inline-flex h-10 flex-shrink-0 items-center rounded-lg border border-slate-200 bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300 sm:text-sm">
                Cambiar
            </button>
        </form>
    </header>
    <style>#buscador-jugador-modal::backdrop { background: rgb(0 0 0 / .5); }</style>
    <dialog id="buscador-jugador-modal" aria-labelledby="buscador-jugador-titulo" style="width:calc(100% - 2rem);max-height:90vh"
        class="m-auto max-w-5xl overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-0 text-gray-900">
        <div class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-gray-200 bg-white px-5 py-3">
            <h2 id="buscador-jugador-titulo" tabindex="-1" class="min-w-0 break-words text-lg font-bold text-gray-800 focus:outline-none">Consulta de jugador</h2>
            <button type="button" data-buscador-jugador-cerrar
                class="min-h-11 flex-shrink-0 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">Cerrar</button>
        </div>
        <div id="buscador-jugador-contenido" aria-live="polite" class="px-5 pb-5 pt-4"></div>
    </dialog>
    <div class="relative flex min-h-screen w-full flex-col overflow-x-hidden pb-6 pt-4 sm:pt-6 lg:pl-64">
        @php
            $idEdicionContexto = $idEdicionActual;

        @endphp
        @if (Route::is('admin'))
            @if (! $resumenDashboard)
                <div class="mx-3 my-6 rounded-xl border border-gray-200 bg-white p-8 text-center sm:mx-4">
                    <h1 class="text-lg font-bold text-gray-900">Seleccioná una edición para ver el resumen del panel.</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Elegí una edición en el selector de arriba para ver los KPIs, los partidos que requieren atención y la próxima fecha.
                    </p>
                </div>
            @else
                @php
                    $nombreUsuarioActivoDashboard = auth()->user()?->name ?? 'Administrador';
                    $horaActualDashboard = now()->hour;
                    $saludoDashboard = match (true) {
                        $horaActualDashboard >= 5 && $horaActualDashboard < 12 => 'Buen día',
                        $horaActualDashboard >= 12 && $horaActualDashboard < 20 => 'Buenas tardes',
                        default => 'Buenas noches',
                    };
                    $partidosJugadosResumen = $resumenDashboard['partidosJugados'] ?? 0;
                    $partidosTotalesResumen = $resumenDashboard['partidosTotales'] ?? 0;
                    $jugadoresPorCategoriaResumen = $resumenDashboard['jugadoresPorCategoria'] ?? [];
                    $maxJugadoresCategoriaResumen = collect($jugadoresPorCategoriaResumen)->max('total_jugadores') ?: 1;
                @endphp

                {{-- Encabezado: saludo + edición activa + acciones secundarias --}}
                <div class="mb-4 flex flex-col gap-3 px-3 sm:px-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ $saludoDashboard }}, {{ $nombreUsuarioActivoDashboard }}</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Edición activa: <span class="font-semibold text-brand-navy">{{ $EdicionSeleccionada?->nombre }}</span>
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:flex-wrap lg:justify-end">
                        <a href="{{ route('notificaciones.index', ['idEdicion' => $idEdicionContexto]) }}"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 sm:text-sm">
                            <svg class="h-4 w-4 text-brand-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Notificaciones
                        </a>
                        <a href="{{ route('partido.index', ['idEdicion' => $idEdicionContexto]) }}"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 sm:text-sm">
                            <svg class="h-4 w-4 text-brand-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Partidos
                        </a>
                        {{-- Botón Imprimir Tabla de Posiciones (todas las categorías y grupos) --}}
                        <button type="button" onclick="imprimirEnIframe('{{ route('admin.imprimir-tabla-posiciones', ['idEdicion' => $idEdicionContexto ?? '']) }}')"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 sm:text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Imprimir Tablas de Posiciones
                        </button>
                        {{-- Botón Imprimir Fixture (todos los partidos o por fecha) --}}
                        <button type="button" onclick="abrirModalFixtureImpresion('{{ route('admin.imprimir-fixture', ['idEdicion' => $idEdicionContexto ?? '']) }}')"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 sm:text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Imprimir Fixture
                        </button>
                    </div>
                </div>

                {{-- KPIs --}}
                <div class="mb-4 grid grid-cols-2 gap-3 px-3 sm:px-4 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Equipos inscriptos</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ $resumenDashboard['equiposInscriptos'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Jugadores habilitados</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ $resumenDashboard['jugadoresHabilitados'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Partidos jugados</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ $partidosJugadosResumen }} / {{ $partidosTotalesResumen }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pendientes de carga</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ $resumenDashboard['pendientesDeCarga'] }}</p>
                    </div>
                </div>

                {{-- Todos los partidos de la edición: sección principal del dashboard, siempre abierta (pedido del usuario, T10). --}}
                @php
                    $pendientesPorPartido = collect($equiposSinAsistencia['equipos'] ?? [])->groupBy('partido_id');
                    $partidosPlanos = $partidos->flatten(1);

                    // Misma clave de agrupación que Panel.partido.index: el horario
                    // legacy (texto 24 h) gana sobre horario_datetime.
                    $gruposPorHorario = $partidosPlanos->groupBy(function ($p) {
                        if ($p->horario !== null && $p->horario !== '') {
                            if (preg_match('/^(\d{1,2})(?:[:.](\d{2}))?$/', trim((string) $p->horario), $partes)) {
                                return sprintf('%02d:%s', (int) $partes[1], $partes[2] ?? '00');
                            }

                            return 'sin-horario';
                        }

                        if ($p->horario_datetime) {
                            return \Carbon\Carbon::parse($p->horario_datetime)->format('H:i');
                        }

                        return 'sin-horario';
                    })->sortKeys();
                    $horarioFiltroActivo = request('horario');
                @endphp
                <section class="mb-4 px-3 sm:px-4" aria-labelledby="titulo-partidos-edicion">
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 id="titulo-partidos-edicion" class="text-lg font-bold text-gray-900">Todos los partidos de la edición</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $partidosPlanos->count() }} {{ $partidosPlanos->count() === 1 ? 'partido' : 'partidos' }} de la última fecha
                                </p>
                            </div>
                            <form action="{{ route('admin', $parametrosEdicionActual) }}" method="GET"
                                class="flex w-full flex-col gap-2 sm:flex-row sm:items-end md:w-auto filtro-fecha">
                                <div class="w-full sm:w-48">
                                    <x-ui.select label="Horario" id="horario" name="horario">
                                        <option value="">Todos los horarios</option>
                                        @foreach ($horariosDisponibles as $horario)
                                            <option value="{{ $horario }}" {{ request('horario') == $horario ? 'selected' : '' }}>
                                                {{ $horario }} hs
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                                @foreach (request()->except(['horario', '_token']) as $key => $value)
                                    @if (is_scalar($value))
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <x-ui.button type="submit" variant="primary" size="sm">Filtrar</x-ui.button>
                            </form>
                        </div>
                        @if ($horarioFiltroActivo)
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <x-ui.filter-chip active href="{{ route('admin', $parametrosEdicionActual) }}">
                                    Mostrando partidos de las {{ $horarioFiltroActivo }} hs
                                </x-ui.filter-chip>
                                <a href="{{ route('admin', $parametrosEdicionActual) }}"
                                    class="rounded-md px-1 py-1 text-sm font-semibold text-brand-navy hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                                    Limpiar
                                </a>
                            </div>
                        @endif
                    </div>

                    @if ($partidosPlanos->isEmpty())
                        <x-ui.empty-state class="mt-4" message="No hay partidos para mostrar"
                            description="{{ $horarioFiltroActivo ? 'Probá con otro horario o limpiá el filtro.' : 'Cuando se carguen partidos en la última fecha aparecerán acá.' }}" />
                    @else
                        @foreach ($gruposPorHorario as $horarioGrupo => $partidosGrupo)
                            <div class="mt-5" data-dashboard-horario="{{ $horarioGrupo }}">
                                <h3 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-brand-navy">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $horarioGrupo === 'sin-horario' ? 'Sin horario asignado' : $horarioGrupo . ' hs' }}
                                </h3>
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    @foreach ($partidosGrupo as $p)
                                        @php
                                            $resultadoCargado = $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null;
                                            $estadoGolesLabel = null;
                                            $estadoGolesVariant = null;

                                            if ($p->goles_confirmados_at !== null) {
                                                $estadoGolesLabel = 'Goles confirmados';
                                                $estadoGolesVariant = 'success';
                                            } elseif ($resultadoCargado) {
                                                $estadoGolesLabel = 'Goles pendientes de carga';
                                                $estadoGolesVariant = 'warning';
                                            }
                                        @endphp
                                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" data-dashboard-partido="{{ $p->id }}">
                                            <div class="flex flex-wrap items-center justify-center gap-2 text-center text-sm font-semibold uppercase tracking-wide text-gray-500">
                                                {{ $p->nombre_fecha }}
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                                                <x-ui.badge variant="info">{{ $p->nombre_categoria }}</x-ui.badge>
                                            </div>

                                            <div class="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                                                <div class="flex flex-col items-center gap-2">
                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" width="60" height="60" alt="Escudo {{ $p->nombre_local }}"
                                                        class="h-14 w-14 rounded-full object-cover md:h-[60px] md:w-[60px]" />
                                                    <div class="text-center text-base font-bold leading-tight text-gray-900 md:text-xl">{{ $p->nombre_local }}</div>
                                                </div>
                                                @if ($resultadoCargado)
                                                    <div data-dashboard-centro class="whitespace-nowrap text-center text-2xl font-extrabold text-gray-900 md:text-3xl">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</div>
                                                @else
                                                    <div data-dashboard-centro class="text-center text-sm font-semibold text-gray-400 md:text-lg">vs</div>
                                                @endif
                                                <div class="flex flex-col items-center gap-2">
                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" width="60" height="60" alt="Escudo {{ $p->nombre_visitante }}"
                                                        class="h-14 w-14 rounded-full object-cover md:h-[60px] md:w-[60px]" />
                                                    <div class="text-center text-base font-bold leading-tight text-gray-900 md:text-xl">{{ $p->nombre_visitante }}</div>
                                                </div>
                                            </div>

                                            <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-gray-600 md:text-base">
                                                @if ($horarioGrupo !== 'sin-horario')
                                                    <span data-dashboard-hora class="text-lg font-bold text-brand-navy">{{ $horarioGrupo }} hs</span>
                                                @endif
                                                @if ($p->horario_datetime)
                                                    <span class="font-medium">{{ \Carbon\Carbon::parse($p->horario_datetime)->format('d-m-Y') }}</span>
                                                @endif
                                                <span class="font-medium">Cancha {{ $p->cancha }}</span>
                                            </div>

                                            @if ($estadoGolesLabel)
                                                <div class="mt-3 flex justify-center">
                                                    <x-ui.badge :variant="$estadoGolesVariant">{{ $estadoGolesLabel }}</x-ui.badge>
                                                </div>
                                            @endif

                                            @if ($pendientesPorPartido->has($p->id))
                                                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                                    @foreach ($pendientesPorPartido->get($p->id) as $pendienteAsistencia)
                                                        <x-ui.badge :variant="$pendienteAsistencia['estado'] === 'sin_planilla' ? 'danger' : 'warning'">
                                                            <svg class="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            {{ $pendienteAsistencia['equipo'] }} ({{ $pendienteAsistencia['condicion'] }}): {{ $pendienteAsistencia['estado_label'] }}
                                                        </x-ui.badge>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @php
                                                $cobrosPartido = collect([
                                                    ['equipo' => $p->nombre_local, 'condicion' => 'Local', 'cargo' => ($cobrosPorPartido ?? collect())->get($p->id.'-'.$p->idEquipoLocal)],
                                                    ['equipo' => $p->nombre_visitante, 'condicion' => 'Visitante', 'cargo' => ($cobrosPorPartido ?? collect())->get($p->id.'-'.$p->idEquipoVisitante)],
                                                ])->filter(fn (array $cobro): bool => $cobro['cargo'] !== null);
                                            @endphp
                                            @if ($cobrosPartido->isNotEmpty())
                                                <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                                                    @foreach ($cobrosPartido as $cobro)
                                                        @php
                                                            $deudaCobro = number_format($cobro['cargo']['deuda_ars'], 0, ',', '.');
                                                            [$cobroVariant, $cobroLabel] = match ($cobro['cargo']['estado']) {
                                                                'pagado' => ['success', 'Planilla pagada'],
                                                                'exento' => ['info', 'Planilla exenta'],
                                                                'no_vino' => ['neutral', 'Planilla: no vino'],
                                                                'parcial' => ['warning', 'Pago parcial, debe $ '.$deudaCobro],
                                                                default => ['danger', 'Debe planilla $ '.$deudaCobro],
                                                            };
                                                        @endphp
                                                        <x-ui.badge :variant="$cobroVariant" data-dashboard-cobro="{{ $cobro['cargo']['id'] }}">
                                                            <span class="font-bold" aria-hidden="true">$</span>
                                                            {{ $cobro['equipo'] }} ({{ $cobro['condicion'] }}): {{ $cobroLabel }}
                                                        </x-ui.badge>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div class="mt-4 flex justify-center">
                                                <x-ui.button size="sm" variant="secondary"
                                                    href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $idEdicionContexto, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0, 'origen' => 'admin', 'horario_filtro' => $horarioString]) }}">
                                                    Planillas
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </section>

                {{-- Requiere atención + Próxima fecha / Jugadores por categoría --}}
                <div class="mb-4 grid grid-cols-1 gap-3 px-3 sm:px-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 lg:col-span-2">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-base font-bold text-gray-900">Requiere atención</h2>
                            <a href="{{ route('partido.index', ['idEdicion' => $idEdicionContexto]) }}" class="text-sm font-semibold text-brand-navy hover:underline">
                                Ver todos
                            </a>
                        </div>
                        @if ($resumenDashboard['partidosPendientes']->isEmpty())
                            <p class="text-sm text-gray-600">No hay partidos pendientes de atención en la última fecha.</p>
                        @else
                            <ul class="divide-y divide-gray-100">
                                @foreach ($resumenDashboard['partidosPendientes'] as $pendiente)
                                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $pendiente['categoria'] }}</p>
                                            <p class="truncate text-sm font-medium text-gray-900">
                                                {{ $pendiente['local'] }}
                                                <span class="text-gray-400">{{ $pendiente['golesEquipoLocal'] ?? '-' }} - {{ $pendiente['golesEquipoVisitante'] ?? '-' }}</span>
                                                {{ $pendiente['visitante'] }}
                                            </p>
                                        </div>
                                        <div class="flex flex-shrink-0 items-center gap-3">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $pendiente['estado']['clase'] }}">
                                                {{ $pendiente['estado']['label'] }}
                                            </span>
                                            <a href="{{ $pendiente['accionUrl'] }}" class="text-sm font-semibold text-brand-navy hover:underline">
                                                {{ $pendiente['accionLabel'] }}
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3">
                        @if ($resumenDashboard['proximaFecha'])
                            <div data-dashboard-proxima-fecha class="rounded-xl bg-brand-navy p-5 text-white">
                                <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Próxima fecha</p>
                                <span class="mt-2 inline-flex items-center rounded-full bg-brand-gold px-3 py-1 text-xs font-bold text-brand-navy">
                                    {{ $resumenDashboard['proximaFecha']['nombre'] }}
                                </span>
                                <dl class="mt-3 space-y-1 text-sm text-white/90">
                                    @if ($resumenDashboard['proximaFecha']['fecha'])
                                        <div class="flex justify-between"><dt>Fecha</dt><dd>{{ $resumenDashboard['proximaFecha']['fecha'] }}</dd></div>
                                    @endif
                                    <div class="flex justify-between"><dt>Partidos</dt><dd>{{ $resumenDashboard['proximaFecha']['cantidadPartidos'] }}</dd></div>
                                    <div class="flex justify-between"><dt>Canchas</dt><dd>{{ $resumenDashboard['proximaFecha']['cantidadCanchas'] }}</dd></div>
                                    @if ($resumenDashboard['proximaFecha']['primerHorario'])
                                        <div class="flex justify-between"><dt>Primer horario</dt><dd>{{ $resumenDashboard['proximaFecha']['primerHorario'] }}</dd></div>
                                    @endif
                                </dl>
                                <a href="{{ route('partido.index', ['idEdicion' => $idEdicionContexto, 'idFecha' => $resumenDashboard['proximaFecha']['id']]) }}"
                                    class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-white px-3 text-sm font-semibold text-brand-navy shadow-sm transition hover:bg-gray-100">
                                    Ver fixture de la fecha
                                </a>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Próxima fecha</p>
                                <p class="mt-2 text-sm text-gray-600">No hay partidos programados por jugar.</p>
                                <a href="{{ route('partido.index', ['idEdicion' => $idEdicionContexto]) }}"
                                    class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-900 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                                    Ir a partidos
                                </a>
                            </div>
                        @endif

                        <div class="rounded-xl border border-gray-200 bg-white p-5">
                            <h2 class="text-sm font-bold text-gray-900">Jugadores por categoría</h2>
                            @if (empty($jugadoresPorCategoriaResumen))
                                <p class="mt-2 text-sm text-gray-600">Sin datos de planillas en la última fecha.</p>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach ($jugadoresPorCategoriaResumen as $categoriaJugadoresResumen)
                                        <div>
                                            <div class="mb-1 flex justify-between text-xs font-medium text-gray-600">
                                                <span>{{ $categoriaJugadoresResumen['categoria'] }}</span>
                                                <span>{{ $categoriaJugadoresResumen['total_jugadores'] }}</span>
                                            </div>
                                            <div class="h-1.5 w-full rounded-full bg-gray-100">
                                                <div class="h-1.5 rounded-full bg-brand-teal" style="width: {{ max(6, (int) round($categoriaJugadoresResumen['total_jugadores'] / $maxJugadoresCategoriaResumen * 100)) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            @endif
        @endif
        @php
            $nombreRutaActual = request()->route()?->getName() ?? '';
            $esVistaShow = Str::endsWith($nombreRutaActual, '.show');
            $esVistaAdmin = request()->routeIs('admin');
            $clasesContenedorPrincipal = $esVistaAdmin
                ? 'mt-0 flex flex-grow items-center justify-center lg:pl-0'
                : 'mt-0 flex flex-grow justify-center lg:pl-0';
            $clasesSeccionPrincipal = $esVistaAdmin
                ? 'w-full min-w-0 px-3 sm:px-4 lg:w-3/4 lg:px-0'
                : 'w-full min-w-0 px-3 sm:px-4 lg:px-6';
        @endphp
        <div class="{{ $clasesContenedorPrincipal }}">
            <section class="{{ $clasesSeccionPrincipal }} panel-admin-content">
                @if (session('status'))
                    <div class="flex justify-center">
                        <div id="status-message" class="flex w-full items-center rounded-xl bg-white p-4 shadow-lg sm:w-11/12 lg:w-1/2">
                            <div class="flex h-10 w-10 items-center justify-center bg-blue-300 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                                    <path
                                        d="M256 48C141.1 48 48 141.1 48 256s93.1 208 208 208 208-93.1 208-208S370.9 48 256 48zm0 368c-88.2 0-160-71.8-160-160S167.8 96 256 96s160 71.8 160 160-71.8 160-160 160zm0-256c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zm0 96c-17.7 0-32 14.3-32 32v64c0 17.7 14.3 32 32 32s32-14.3 32-32v-64c0-17.7-14.3-32-32-32z" />
                                </svg>
                            </div>
                            <div class="ml-6">
                                <div class="flex justify-center">
                                    <p class="text-lg text-black font-semibold">{{ session('status') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        setTimeout(function () {
                            document.getElementById('status-message').remove();
                        }, 8000);
                    </script>
                @endif
                @if ($esVistaShow)
                    @yield('detalle')
                @else
                    @yield('contenido')
                @endif
            </section>
        </div>
    </div>

    <div id="fixture-impresion-modal" class="fixed inset-0 z-[80] hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="fixture-impresion-modal-title">
        <button type="button" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" aria-label="Cerrar modal de impresión de fixture" onclick="cerrarModalFixtureImpresion()"></button>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-white/15 bg-white shadow-2xl">
            <div class="bg-gradient-to-r from-blue-700 to-cyan-700 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-100">Fixture</p>
                        <h2 id="fixture-impresion-modal-title" class="mt-1 text-xl font-bold">Seleccionar fecha de impresión</h2>
                        <p class="mt-1 text-sm text-blue-100">Elegí una jornada específica o imprimí todo el fixture.</p>
                    </div>
                    <button type="button" class="rounded-full bg-white/15 p-2 text-white transition hover:bg-white/25" aria-label="Cerrar modal" onclick="cerrarModalFixtureImpresion()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div>
                    <label for="fecha-fixture-impresion" class="mb-2 block text-sm font-semibold text-slate-700">Fecha a imprimir</label>
                    <select id="fecha-fixture-impresion"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Todas las fechas</option>
                        @foreach ($fechasFixtureImpresion ?? collect() as $fechaFixture)
                            <option value="{{ $fechaFixture->id }}">{{ $fechaFixture->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Si elegís “Todas las fechas”, se imprime el fixture completo de la edición.</p>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" onclick="cerrarModalFixtureImpresion()">
                        Cancelar
                    </button>
                    <button type="button" id="fixture-impresion-confirmar" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-blue-800" onclick="confirmarImpresionFixture()">
                        Imprimir fixture
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mensajes de éxito
            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: @json(session('success')),
                    confirmButtonColor: '#3085d6',
                });
            @endif

            // Mensajes de advertencia
            @if (session('warning'))
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: @json(session('warning')),
                    confirmButtonColor: '#d97706',
                });
            @endif

            // Mensajes de error (personalizados)
            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error')),
                    confirmButtonColor: '#d33',
                });
            @endif

                // Errores de validación (Laravel)
                @if ($errors->any())
                    let errorHtml = '<ul style="text-align: left;">';
                    @foreach ($errors->all() as $error)
                        errorHtml += '<li>{{ $error }}</li>';
                    @endforeach
                    errorHtml += '</ul>';

                    Swal.fire({
                        icon: 'error',
                        title: 'Errores de Validación',
                        html: errorHtml,
                        confirmButtonColor: '#d33',
                    });
                @endif
        });


    </script>

    {{-- Función para imprimir usando iframe oculto --}}
    <script>
        function imprimirConTitulo(url, titulo) {
            const tituloOriginal = document.title;
            document.title = titulo;
            window.addEventListener('afterprint', function restaurar() {
                document.title = tituloOriginal;
                window.removeEventListener('afterprint', restaurar);
            });
            imprimirEnIframe(url);
        }

        function imprimirEnIframe(url) {
            // Crear iframe oculto
            let iframe = document.getElementById('print-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'print-iframe';
                iframe.style.position = 'absolute';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = 'none';
                iframe.style.left = '-9999px';
                document.body.appendChild(iframe);
            }
            
            // Mostrar loading
            Swal.fire({
                title: 'Preparando impresión...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Cargar contenido e imprimir
            iframe.onload = function() {
                Swal.close();
                setTimeout(() => {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                }, 500);
            };
            
            iframe.src = url;
        }

        let fixtureImpresionUrl = null;

        function abrirModalFixtureImpresion(url) {
            fixtureImpresionUrl = url;
            const modal = document.getElementById('fixture-impresion-modal');
            const selectorFecha = document.getElementById('fecha-fixture-impresion');

            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            setTimeout(() => selectorFecha?.focus(), 50);
        }

        function cerrarModalFixtureImpresion() {
            const modal = document.getElementById('fixture-impresion-modal');

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function confirmarImpresionFixture() {
            if (!fixtureImpresionUrl) {
                return;
            }

            imprimirFixtureCompleto(fixtureImpresionUrl);
            cerrarModalFixtureImpresion();
        }

        function imprimirFixtureCompleto(url) {
            const selectorFecha = document.getElementById('fecha-fixture-impresion');
            const urlImpresion = new URL(url, window.location.origin);

            if (selectorFecha?.value) {
                urlImpresion.searchParams.set('idFecha', selectorFecha.value);
            }

            imprimirEnIframe(urlImpresion.toString());
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalFixtureImpresion();
            }
        });
    </script>
    <script>
        // Buscador de jugador del header: trae el mismo resultado que Consulta de jugador y lo muestra en el modal.
        (() => {
            const formulario = document.querySelector('[data-buscador-jugador]');
            const modal = document.getElementById('buscador-jugador-modal');
            if (!formulario || !modal || typeof modal.showModal !== 'function') {
                return;
            }

            const contenido = document.getElementById('buscador-jugador-contenido');
            const titulo = document.getElementById('buscador-jugador-titulo');
            const campoDni = document.getElementById('buscador-jugador-dni');
            let consultaActual = 0;

            const mostrarAviso = (texto) => {
                const aviso = document.createElement('p');
                aviso.setAttribute('role', 'alert');
                aviso.className = 'rounded-lg border border-red-300 bg-red-50 p-4 text-red-800';
                aviso.textContent = texto;
                contenido.replaceChildren(aviso);
            };

            formulario.addEventListener('submit', async (evento) => {
                evento.preventDefault();
                const dni = campoDni.value.trim();
                const numeroConsulta = ++consultaActual;

                titulo.textContent = `Consulta de jugador · DNI ${dni}`;
                const cargando = document.createElement('p');
                cargando.className = 'text-sm text-gray-600';
                cargando.textContent = 'Buscando jugador…';
                contenido.replaceChildren(cargando);
                if (!modal.open) {
                    modal.showModal();
                }
                titulo.focus();

                const url = new URL(formulario.dataset.urlRapida, window.location.origin);
                url.searchParams.set('dni', dni);
                const idEdicion = formulario.elements.idEdicion.value;
                if (idEdicion) {
                    url.searchParams.set('idEdicion', idEdicion);
                }

                try {
                    const respuesta = await fetch(url, {
                        credentials: 'same-origin',
                        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (numeroConsulta !== consultaActual) {
                        return;
                    }
                    if (respuesta.redirected) {
                        mostrarAviso('Tu sesión expiró. Recargá la página para volver a ingresar.');
                        return;
                    }
                    if (!respuesta.ok && respuesta.status !== 422) {
                        throw new Error(`HTTP ${respuesta.status}`);
                    }
                    contenido.innerHTML = await respuesta.text();
                } catch (error) {
                    if (numeroConsulta === consultaActual) {
                        mostrarAviso('No se pudo completar la búsqueda. Probá de nuevo.');
                    }
                }
            });

            modal.querySelector('[data-buscador-jugador-cerrar]').addEventListener('click', () => modal.close());
            modal.addEventListener('click', (evento) => {
                if (evento.target === modal) {
                    modal.close();
                }
            });
            modal.addEventListener('close', () => {
                consultaActual++;
                campoDni.focus();
            });
        })();
    </script>
    @yield('scripts')
</body>

</html>
