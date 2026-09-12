@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div x-data="crearPlanillaPublica()" class="relative flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main class="flex-1">
                <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-3 max-w-2xl">
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-yellow-300/80">
                                <span class="h-2 w-2 rounded-full bg-yellow-300"></span>
                                Agenda oficial
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Fixture {{ $nombreCategoria }}</h1>
                            <p class="text-sm sm:text-base text-white/70">Explorá todos los partidos programados, filtrá por categoría, fecha o estado y no te pierdas ningún cruce de la {{ optional($EdicionSeleccionada)->nombre ?? 'vigente' }}.</p>
                        </div>
                        <div class="grid gap-3 text-sm text-white/70">
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/50">
                                <i class="fa-solid fa-calendar-days text-yellow-300"></i>
                                {{ $nombreFecha ?? 'Seleccioná una fecha' }}
                            </div>
                            <a href="{{ route('instancias-finales', ['idEdicion' => optional($EdicionSeleccionada)->id]) }}"
                                class="group inline-flex items-center justify-center gap-2 rounded-full border border-white/20 px-5 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/70 transition hover:border-yellow-300 hover:bg-yellow-300/10">
                                Ver instancias finales
                                <i class="fa-solid fa-arrow-up-right-from-square text-[11px] transition-transform group-hover:translate-x-1"></i>
                            </a>
                        </div>
                    </div>
                </section>

                @php
                    $partidosPorFecha = $partidos->groupBy('idFechas');
                    $fechasNavegacion = !is_null($fechas)
                        ? $fechas->filter(fn ($fecha) => $partidosPorFecha->has($fecha->id))->values()
                        : $partidosPorFecha->map(function ($partidosFecha, $fechaId) {
                            $primerPartido = $partidosFecha->first();

                            return (object) [
                                'id' => $fechaId,
                                'nombre' => optional($primerPartido?->fecha)->nombre ?? 'Fecha',
                            ];
                        })->values();
                @endphp

                <div class="relative">
                @if ($fechasNavegacion->isNotEmpty())
                    <div id="fixture-fechas-sentinel" aria-hidden="true" class="h-px w-full"></div>
                    <div id="fixture-fechas-spacer" class="hidden"></div>
                    <section id="fixture-fechas-bar" class="relative z-40 border-b border-white/10 bg-black/90 shadow-[0_18px_40px_-24px_rgba(0,0,0,0.85)] backdrop-blur-xl">
                        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Fechas</p>
                                    <p class="mt-1 text-sm text-white/65">Saltá rápido entre jornadas sin recorrer todo el fixture.</p>
                                </div>
                                @if ($nombreFecha)
                                    <span class="hidden sm:inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                                        <i class="fa-regular fa-calendar-days text-[#f7f280]"></i>
                                        {{ $nombreFecha }}
                                    </span>
                                @endif
                            </div>

                            <div class="-mx-4 mt-4 overflow-x-auto px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <div class="flex w-max gap-3 pb-1">
                                    <a href="#fixture-contenido"
                                        class="inline-flex min-w-max items-center gap-2 rounded-full border px-4 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40 border-[#f7f280]/45 bg-[#f7f280]/10 text-[#f7f280]">
                                        <i class="fa-solid fa-layer-group text-xs"></i>
                                        Todas
                                    </a>

                                    @foreach ($fechasNavegacion as $f)
                                        <a href="#fecha-{{ $f->id }}"
                                            class="inline-flex min-w-max items-center gap-2 rounded-full border border-white/15 bg-black/15 px-4 py-3 text-sm font-semibold text-white/70 transition hover:border-[#f7f280]/40 hover:text-white focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                            <i class="fa-regular fa-calendar text-xs"></i>
                                            {{ $f->nombre }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                @if (!is_null($grupos) && $grupos->isNotEmpty())
                    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8">
                        <div class="sm:hidden">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Acceso rápido</p>
                                    <p class="mt-1 text-sm font-semibold text-white/80">Tabla de posiciones por grupo</p>
                                </div>
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/15 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                                    <i class="fa-solid fa-table-list text-[#f7f280]"></i>
                                    {{ $grupos->count() }}
                                </span>
                            </div>

                            <div class="-mx-4 mt-4 overflow-x-auto px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <div class="flex w-max gap-3 pb-1">
                                    @foreach ($grupos as $g)
                                        <a href="{{ route('tabla-posiciones', ['idCategoria' => $idCategoria ?: $g->idCategoria, 'idGrupo' => $g->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                                            class="group inline-flex min-w-max items-center gap-3 rounded-2xl border border-[#f7f280]/35 bg-[#0e4b5e]/85 px-4 py-3 text-white transition hover:border-[#f7f280]/60 hover:bg-[#f7f280]/10 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                            <i class="fa-solid fa-ranking-star text-xs text-[#f7f280]"></i>
                                            <span class="flex flex-col text-left leading-tight">
                                                <span class="text-sm font-semibold">{{ $g->nombre }}</span>
                                                @if (!empty($g->categoria_nombre))
                                                    <span class="mt-1 text-[10px] font-medium uppercase tracking-[0.18em] text-white/55">{{ $g->categoria_nombre }}</span>
                                                @endif
                                            </span>
                                            <i class="fa-solid fa-arrow-right text-[10px] text-white/50 transition group-hover:translate-x-1 group-hover:text-[#f7f280]"></i>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="hidden rounded-3xl border border-[#f7f280]/25 bg-gradient-to-br from-[#0e4b5e]/80 via-[#2a6d81]/70 to-[#0e4b5e]/80 p-5 shadow-[0_24px_60px_-40px_rgba(14,75,94,0.85)] sm:block sm:p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="max-w-2xl space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Acceso rápido</p>
                                    <h2 class="text-xl font-bold text-white sm:text-2xl">Encontrá la tabla de posiciones sin perderte en el fixture</h2>
                                    <p class="text-sm text-white/70">Cada grupo tiene su acceso directo. Elegí uno y saltá a la tabla de posiciones al instante.</p>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white/60">
                                    <i class="fa-solid fa-table-list text-[#f7f280]"></i>
                                    {{ $grupos->count() }} {{ $grupos->count() === 1 ? 'grupo disponible' : 'grupos disponibles' }}
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                @foreach ($grupos as $g)
                                    <a href="{{ route('tabla-posiciones', ['idCategoria' => $idCategoria ?: $g->idCategoria, 'idGrupo' => $g->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                                        class="group inline-flex items-center gap-3 rounded-2xl border border-[#f7f280]/35 bg-black/15 px-4 py-3 text-sm font-semibold text-white transition hover:border-[#f7f280]/60 hover:bg-[#f7f280]/10 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#f7f280]/30 bg-[#f7f280]/10 text-[#f7f280]">
                                            <i class="fa-solid fa-ranking-star text-sm"></i>
                                        </span>
                                        <span class="flex flex-col text-left">
                                            <span>{{ $g->nombre }}</span>
                                            @if (!empty($g->categoria_nombre))
                                                <span class="text-xs font-medium uppercase tracking-[0.2em] text-white/55">{{ $g->categoria_nombre }}</span>
                                            @endif
                                        </span>
                                        <i class="fa-solid fa-arrow-right text-xs text-white/50 transition group-hover:translate-x-1 group-hover:text-[#f7f280]"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if (!is_null($grupos) && $grupos->isNotEmpty())
                    <div id="fixture-contenido" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12">
                        @foreach ($fechasNavegacion as $fechaNavegacion)
                            @php
                                $partidosFecha = $partidos->where('idFechas', $fechaNavegacion->id);
                                $partidosInterzonalesFecha = $partidosFecha->whereNull('idGrupo');
                            @endphp

                            @if ($partidosFecha->isNotEmpty())
                                <section id="fecha-{{ $fechaNavegacion->id }}" class="scroll-mt-28 space-y-8">
                                    <div class="flex flex-col gap-3 rounded-3xl border border-white/10 bg-white/5 px-6 py-5 shadow-[0_24px_60px_-40px_rgba(0,0,0,0.65)] sm:flex-row sm:items-center sm:justify-between">
                                        <div class="space-y-2">
                                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Jornada</p>
                                            <h2 class="text-2xl font-bold text-white sm:text-3xl">{{ $fechaNavegacion->nombre }}</h2>
                                        </div>
                                        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/55">
                                            <i class="fa-regular fa-futbol text-[#f7f280]"></i>
                                            {{ $partidosFecha->count() }} {{ $partidosFecha->count() === 1 ? 'partido' : 'partidos' }}
                                        </span>
                                    </div>

                                    @foreach ($grupos as $g)
                                        @php $partidosGrupo = $partidosFecha->where('idGrupo', $g->id); @endphp

                                        @if ($partidosGrupo->isNotEmpty())
                                            <section class="space-y-6">
                                                <div class="space-y-2 px-1">
                                                    <h3 class="text-2xl font-bold text-white">{{ $g->nombre }}</h3>
                                                    @if (!empty($g->categoria_nombre))
                                                        <p class="text-sm text-white/60">{{ $g->categoria_nombre }}</p>
                                                    @endif
                                                </div>

                                                <div class="space-y-6">
                                                    @foreach ($partidosGrupo as $p)
                                                        @php
                                                            $esFuturo = optional(\Carbon\Carbon::parse($p->horario_datetime))->isFuture();
                                                        @endphp
                                                        <article class="partido-card rounded-3xl border border-[#f7f280]/30 bg-gradient-to-br from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] px-6 py-6 text-white shadow-[0_24px_60px_-40px_rgba(14,75,94,0.75)] transition hover:border-[#f7f280]/45 md:shadow-[0_32px_80px_-48px_rgba(14,75,94,0.9)]" data-jugado="{{ (int) $p->jugado }}" data-horario="{{ \Carbon\Carbon::parse($p->horario_datetime)->toIso8601String() }}" data-local="{{ strtolower($p->nombre_local) }}" data-visitante="{{ strtolower($p->nombre_visitante) }}" id="partido-{{ $p->id }}">
                                                            @php
                                                                $statusLabel = 'Pendiente';
                                                                $statusClasses = 'bg-[#f7f280]/15 text-[#f7f280] border border-[#f7f280]/40';

                                                                if ($p->jugado) {
                                                                    $statusLabel = 'Finalizado';
                                                                    $statusClasses = 'bg-gradient-to-r from-[#2a6d81]/90 to-[#0e4b5e]/90 text-[#f7f280] border border-[#f7f280]/45';
                                                                } elseif ($esFuturo) {
                                                                    $statusLabel = 'Próximo';
                                                                    $statusClasses = 'bg-gradient-to-r from-[#0e4b5e]/70 to-[#2a6d81]/70 text-white border border-[#f7f280]/35';
                                                                }
                                                            @endphp

                                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                                <div class="text-sm font-semibold uppercase tracking-[0.3em] text-white/65">
                                                                    {{ $p->fecha->nombre }}
                                                                </div>
                                                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                                    <span class="inline-flex h-2 w-2 rounded-full bg-[#f7f280]"></span>
                                                                    {{ $statusLabel }}
                                                                </span>
                                                            </div>

                                                            <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4 md:hidden">
                                                                <div class="flex flex-col items-center gap-3 text-center">
                                                                    <div class="h-16 w-16 overflow-hidden rounded-full border border-white/20 bg-black/25 p-2.5">
                                                                        <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" alt="{{ $p->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                    </div>
                                                                    <h3 class="text-sm font-bold text-white/95">{{ $p->nombre_local }}</h3>
                                                                </div>
                                                                <div class="flex min-w-[80px] flex-col items-center gap-1 text-center">
                                                                    <span class="text-[11px] font-semibold uppercase tracking-[0.35em] text-white/60">vs</span>
                                                                    <span class="text-2xl font-bold text-white">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                                </div>
                                                                <div class="flex flex-col items-center gap-3 text-center">
                                                                    <div class="h-16 w-16 overflow-hidden rounded-full border border-white/20 bg-black/25 p-2.5">
                                                                        <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" alt="{{ $p->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                    </div>
                                                                    <h3 class="text-sm font-bold text-white/95">{{ $p->nombre_visitante }}</h3>
                                                                </div>
                                                            </div>

                                                            <div class="mt-6 hidden md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-6">
                                                                <div class="flex flex-col items-center gap-3 text-center">
                                                                    <div class="h-20 w-20 overflow-hidden rounded-full border border-white/20 bg-black/25 p-3">
                                                                        <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" alt="{{ $p->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                    </div>
                                                                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Local</p>
                                                                    <h3 class="text-lg font-bold text-white">{{ $p->nombre_local }}</h3>
                                                                </div>

                                                                <div class="flex flex-col items-center justify-center gap-1 text-center">
                                                                    <span class="text-xs font-semibold uppercase tracking-[0.35em] text-white/40">vs</span>
                                                                    <span class="text-3xl font-bold text-white md:text-4xl">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                                </div>

                                                                <div class="flex flex-col items-center gap-3 text-center">
                                                                    <div class="h-20 w-20 overflow-hidden rounded-full border border-white/20 bg-black/25 p-3">
                                                                        <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" alt="{{ $p->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                    </div>
                                                                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Visita</p>
                                                                    <h3 class="text-lg font-bold text-white">{{ $p->nombre_visitante }}</h3>
                                                                </div>
                                                            </div>

                                                            <div class="mt-6 flex flex-col gap-3 text-sm text-white/70 md:flex-row md:items-center md:justify-between md:text-white/60">
                                                                <div class="inline-flex items-center gap-2">
                                                                    <i class="fa-regular fa-clock text-yellow-300"></i>
                                                                    <span>{{ \Carbon\Carbon::parse($p->horario_datetime)->format('d/m/Y · H:i') }}</span>
                                                                </div>
                                                                <div class="inline-flex items-center gap-2">
                                                                    <i class="fa-solid fa-location-dot text-emerald-300"></i>
                                                                    <span>Cancha {{ $p->cancha }}</span>
                                                                </div>
                                                            </div>

                                                            @php
                                                                $publicToken = data_get($p, 'public_token');
                                                                $planillaPublicaDisponible = filled($publicToken);
                                                                $planillaPublicaUrl = $planillaPublicaDisponible
                                                                    ? route('planillas-publicas.show', [
                                                                        'tipoPartido' => 'partido',
                                                                        'publicToken' => $publicToken,
                                                                    ])
                                                                    : null;
                                                            @endphp

                                                            <div class="mt-5 flex justify-end">
                                                                @if ($planillaPublicaDisponible)
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex items-center gap-2 rounded-full border border-[#f7f280]/40 bg-black/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-[#f7f280] transition hover:bg-[#f7f280]/10 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40"
                                                                        x-on:click='abrirPlanilla(@json($planillaPublicaUrl))'
                                                                    >
                                                                        <i class="fa-regular fa-rectangle-list"></i>
                                                                        Ver planilla
                                                                    </button>
                                                                @else
                                                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white/45">
                                                                        <i class="fa-regular fa-rectangle-list"></i>
                                                                        Planilla no disponible
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif
                                    @endforeach

                                    @if ($partidosInterzonalesFecha->isNotEmpty())
                                        <section class="space-y-6">
                                            <div class="flex flex-col gap-4 rounded-3xl border border-amber-400/40 bg-gradient-to-br from-amber-500/20 via-amber-600/15 to-amber-500/20 px-6 py-5 shadow-[0_24px_60px_-40px_rgba(245,158,11,0.5)] sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex items-center gap-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    <div>
                                                        <h3 class="text-2xl font-bold text-white">Partidos Interzonales</h3>
                                                        <p class="text-sm text-amber-200/70">Enfrentamientos entre equipos de diferentes grupos</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="space-y-6">
                                                @foreach ($partidosInterzonalesFecha as $p)
                                                    @php
                                                        $esFuturo = optional(\Carbon\Carbon::parse($p->horario_datetime))->isFuture();
                                                    @endphp
                                                    <article class="partido-card rounded-3xl border border-amber-400/40 bg-gradient-to-br from-[#0e4b5e] via-amber-900/30 to-[#0e4b5e] px-6 py-6 text-white shadow-[0_24px_60px_-40px_rgba(245,158,11,0.5)] transition hover:border-amber-400/60 md:shadow-[0_32px_80px_-48px_rgba(245,158,11,0.6)]" data-jugado="{{ (int) $p->jugado }}" data-horario="{{ \Carbon\Carbon::parse($p->horario_datetime)->toIso8601String() }}" data-local="{{ strtolower($p->nombre_local) }}" data-visitante="{{ strtolower($p->nombre_visitante) }}" id="partido-{{ $p->id }}">
                                                        @php
                                                            $statusLabel = 'Pendiente';
                                                            $statusClasses = 'bg-amber-500/20 text-amber-300 border border-amber-400/50';

                                                            if ($p->jugado) {
                                                                $statusLabel = 'Finalizado';
                                                                $statusClasses = 'bg-gradient-to-r from-amber-600/30 to-amber-700/30 text-amber-200 border border-amber-400/60';
                                                            } elseif ($esFuturo) {
                                                                $statusLabel = 'Próximo';
                                                                $statusClasses = 'bg-gradient-to-r from-amber-500/15 to-amber-600/15 text-white border border-amber-400/40';
                                                            }
                                                        @endphp

                                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                                            <div class="flex items-center gap-2">
                                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-1 text-xs font-semibold text-amber-300 border border-amber-400/40">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                    </svg>
                                                                    Interzonal
                                                                </span>
                                                                <span class="text-sm font-semibold uppercase tracking-[0.3em] text-white/65">
                                                                    {{ $p->fecha->nombre }}
                                                                </span>
                                                            </div>
                                                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                                <span class="inline-flex h-2 w-2 rounded-full bg-amber-400"></span>
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </div>

                                                        <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4 md:hidden">
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-16 w-16 overflow-hidden rounded-full border border-amber-400/30 bg-black/25 p-2.5">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" alt="{{ $p->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <h3 class="text-sm font-bold text-white/95">{{ $p->nombre_local }}</h3>
                                                            </div>
                                                            <div class="flex min-w-[80px] flex-col items-center gap-1 text-center">
                                                                <span class="text-[11px] font-semibold uppercase tracking-[0.35em] text-white/60">vs</span>
                                                                <span class="text-2xl font-bold text-white">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                            </div>
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-16 w-16 overflow-hidden rounded-full border border-amber-400/30 bg-black/25 p-2.5">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" alt="{{ $p->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <h3 class="text-sm font-bold text-white/95">{{ $p->nombre_visitante }}</h3>
                                                            </div>
                                                        </div>

                                                        <div class="mt-6 hidden md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-6">
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-20 w-20 overflow-hidden rounded-full border border-amber-400/30 bg-black/25 p-3">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" alt="{{ $p->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Local</p>
                                                                <h3 class="text-lg font-bold text-white">{{ $p->nombre_local }}</h3>
                                                            </div>

                                                            <div class="flex flex-col items-center justify-center gap-1 text-center">
                                                                <span class="text-xs font-semibold uppercase tracking-[0.35em] text-white/40">vs</span>
                                                                <span class="text-3xl font-bold text-white md:text-4xl">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                            </div>

                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-20 w-20 overflow-hidden rounded-full border border-amber-400/30 bg-black/25 p-3">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" alt="{{ $p->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Visita</p>
                                                                <h3 class="text-lg font-bold text-white">{{ $p->nombre_visitante }}</h3>
                                                            </div>
                                                        </div>

                                                        <div class="mt-6 flex flex-col gap-3 text-sm text-white/70 md:flex-row md:items-center md:justify-between md:text-white/60">
                                                            <div class="inline-flex items-center gap-2">
                                                                <i class="fa-regular fa-clock text-amber-300"></i>
                                                                <span>{{ \Carbon\Carbon::parse($p->horario_datetime)->format('d/m/Y · H:i') }}</span>
                                                            </div>
                                                            <div class="inline-flex items-center gap-2">
                                                                <i class="fa-solid fa-location-dot text-emerald-300"></i>
                                                                <span>Cancha {{ $p->cancha }}</span>
                                                            </div>
                                                        </div>

                                                        @php
                                                            $publicToken = data_get($p, 'public_token');
                                                            $planillaPublicaDisponible = filled($publicToken);
                                                            $planillaPublicaUrl = $planillaPublicaDisponible
                                                                ? route('planillas-publicas.show', [
                                                                    'tipoPartido' => 'partido',
                                                                    'publicToken' => $publicToken,
                                                                ])
                                                                : null;
                                                        @endphp

                                                        <div class="mt-5 flex justify-end">
                                                            @if ($planillaPublicaDisponible)
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex items-center gap-2 rounded-full border border-amber-400/45 bg-black/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-amber-300 transition hover:bg-amber-400/10 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                                                    x-on:click='abrirPlanilla(@json($planillaPublicaUrl))'
                                                                >
                                                                    <i class="fa-regular fa-rectangle-list"></i>
                                                                    Ver planilla
                                                                </button>
                                                            @else
                                                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white/45">
                                                                    <i class="fa-regular fa-rectangle-list"></i>
                                                                    Planilla no disponible
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </article>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif
                                </section>
                            @endif
                        @endforeach
                    </div>
                </div>
                @else
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                        <div class="rounded-3xl border border-white/10 bg-white/5 px-6 py-12 text-center text-lg font-semibold text-white/70">
                            @if (is_null($grupos))
                                Elegí una categoría en los filtros para ver los partidos disponibles.
                            @elseif ($grupos->isEmpty())
                                No hay grupos cargados para la selección actual.
                            @else
                                No hay partidos cargados para la selección actual.
                            @endif
                        </div>
                    </div>
                @endif
            </main>

            @include('layouts.footer')

            <x-planilla-publica-modal />
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const barraFechas = document.getElementById('fixture-fechas-bar');
        const barraFechasSpacer = document.getElementById('fixture-fechas-spacer');
        const barraFechasSentinel = document.getElementById('fixture-fechas-sentinel');

        function fijarBarraFechas() {
            if (!barraFechas || !barraFechasSpacer) {
                return;
            }

            barraFechas.classList.remove('relative');
            barraFechas.classList.add('fixed', 'inset-x-0', 'top-0');
            barraFechasSpacer.style.height = `${barraFechas.offsetHeight}px`;
            barraFechasSpacer.classList.remove('hidden');
        }

        function soltarBarraFechas() {
            if (!barraFechas || !barraFechasSpacer) {
                return;
            }

            barraFechas.classList.remove('fixed', 'inset-x-0', 'top-0');
            barraFechas.classList.add('relative');
            barraFechasSpacer.style.height = '0px';
            barraFechasSpacer.classList.add('hidden');
        }

        function sincronizarAlturaSpacer() {
            if (!barraFechas || !barraFechasSpacer || barraFechasSpacer.classList.contains('hidden')) {
                return;
            }

            barraFechasSpacer.style.height = `${barraFechas.offsetHeight}px`;
        }

        function inicializarBarraFechasPersistente() {
            if (!barraFechas || !barraFechasSpacer || !barraFechasSentinel) {
                return;
            }

            const observer = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        soltarBarraFechas();
                        return;
                    }

                    fijarBarraFechas();
                },
                {
                    root: null,
                    threshold: 0,
                }
            );

            observer.observe(barraFechasSentinel);
        }

        window.scrollTo(0, 0);
        setTimeout(function() {
            window.scrollTo(0, 0);
            sincronizarAlturaSpacer();
        }, 100);

        inicializarBarraFechasPersistente();
        window.addEventListener('resize', sincronizarAlturaSpacer);

        // Scroll automático al próximo partido pendiente
        try {
            const now = new Date();
            const cards = Array.from(document.querySelectorAll('.partido-card'));
            const proximos = cards
                .filter(c => c.dataset.jugado === '0')
                .map(c => ({ el: c, dt: new Date(c.dataset.horario) }))
                .filter(x => x.dt.toString() !== 'Invalid Date' && x.dt >= now)
                .sort((a, b) => a.dt - b.dt);
            const objetivo = proximos.length ? proximos[0].el : (cards.find(c => c.dataset.jugado === '0') || null);
            if (objetivo) {
                objetivo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                objetivo.classList.add('ring-2','ring-blue-400','ring-offset-2','ring-offset-white');
                setTimeout(() => objetivo.classList.remove('ring-2','ring-blue-400','ring-offset-2','ring-offset-white'), 3000);
            }
        } catch (e) {}
    });
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.scrollTo(0, 0);
            }
        });
    </script>
</body>
