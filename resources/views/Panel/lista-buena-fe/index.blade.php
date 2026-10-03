@extends('Panel.admin')

@section('contenido')
    @php
        $equiposSinJugadores = $EdicionSeleccionada && isset($equipos)
            ? $equipos->where('sin_jugadores', true)->count()
            : 0;
        $totalEquipos = $EdicionSeleccionada && isset($equipos) ? $equipos->count() : 0;
        $totalExcedidos = isset($equiposExcedidos) ? count($equiposExcedidos) : 0;
        $totalFechas = isset($fechasEdicion) ? $fechasEdicion->count() : 0;

        // Claves idEquipo-idCategoria que exceden el tope, para marcar el
        // badge correspondiente en cada tarjeta del grid sin otra consulta.
        $clavesExcedidas = collect($equiposExcedidos ?? [])
            ->map(fn ($e) => $e->idEquipo.'-'.$e->idCategoria);
    @endphp

    <x-ui.page-header title="Lista de buena fe"
        subtitle="Administrá la lista de buena fe de cada equipo: seleccioná una edición para ver sus equipos, imprimir planillas y gestionar jugadores." />

    {{-- ═══════════════ ALERTA DE EXCEDIDOS ═══════════════ --}}
    @if (isset($equiposExcedidos) && count($equiposExcedidos) > 0)
        <div role="alert" class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <p class="font-semibold text-amber-900">Equipos con más de {{ $limiteJugadoresPorPlanilla }} jugadores</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-800">
                @foreach ($equiposExcedidos as $equipoExcedido)
                    <li>
                        <strong>{{ $equipoExcedido->equipo->nombre }}</strong>
                        ({{ $equipoExcedido->categoria->nombreCategoria }}): {{ $equipoExcedido->total }} jugadores
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════════════ FILTROS ═══════════════ --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
        <form method="GET" action="{{ route('lista-buena-fe.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-ui.select label="Edición" name="idEdicion" id="idEdicion" onchange="this.form.submit()">
                <option value="">-- Seleccione una edición --</option>
                @foreach ($ediciones as $edicion)
                    <option value="{{ $edicion->id }}" {{ $EdicionSeleccionada && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                        {{ $edicion->nombre }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Categoría" name="idCategoria" id="idCategoria" onchange="this.form.submit()"
                :disabled="! $EdicionSeleccionada">
                <option value="">-- Todas las categorías --</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" {{ $idCategoria == $categoria->id ? 'selected' : '' }}>
                        {{ $categoria->nombreCategoria }}
                    </option>
                @endforeach
            </x-ui.select>
        </form>
    </div>

    @if ($EdicionSeleccionada)
        {{-- ═══════════════ STATS ═══════════════ --}}
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
                <span class="text-2xl font-bold text-gray-900">{{ $totalEquipos }}</span>
                <p class="text-xs font-medium text-gray-500">Equipos</p>
            </div>
            <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center {{ $equiposSinJugadores > 0 ? 'border-amber-300' : '' }}">
                <span class="text-2xl font-bold {{ $equiposSinJugadores > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $equiposSinJugadores }}</span>
                <p class="text-xs font-medium text-gray-500">Sin jugadores</p>
            </div>
            <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center {{ $totalExcedidos > 0 ? 'border-red-300' : '' }}">
                <span class="text-2xl font-bold {{ $totalExcedidos > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $totalExcedidos }}</span>
                <p class="text-xs font-medium text-gray-500">Excedidos</p>
            </div>
            <div class="min-w-[140px] rounded-lg border border-gray-200 bg-white px-6 py-4 text-center">
                <span class="text-2xl font-bold text-gray-900">{{ $totalFechas }}</span>
                <p class="text-xs font-medium text-gray-500">Fechas</p>
            </div>
        </div>

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

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">

                {{-- Search (dominante) --}}
                <form action="{{ route('lista-buena-fe.index') }}" method="GET" class="flex min-w-0 flex-1 items-center gap-2">
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <input type="hidden" name="idCategoria" value="{{ $idCategoria ?? '' }}">
                    <div class="min-w-0 flex-1">
                        <x-ui.search label="Buscar equipo" labelHidden name="search" :value="request('search')"
                            placeholder="Buscar equipo por nombre..." />
                    </div>
                    @if (request('search'))
                        <x-ui.button href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria ?? '']) }}"
                            variant="secondary">
                            Limpiar
                        </x-ui.button>
                    @endif
                </form>

                <div class="hidden h-8 w-px bg-gray-200 lg:block"></div>

                {{-- Actions (dropdowns) --}}
                <div class="flex flex-wrap items-center gap-2">

                    {{-- Dropdown: Impresión --}}
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open"
                            class="flex h-10 items-center gap-2 rounded-lg bg-brand-navy px-4 text-sm font-semibold text-white shadow hover:bg-brand-navy/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40 whitespace-nowrap">
                            <span>Impresión</span>
                            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition
                            class="absolute right-0 z-20 mt-2 w-80 rounded-xl bg-white p-2 shadow-xl ring-1 ring-gray-200">

                            <button type="button"
                                data-print-url="{{ $urlImpresionMasiva }}"
                                class="flex w-full items-start gap-3 rounded-lg p-3 text-left hover:bg-gray-50"
                                onclick="printExternal(this.dataset.printUrl); $dispatch('close-menu')">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Todas las planillas</p>
                                    <p class="text-xs text-gray-500">Imprime las planillas de todos los equipos filtrados.</p>
                                </div>
                            </button>

                            <div class="my-1 border-t border-gray-100"></div>

                            <div class="p-3">
                                <p class="mb-2 text-sm font-semibold text-gray-800">Por fecha</p>
                                <form data-print-fecha-form class="flex items-center gap-2">
                                    <select name="idFechaImpresion"
                                        id="idFechaImpresion"
                                        class="h-10 min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                                        @disabled($fechasEdicion->isEmpty())
                                        onchange="actualizarEstadoImpresionFecha(this)">
                                        <option value="">-- Seleccionar --</option>
                                        @foreach ($fechasEdicion as $fecha)
                                            <option value="{{ $fecha->id }}">{{ $fecha->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-ui.button type="button" size="sm" variant="primary"
                                        data-print-url-base="{{ $urlImpresionPorFecha }}"
                                        disabled
                                        onclick="imprimirPlanillasPorFecha(this)">
                                        Imprimir
                                    </x-ui.button>
                                </form>
                                @if ($fechasEdicion->isEmpty())
                                    <p class="mt-2 text-xs text-amber-600">Esta edición todavía no tiene fechas cargadas.</p>
                                @else
                                    <p class="mt-2 text-xs text-gray-500">Se habilita al seleccionar una fecha.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Dropdown: Administración --}}
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open"
                            class="flex h-10 items-center gap-2 rounded-lg bg-gray-700 px-4 text-sm font-semibold text-white shadow hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/40 whitespace-nowrap">
                            <span>Administración</span>
                            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition
                            class="absolute right-0 z-20 mt-2 w-72 rounded-xl bg-white p-2 shadow-xl ring-1 ring-gray-200">

                            <form method="POST" action="{{ route('lista-buena-fe.migrar-todos') }}">
                                @csrf
                                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                                <button type="submit"
                                    class="flex w-full items-start gap-3 rounded-lg p-3 text-left hover:bg-gray-50"
                                    onclick="return confirm('¿Estás seguro de querer migrar los jugadores de TODOS los equipos de esta edición? Esto puede tomar unos momentos.')">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Migrar todos los equipos</p>
                                        <p class="text-xs text-gray-500">Importa jugadores de todos los equipos de esta edición.</p>
                                    </div>
                                </button>
                            </form>

                            <div class="my-1 border-t border-gray-100"></div>

                            <a href="{{ route('lista-buena-fe.conflictivos') }}"
                                class="flex items-start gap-3 rounded-lg p-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Revisión de jugadores</p>
                                    <p class="text-xs text-gray-500">Detecta duplicados y conflictos entre equipos.</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════ BANNER DE PRIORIDAD ═══════════════ --}}
        @if ($equiposSinJugadores > 0)
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Prioridad administrativa</p>
                        <h2 class="text-lg font-bold text-gray-800">{{ $equiposSinJugadores }} {{ $equiposSinJugadores === 1 ? 'equipo' : 'equipos' }} sin jugadores cargados</h2>
                    </div>
                    <p class="text-sm text-gray-600 md:text-right">
                        Se muestran primero para facilitar la carga inicial.
                    </p>
                </div>
            </div>
        @endif

        {{-- ═══════════════ GRID DE EQUIPOS ═══════════════ --}}
        @if ($equipos->isEmpty())
            <x-ui.empty-state class="mt-6" message="No se encontraron equipos"
                description="Probá cambiar la categoría o ajustar el filtro de búsqueda." />
        @else
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($equipos as $equipo)
                    @php
                        $sinJugadores = (bool) ($equipo->sin_jugadores ?? false);
                        $totalJugadoresActivos = (int) ($equipo->total_jugadores_activos ?? 0);
                        $excedeElTope = $clavesExcedidas->contains($equipo->idEquipo.'-'.$equipo->idCategoria);
                        $porcentajeCupo = $limiteJugadoresPorPlanilla > 0
                            ? min(100, (int) round($totalJugadoresActivos / $limiteJugadoresPorPlanilla * 100))
                            : 0;
                        $colorBarra = $excedeElTope ? 'bg-red-500' : ($sinJugadores ? 'bg-amber-400' : 'bg-brand-navy');
                    @endphp
                    <a href="{{ route('lista-buena-fe.show', ['idEquipo' => $equipo->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                        class="group block overflow-hidden rounded-xl border bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md {{ $sinJugadores ? 'border-amber-300' : 'border-gray-200' }}">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        {{ $equipo->categoria->nombreCategoria }}
                                    </p>
                                    <h3 class="mt-1 truncate text-base font-semibold text-gray-800">{{ $equipo->equipo->nombre }}</h3>
                                </div>
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100">
                                    @if ($equipo->equipo->foto)
                                        <img src="{{ asset('fotos/equipos/'.$equipo->equipo->foto) }}" alt="{{ $equipo->equipo->nombre }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @if ($sinJugadores)
                                    <x-ui.badge variant="warning">Sin jugadores</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success">{{ $totalJugadoresActivos }} jugadores</x-ui.badge>
                                @endif
                                @if ($excedeElTope)
                                    <x-ui.badge variant="danger">Excede el tope</x-ui.badge>
                                @endif
                            </div>

                            <div class="mt-3">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full {{ $colorBarra }}" style="width: {{ $porcentajeCupo }}%"></div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ $totalJugadoresActivos }}/{{ $limiteJugadoresPorPlanilla }} jugadores</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-center text-xs font-semibold text-brand-navy group-hover:underline">
                            Ver lista de buena fe
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición para comenzar"
            description="Los equipos y acciones aparecerán abajo." />
    @endif

    {{-- Modal de Resultado de Migración --}}
    @if (session('migration_result'))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full
                                {{ session('migration_result')['status'] == 'success' ? 'bg-green-100' : (session('migration_result')['status'] == 'warning' ? 'bg-yellow-100' : 'bg-red-100') }}
                                sm:mx-0 sm:h-10 sm:w-10">
                                @if (session('migration_result')['status'] == 'success')
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                @elseif (session('migration_result')['status'] == 'warning')
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                @else
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                @endif
                            </div>
                            <div class="mt-3 w-full text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                                    Resultado de Migración
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        {{ session('migration_result')['message'] }}
                                    </p>
                                    @if (isset(session('migration_result')['errors']) && count(session('migration_result')['errors']) > 0)
                                        <div class="mt-3 max-h-40 overflow-y-auto rounded bg-red-50 p-2 text-left">
                                            <p class="mb-1 text-xs font-bold text-red-700">Errores:</p>
                                            <ul class="list-inside list-disc text-xs text-red-600">
                                                @foreach (session('migration_result')['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="button" onclick="document.querySelector('.fixed.inset-0').remove()"
                            class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-navy px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-navy/90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
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
