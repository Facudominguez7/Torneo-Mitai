{{--
    Navegación lateral del panel de administración.

    Se incluye una única vez dentro del <aside> de resources/views/Panel/admin.blade.php,
    que se comporta como sidebar fija en desktop (lg+) y como drawer off-canvas en mobile.
    Este parcial concentra la configuración de grupos/items para que ambos contextos
    (desktop y drawer) compartan exactamente el mismo marcado.
--}}
@php
    $gruposNav = [
        [
            'id' => 'hoy',
            'label' => 'Hoy',
            'siempreAbierto' => true,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => route('admin', $parametrosEdicionActual),
                    'activo' => request()->routeIs('admin', 'admin.*'),
                ],
                [
                    'label' => 'Partidos',
                    'route' => route('partido.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('partido.*', 'cargar-resultado', 'planilla.*'),
                ],
            ],
        ],
        [
            'id' => 'torneo',
            'label' => 'Torneo',
            'items' => [
                [
                    'label' => 'Ediciones',
                    'route' => route('edicion.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('edicion.*'),
                ],
                [
                    'label' => 'Categorías',
                    'route' => route('categoria.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('categoria.*'),
                ],
                [
                    'label' => 'Grupos',
                    'route' => route('grupos.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('grupos.*', 'equipogrupo.*'),
                ],
                [
                    'label' => 'Fechas',
                    'route' => route('fecha.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('fecha.*'),
                ],
                [
                    'label' => 'Fases',
                    'route' => route('fase.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('fase.*'),
                ],
                [
                    'label' => 'Copas',
                    'route' => route('copa.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('copa.*'),
                ],
                [
                    'label' => 'Instancias finales',
                    'route' => route('instancia_final.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('instancia_final*', 'cargar-resultado-instancia'),
                ],
            ],
        ],
        [
            'id' => 'equipos-jugadores',
            'label' => 'Equipos y jugadores',
            'items' => [
                [
                    'label' => 'Instituciones',
                    'route' => route('institucion.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('institucion.*'),
                ],
                [
                    'label' => 'Equipos',
                    'route' => route('equipo.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('equipo.*'),
                ],
                [
                    'label' => 'Lista de buena fe',
                    'route' => route('lista-buena-fe.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('lista-buena-fe.index', 'lista-buena-fe.show', 'lista-buena-fe.pdf', 'lista-buena-fe.imprimir-planilla*'),
                ],
                [
                    'label' => 'Revisión de jugadores',
                    'route' => route('lista-buena-fe.conflictivos'),
                    'activo' => request()->routeIs('lista-buena-fe.conflictivos', 'lista-buena-fe.resolver-conflicto', 'lista-buena-fe.buscar-dni'),
                ],
                [
                    'label' => 'Consulta de jugador',
                    'route' => route('jugador.consulta', $parametrosEdicionActual),
                    'activo' => request()->routeIs('jugador.*'),
                ],
            ],
        ],
        [
            'id' => 'estadisticas',
            'label' => 'Estadísticas',
            'items' => [
                [
                    'label' => 'Tabla de Posiciones',
                    'route' => route('tabla_posicion.index', array_filter([
                        ...$parametrosEdicionActual,
                        'idCategoria' => request('idCategoria'),
                        'idGrupo' => request('idGrupo'),
                    ], static fn ($valor) => $valor !== null && $valor !== '')),
                    'activo' => request()->routeIs('tabla_posicion.*'),
                ],
                [
                    'label' => 'Tabla de goleadores',
                    'route' => route('tabla_goleador.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('tabla_goleador.*'),
                ],
                [
                    // Ranking de solo lectura (T8c, panel-admin-redesign);
                    // cargar o cambiar el arquero se hace desde Premios
                    // individuales. valla.index sigue existiendo
                    // (seleccionar-categoria, redirects de
                    // store/update/destroy) pero redirige a Premios.
                    'label' => 'Vallas menos vencidas',
                    'route' => route('vallas.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('vallas.*'),
                ],
                [
                    'label' => 'Estadísticas de Jugadores',
                    'route' => route('estadisticas-jugadores', $parametrosEdicionActual),
                    'activo' => request()->routeIs('estadisticas-jugadores'),
                ],
                [
                    // Podio fusiona Campeones y Subcampeones (T8b,
                    // panel-admin-redesign); campeon.index/subcampeon.index
                    // siguen existiendo (seleccionar-categoria, redirects de
                    // store/update/destroy) pero redirigen acá.
                    'label' => 'Podio',
                    'route' => route('podio.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('podio.*', 'campeon.*', 'subcampeon.*'),
                ],
                [
                    // Premios individuales fusiona Goleador del torneo y
                    // Valla menos vencida (T8c, panel-admin-redesign);
                    // goleador.index/valla.index siguen existiendo
                    // (seleccionar-categoria, redirects de
                    // store/update/destroy) pero redirigen acá.
                    'label' => 'Premios individuales',
                    'route' => route('premios.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('premios.*', 'goleador.*', 'valla.*'),
                ],
            ],
        ],
        [
            'id' => 'comunicacion',
            'label' => 'Comunicación',
            'items' => [
                [
                    'label' => 'Suscriptores',
                    'route' => route('notificaciones.index', $parametrosEdicionActual),
                    'activo' => request()->routeIs('notificaciones.*'),
                ],
            ],
        ],
    ];

    foreach ($gruposNav as $indiceGrupo => $grupoNav) {
        $grupoActivo = collect($grupoNav['items'])->contains(fn ($item) => $item['activo']);
        $gruposNav[$indiceGrupo]['activo'] = $grupoActivo;
        $gruposNav[$indiceGrupo]['abierto'] = ($grupoNav['siempreAbierto'] ?? false) || $grupoActivo;
    }
@endphp

<div class="flex h-[72px] shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-4">
    <a href="{{ route('admin', $parametrosEdicionActual) }}" class="flex min-w-0 items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-brand-navy text-sm font-bold text-brand-gold">
            M
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-bold text-gray-900">Torneo Mitai</span>
            <span class="block truncate text-xs text-gray-600">Panel de administración</span>
        </span>
    </a>
    <button type="button" @click="sidebarAbierto = false"
        aria-label="Cerrar menú"
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40 lg:hidden">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navegación principal">
    <div class="space-y-4">
        @foreach ($gruposNav as $grupoNav)
            <div data-grupo-nav="{{ $grupoNav['id'] }}" data-grupo-abierto="{{ $grupoNav['abierto'] ? 'true' : 'false' }}"
                x-data="{ open: $el.dataset.grupoAbierto === 'true' }">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                    aria-controls="grupo-nav-{{ $grupoNav['id'] }}"
                    class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                    <span>{{ $grupoNav['label'] }}</span>
                    <span class="flex items-center gap-1.5">
                        <span x-show="!open" @if ($grupoNav['abierto']) x-cloak @endif
                            class="rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-bold normal-case tracking-normal text-gray-600">
                            {{ count($grupoNav['items']) }}
                        </span>
                        <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform duration-150" :class="{ '-rotate-90': !open }"
                            fill="none" viewBox="0 0 10 6" aria-hidden="true">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                        </svg>
                    </span>
                </button>
                <ul id="grupo-nav-{{ $grupoNav['id'] }}" x-show="open" @unless ($grupoNav['abierto']) x-cloak @endunless
                    class="mt-1 space-y-0.5">
                    @foreach ($grupoNav['items'] as $item)
                        <li>
                            <a href="{{ $item['route'] }}" @click="sidebarAbierto = false"
                                @if ($item['activo']) aria-current="page" @endif
                                class="flex h-9 items-center rounded-lg px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40 {{ $item['activo'] ? 'bg-brand-navy/10 font-semibold text-brand-navy' : 'text-gray-700 hover:bg-gray-100' }}">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</nav>

<div class="shrink-0 border-t border-gray-200 px-4 py-4">
    <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-teal text-sm font-bold text-white">
            {{ Str::of(auth()->user()->name ?? 'A')->substr(0, 1)->upper() }}
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold text-gray-900">{{ auth()->user()->name ?? 'Administrador' }}</span>
            <span class="block truncate text-xs text-gray-500">Administrador</span>
        </span>
    </div>
    <div class="mt-3 flex items-center justify-between gap-2">
        <a href="{{ route('home') }}" class="text-xs font-semibold text-gray-500 hover:text-brand-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
            Inicio
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-brand-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy/40">
                Cerrar sesión
            </button>
        </form>
    </div>
</div>
