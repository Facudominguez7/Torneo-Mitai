@extends('Panel.admin')

@section('contenido')
    @php
        $equiposSinJugadores = $EdicionSeleccionada && isset($equipos)
            ? $equipos->where('sin_jugadores', true)->count()
            : 0;
        $totalEquipos = $EdicionSeleccionada && isset($equipos) ? $equipos->count() : 0;
        $totalExcedidos = isset($equiposExcedidos) ? count($equiposExcedidos) : 0;
        $totalFechas = isset($fechasEdicion) ? $fechasEdicion->count() : 0;
    @endphp

    <div class="container mx-auto px-4 py-6 space-y-6">

        {{-- ═══════════════ HERO ═══════════════ --}}
        <header class="flex flex-col gap-2 text-white">
            <div class="flex items-center gap-3">
                <span class="text-3xl">📋</span>
                <h1 class="text-3xl font-bold tracking-tight">Gestión de Lista de Buena Fe</h1>
            </div>
            <p class="text-sm text-slate-300 max-w-3xl">
                Administrá la lista de buena fe de cada equipo. Seleccioná una edición para ver sus equipos, imprimir planillas y gestionar jugadores.
            </p>
        </header>

        {{-- ═══════════════ ALERTA DE EXCEDIDOS ═══════════════ --}}
        @if(isset($equiposExcedidos) && count($equiposExcedidos) > 0)
            <div role="alert" class="rounded-2xl border border-yellow-400/40 bg-gradient-to-r from-yellow-50 to-amber-50 p-5 shadow-md">
                <div class="flex gap-3">
                    <span class="text-2xl">⚠️</span>
                    <div class="flex-1">
                        <h3 class="font-bold text-yellow-900">Equipos con más de {{ $limiteJugadoresPorPlanilla }} jugadores</h3>
                        <ul class="mt-2 list-disc list-inside text-sm text-yellow-800 space-y-1">
                            @foreach($equiposExcedidos as $equipoExcedido)
                                <li>
                                    <strong>{{ $equipoExcedido->equipo->nombre }}</strong>
                                    <span class="text-yellow-700">({{ $equipoExcedido->categoria->nombreCategoria }})</span>:
                                    {{ $equipoExcedido->total }} jugadores
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════ FILTROS ═══════════════ --}}
        <section class="rounded-2xl bg-white p-5 shadow-md ring-1 ring-slate-200/50">
            <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Contexto</h2>
            <form method="GET" action="{{ route('lista-buena-fe.index') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="idEdicion" class="mb-1 block text-sm font-semibold text-slate-700">Edición</label>
                    <select name="idEdicion" id="idEdicion"
                            class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="this.form.submit()">
                        <option value="">-- Seleccione una edición --</option>
                        @foreach ($ediciones as $edicion)
                            <option value="{{ $edicion->id }}" {{ $EdicionSeleccionada && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                {{ $edicion->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="idCategoria" class="mb-1 block text-sm font-semibold text-slate-700">Categoría</label>
                    <select name="idCategoria" id="idCategoria"
                            class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="this.form.submit()"
                            {{ !$EdicionSeleccionada ? 'disabled' : '' }}>
                        <option value="">-- Todas las categorías --</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" {{ $idCategoria == $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nombreCategoria }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        @if ($EdicionSeleccionada)

            {{-- ═══════════════ STATS ═══════════════ --}}
            <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-slate-200/50">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Equipos</p>
                    <p class="mt-1 text-3xl font-bold text-slate-800">{{ $totalEquipos }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-slate-200/50 {{ $equiposSinJugadores > 0 ? 'border-l-4 border-amber-400' : '' }}">
                    <p class="text-xs font-bold uppercase tracking-wider {{ $equiposSinJugadores > 0 ? 'text-amber-600' : 'text-slate-500' }}">Sin jugadores</p>
                    <p class="mt-1 text-3xl font-bold {{ $equiposSinJugadores > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $equiposSinJugadores }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-slate-200/50 {{ $totalExcedidos > 0 ? 'border-l-4 border-red-400' : '' }}">
                    <p class="text-xs font-bold uppercase tracking-wider {{ $totalExcedidos > 0 ? 'text-red-600' : 'text-slate-500' }}">Excedidos</p>
                    <p class="mt-1 text-3xl font-bold {{ $totalExcedidos > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $totalExcedidos }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-slate-200/50">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Fechas</p>
                    <p class="mt-1 text-3xl font-bold text-slate-800">{{ $totalFechas }}</p>
                </div>
            </section>

            {{-- ═══════════════ ACTION TOOLBAR ═══════════════ --}}
            @php
                $urlImpresionMasiva = route('lista-buena-fe.imprimir-planillas', [
                    'idEdicion' => $EdicionSeleccionada->id,
                    'idCategoria' => $idCategoria,
                    'search' => request('search'),
                ]);
                $urlImpresionPorFecha = route('lista-buena-fe.imprimir-planillas-fecha', [
                    'idEdicion' => $EdicionSeleccionada->id,
                    'idFecha' => '__FECHA__',
                ]);
            @endphp

            <section class="rounded-2xl bg-white p-4 shadow-md ring-1 ring-slate-200/50">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">

                    {{-- Search (dominante) --}}
                    <form action="{{ route('lista-buena-fe.index') }}" method="GET" class="flex min-w-0 flex-1 items-center gap-2">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <input type="hidden" name="idCategoria" value="{{ $idCategoria ?? '' }}">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Buscar equipo por nombre..."
                                class="h-10 w-full rounded-lg border-slate-300 pl-10 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        @if(request('search'))
                            <a href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria ?? '']) }}"
                               class="flex h-10 shrink-0 items-center rounded-lg bg-slate-100 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                                Limpiar
                            </a>
                        @endif
                    </form>

                    {{-- Separator --}}
                    <div class="hidden h-8 w-px bg-slate-200 lg:block"></div>

                    {{-- Actions (dropdowns) --}}
                    <div class="flex flex-wrap items-center gap-2">

                        {{-- Dropdown: Impresión --}}
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open"
                                    class="flex h-10 items-center gap-2 rounded-lg bg-green-600 px-4 text-sm font-bold text-white shadow hover:bg-green-700 whitespace-nowrap">
                                <span>🖨️</span>
                                <span>Impresión</span>
                                <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                                 class="absolute right-0 z-20 mt-2 w-80 rounded-xl bg-white p-2 shadow-xl ring-1 ring-slate-200">

                                <button type="button"
                                        data-print-url="{{ $urlImpresionMasiva }}"
                                        class="flex w-full items-start gap-3 rounded-lg p-3 text-left hover:bg-slate-50"
                                        onclick="printExternal(this.dataset.printUrl); $dispatch('close-menu')">
                                    <span class="text-xl">🖨️</span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Todas las planillas</p>
                                        <p class="text-xs text-slate-500">Imprime las planillas de todos los equipos filtrados.</p>
                                    </div>
                                </button>

                                <div class="my-1 border-t border-slate-100"></div>

                                <div class="p-3">
                                    <p class="mb-2 text-sm font-semibold text-slate-800 flex items-center gap-2">
                                        <span>🗓️</span> Por fecha
                                    </p>
                                    <form data-print-fecha-form class="flex items-center gap-2">
                                        <select name="idFechaImpresion"
                                                id="idFechaImpresion"
                                                class="h-10 min-w-0 flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                @disabled($fechasEdicion->isEmpty())
                                                onchange="actualizarEstadoImpresionFecha(this)">
                                            <option value="">-- Seleccionar --</option>
                                            @foreach ($fechasEdicion as $fecha)
                                                <option value="{{ $fecha->id }}">{{ $fecha->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button"
                                                class="flex h-10 shrink-0 items-center rounded-md bg-blue-600 px-3 text-sm font-bold text-white shadow hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                                                data-print-url-base="{{ $urlImpresionPorFecha }}"
                                                disabled
                                                onclick="imprimirPlanillasPorFecha(this)">
                                            Imprimir
                                        </button>
                                    </form>
                                    @if($fechasEdicion->isEmpty())
                                        <p class="mt-2 text-xs text-amber-600">Esta edición todavía no tiene fechas cargadas.</p>
                                    @else
                                        <p class="mt-2 text-xs text-slate-500">Se habilita al seleccionar una fecha.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Dropdown: Administración --}}
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open"
                                    class="flex h-10 items-center gap-2 rounded-lg bg-slate-700 px-4 text-sm font-bold text-white shadow hover:bg-slate-800 whitespace-nowrap">
                                <span>⚙️</span>
                                <span>Administración</span>
                                <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                                 class="absolute right-0 z-20 mt-2 w-72 rounded-xl bg-white p-2 shadow-xl ring-1 ring-slate-200">

                                <form method="POST" action="{{ route('lista-buena-fe.migrar-todos') }}">
                                    @csrf
                                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                    <button type="submit"
                                            class="flex w-full items-start gap-3 rounded-lg p-3 text-left hover:bg-slate-50"
                                            onclick="return confirm('¿Estás seguro de querer migrar los jugadores de TODOS los equipos de esta edición? Esto puede tomar unos momentos.')">
                                        <span class="text-xl">🔄</span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">Migrar todos los equipos</p>
                                            <p class="text-xs text-slate-500">Importa jugadores de todos los equipos de esta edición.</p>
                                        </div>
                                    </button>
                                </form>

                                <div class="my-1 border-t border-slate-100"></div>

                                <a href="{{ route('lista-buena-fe.conflictivos') }}"
                                   class="flex items-start gap-3 rounded-lg p-3 hover:bg-slate-50">
                                    <span class="text-xl">📋</span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Revisión de jugadores</p>
                                        <p class="text-xs text-slate-500">Detecta duplicados y conflictos entre equipos.</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ═══════════════ BANNER DE PRIORIDAD ═══════════════ --}}
            @if($equiposSinJugadores > 0)
                <div class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 via-white to-rose-50 px-5 py-4 shadow-sm">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">⚡</span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Prioridad administrativa</p>
                                <h2 class="text-lg font-bold text-slate-800">{{ $equiposSinJugadores }} {{ $equiposSinJugadores === 1 ? 'equipo' : 'equipos' }} sin jugadores cargados</h2>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600 md:text-right">
                            Se muestran primero para facilitar la carga inicial.
                        </p>
                    </div>
                </div>
            @endif

            {{-- ═══════════════ GRID DE EQUIPOS ═══════════════ --}}
            @if ($equipos->isEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-md">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-3xl">
                        🔍
                    </div>
                    <p class="text-lg font-semibold text-slate-800">No se encontraron equipos</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Probá cambiar la categoría o ajustar el filtro de búsqueda.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach ($equipos as $equipo)
                        @php
                            $sinJugadores = (bool) ($equipo->sin_jugadores ?? false);
                            $totalJugadoresActivos = (int) ($equipo->total_jugadores_activos ?? 0);
                        @endphp
                        <a href="{{ route('lista-buena-fe.show', ['idEquipo' => $equipo->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                           class="group block overflow-hidden rounded-2xl border transition duration-200 transform hover:-translate-y-1 hover:shadow-xl {{ $sinJugadores ? 'border-amber-300 bg-gradient-to-br from-amber-50 via-white to-rose-50 shadow-lg shadow-amber-100/70' : 'border-slate-200 bg-white shadow-md' }}">
                            <div class="p-4 text-center">
                                <div class="mb-4 flex items-start justify-between gap-3 text-left">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $sinJugadores ? 'text-amber-700' : 'text-slate-400' }}">
                                            {{ $equipo->categoria->nombreCategoria }}
                                        </p>
                                        @if($sinJugadores)
                                            <span class="mt-2 inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                                Sin jugadores
                                            </span>
                                        @else
                                            <span class="mt-2 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {{ $totalJugadoresActivos }} jugadores
                                            </span>
                                        @endif
                                    </div>
                                    @if($sinJugadores)
                                        <span class="rounded-full bg-white/80 px-2 py-1 text-lg shadow-sm">⚠️</span>
                                    @endif
                                </div>

                                <div class="mx-auto mb-3 flex h-20 w-20 items-center justify-center overflow-hidden rounded-full {{ $sinJugadores ? 'bg-amber-100' : 'bg-slate-100' }}">
                                    @if($equipo->equipo->foto)
                                        <img src="{{ asset('fotos/equipos/' . $equipo->equipo->foto) }}" alt="{{ $equipo->equipo->nombre }}" class="h-full w-full object-cover">
                                    @else
                                        <span class="text-2xl">🛡️</span>
                                    @endif
                                </div>
                                <h3 class="text-lg font-semibold text-slate-800">{{ $equipo->equipo->nombre }}</h3>
                                <p class="mt-1 text-sm font-medium {{ $sinJugadores ? 'text-amber-700' : 'text-blue-600' }}">
                                    {{ $sinJugadores ? 'Requiere carga inicial' : 'Lista disponible' }}
                                </p>
                            </div>
                            <div class="px-4 py-3 border-t text-center {{ $sinJugadores ? 'border-amber-200 bg-white/80' : 'border-slate-100 bg-slate-50' }}">
                                <span class="text-xs font-semibold uppercase tracking-wider {{ $sinJugadores ? 'text-amber-700' : 'text-slate-500' }} group-hover:text-blue-600">
                                    Ver Lista de Buena Fe →
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @else
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-12 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-3xl">
                    👆
                </div>
                <p class="text-lg font-semibold text-blue-800">Seleccioná una edición para comenzar</p>
                <p class="mt-1 text-sm text-blue-600">Los equipos y acciones aparecerán abajo.</p>
            </div>
        @endif
    </div>

    {{-- Modal de Resultado de Migración --}}
    @if (session('migration_result'))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full
                                {{ session('migration_result')['status'] == 'success' ? 'bg-green-100' : (session('migration_result')['status'] == 'warning' ? 'bg-yellow-100' : 'bg-red-100') }}
                                sm:mx-0 sm:h-10 sm:w-10">
                                @if(session('migration_result')['status'] == 'success')
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                @elseif(session('migration_result')['status'] == 'warning')
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                @else
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                @endif
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Resultado de Migración
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        {{ session('migration_result')['message'] }}
                                    </p>
                                    @if(isset(session('migration_result')['errors']) && count(session('migration_result')['errors']) > 0)
                                        <div class="mt-3 bg-red-50 p-2 rounded max-h-40 overflow-y-auto text-left">
                                            <p class="text-xs font-bold text-red-700 mb-1">Errores:</p>
                                            <ul class="list-disc list-inside text-xs text-red-600">
                                                @foreach(session('migration_result')['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" onclick="document.querySelector('.fixed.inset-0').remove()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>[x-cloak]{display:none !important}</style>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-print-fecha-form] select').forEach(function (select) {
                actualizarEstadoImpresionFecha(select);
            });
        });

        function printExternal(url) {
            let printFrame = document.getElementById('printFrame');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'printFrame';
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
            }
            printFrame.src = url;
        }

        function actualizarEstadoImpresionFecha(select) {
            const form = select.closest('[data-print-fecha-form]');
            const button = form ? form.querySelector('[data-print-url-base]') : null;
            if (button) {
                button.disabled = ! select.value;
            }
        }

        function imprimirPlanillasPorFecha(button) {
            const form = button.closest('[data-print-fecha-form]');
            const select = form ? form.querySelector('[name="idFechaImpresion"]') : null;
            if (! select || ! select.value) {
                alert('Seleccioná una fecha antes de imprimir.');
                return;
            }
            const urlBase = button.dataset.printUrlBase;
            printExternal(urlBase.replace('__FECHA__', select.value));
        }
    </script>
@endsection
