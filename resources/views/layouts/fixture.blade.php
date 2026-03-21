@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0">
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

                <div class="md:sticky md:top-0 z-30 border-y border-white/10 bg-black/75/ backdrop-blur">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="grid w-full gap-4 md:grid-cols-4 md:gap-3">
                                <div x-data="{ open: false }" class="filtro-categoria">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button
                                                class="inline-flex w-full items-center justify-between gap-2 rounded-2xl border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                                <span class="truncate">{{ $nombreCategoria ?? 'Seleccionar categoría' }}</span>
                                                <i class="fa-solid fa-chevron-down text-xs"></i>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            @foreach ($categorias as $c)
                                                <div class="cursor-pointer px-4 py-2 text-sm text-white/90 transition hover:bg-[#f7f280]/15" data-id="{{ $c->id }}" data-nombre="{{ $c->nombreCategoria }}">
                                                    {{ $c->nombreCategoria }}
                                                </div>
                                            @endforeach
                                        </x-slot>
                                    </x-dropdown>
                                </div>

                                <div x-data="{ open: false }" class="filtro-fecha hidden">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button
                                                class="inline-flex w-full items-center justify-between gap-2 rounded-2xl border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                                <span class="truncate">Filtrar por fecha</span>
                                                <i class="fa-solid fa-chevron-down text-xs"></i>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <div class="max-h-60 overflow-y-auto">
                                                @if (!is_null($fechas))
                                                    @foreach ($fechas as $f)
                                                        <div class="cursor-pointer px-4 py-2 text-sm text-white/90 transition hover:bg-[#f7f280]/15" data-id="{{ $f->id }}" data-nombre="{{ $f->nombre }}">
                                                            {{ $f->nombre }}
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </x-slot>
                                    </x-dropdown>
                                </div>

                                <div x-data="{ open: false }" class="filtro-estado">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button
                                                class="inline-flex w-full items-center justify-between gap-2 rounded-2xl border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                                <span class="truncate">Estado</span>
                                                <i class="fa-solid fa-chevron-down text-xs"></i>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            @php $estadoActual = request()->query('estado', 'todos'); @endphp
                                            @foreach (['todos' => 'Todos', 'proximos' => 'Próximos', 'finalizados' => 'Finalizados'] as $k => $label)
                                                <div class="cursor-pointer px-4 py-2 text-sm text-white/90 transition hover:bg-[#f7f280]/15 {{ $estadoActual === $k ? 'font-semibold text-white' : '' }}" data-estado="{{ $k }}">{{ $label }}</div>
                                            @endforeach
                                        </x-slot>
                                    </x-dropdown>
                                </div>

                                {{-- Buscador por nombre de equipo --}}
                                <div class="relative">
                                    <input type="text" id="buscar-equipo" placeholder="Buscar equipo..."
                                        class="w-full rounded-2xl border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-3 pl-10 text-sm font-semibold text-white placeholder-white/50 shadow-lg transition focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-white/60 text-sm"></i>
                                    <button type="button" id="limpiar-busqueda" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition hidden">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                @if (!is_null($grupos) && $grupos->isNotEmpty())
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12">
                        @foreach ($grupos as $g)
                            @php $partidosGrupo = $partidos->where('idGrupo', $g->id); @endphp
                            <section class="space-y-6">
                                <div class="flex flex-col gap-4 rounded-3xl border border-[#f7f280]/25 bg-gradient-to-br from-[#0e4b5e]/85 via-[#2a6d81]/82 to-[#0e4b5e]/85 px-6 py-5 shadow-[0_24px_60px_-40px_rgba(14,75,94,0.85)] sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-2xl font-bold text-white">{{ $g->nombre }}</h2>
                                        @if (!empty($g->categoria_nombre))
                                            <p class="text-sm text-white/60">{{ $g->categoria_nombre }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('tabla-posiciones', ['idCategoria' => $idCategoria ?: $g->idCategoria, 'idGrupo' => $g->id, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                                        class="inline-flex items-center justify-center gap-2 rounded-full border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                        Tabla de posiciones
                                        <i class="fa-solid fa-arrow-right text-[11px]"></i>
                                    </a>
                                </div>

                                @if ($partidosGrupo->isEmpty())
                                    <div class="rounded-3xl border border-[#f7f280]/25 bg-gradient-to-br from-[#0e4b5e]/80 via-[#2a6d81]/75 to-[#0e4b5e]/80 px-6 py-10 text-center text-sm font-semibold text-white/70">
                                        Sin partidos programados en este grupo.
                                    </div>
                                @else
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
                                @endif
                            </section>
                        @endforeach

                        {{-- Sección de Partidos Interzonales --}}
                        @php $partidosInterzonales = $partidos->whereNull('idGrupo'); @endphp
                        @if ($partidosInterzonales->isNotEmpty())
                            <section class="space-y-6">
                                <div class="flex flex-col gap-4 rounded-3xl border border-amber-400/40 bg-gradient-to-br from-amber-500/20 via-amber-600/15 to-amber-500/20 px-6 py-5 shadow-[0_24px_60px_-40px_rgba(245,158,11,0.5)] sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <div>
                                            <h2 class="text-2xl font-bold text-white">Partidos Interzonales</h2>
                                            <p class="text-sm text-amber-200/70">Enfrentamientos entre equipos de diferentes grupos</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    @foreach ($partidosInterzonales as $p)
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
        var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
        var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
        var idFecha = new URLSearchParams(window.location.search).get('idFecha') || '';
        var estado = new URLSearchParams(window.location.search).get('estado') || 'todos';
        var orden = new URLSearchParams(window.location.search).get('orden') || 'desc';
        var url = "{{ route('fixture') }}";

        window.scrollTo(0, 0);
        setTimeout(function() {
            window.scrollTo(0, 0);
        }, 100);

        // Mostrar u ocultar filtros
        function toggleFiltersVisibility() {
            const fechaFilterContainer = document.querySelector('.filtro-fecha');
            const estadoFilterContainer = document.querySelector('.filtro-estado');
            const ordenFilterContainer = null; // eliminado

            const categoriaFilterSelected = idCategoria.trim() !== '';

            // Mostrar el filtro de fecha solo si se ha seleccionado una categoría
            fechaFilterContainer?.classList.toggle('hidden', !(categoriaFilterSelected));
            // El filtro de estado SIEMPRE visible, incluso sin categoría
            estadoFilterContainer?.classList.remove('hidden');
            // (eliminado) filtro de orden
        }

        // Actualizar la URL con los filtros seleccionados
        function updateUrl(params) {
            var newUrl = new URL(url);
            Object.keys(params).forEach(function(key) {
                if (params[key]) {
                    newUrl.searchParams.set(key, params[key]);
                } else {
                    newUrl.searchParams.delete(key);
                }
            });

            // Remove idFecha from URL if idCategoria is changed
            if (params.idCategoria !== idCategoria) {
                newUrl.searchParams.delete('idFecha');
            }

            window.location.href = newUrl;
        }

        // (Eliminado) Filtro de Edición

        // Añadir eventos a cada filtro de "Categoria"
        document.querySelectorAll('.filtro-categoria [data-id]').forEach(function(item) {
            item.addEventListener('click', function() {
                idCategoria = this.getAttribute('data-id');
                document.querySelectorAll('.filtro-categoria [data-id]').forEach(function(i) {
                    i.classList.remove('active');
                });
                this.classList.add('active');
                toggleFiltersVisibility();
                updateUrl({
                    idCategoria,
                    idEdicion,
                    idFecha: '', // Clear idFecha when a new category is selected
                    estado,
                    orden
                });
            });
        });

        // Añadir eventos a cada filtro de "Fecha"
        document.querySelectorAll('.filtro-fecha [data-id]').forEach(function(item) {
            item.addEventListener('click', function() {
                idFecha = this.getAttribute('data-id');
                updateUrl({
                    idCategoria,
                    idEdicion,
                    idFecha,
                    estado,
                    orden
                });
            });
        });

        // Filtro estado
        document.querySelectorAll('.filtro-estado [data-estado]').forEach(function(item) {
            item.addEventListener('click', function() {
                estado = this.getAttribute('data-estado');
                updateUrl({ idCategoria, idEdicion, idFecha, estado, orden });
            });
        });

        // (eliminado) Filtro orden

        // Inicializar el estado de visibilidad de los filtros
        toggleFiltersVisibility();

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

        // Buscador de equipos en tiempo real
        const buscadorInput = document.getElementById('buscar-equipo');
        const btnLimpiar = document.getElementById('limpiar-busqueda');
        
        if (buscadorInput) {
            buscadorInput.addEventListener('input', function() {
                const termino = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.partido-card');
                const secciones = document.querySelectorAll('section.space-y-6');
                
                // Mostrar/ocultar botón limpiar
                if (btnLimpiar) {
                    btnLimpiar.classList.toggle('hidden', termino === '');
                }
                
                if (termino === '') {
                    // Mostrar todo si no hay búsqueda
                    cards.forEach(card => card.classList.remove('hidden'));
                    secciones.forEach(sec => sec.classList.remove('hidden'));
                    return;
                }
                
                // Filtrar tarjetas por nombre de equipo
                cards.forEach(card => {
                    const local = card.dataset.local || '';
                    const visitante = card.dataset.visitante || '';
                    const coincide = local.includes(termino) || visitante.includes(termino);
                    card.classList.toggle('hidden', !coincide);
                });
                
                // Ocultar secciones vacías (grupos sin partidos visibles)
                secciones.forEach(sec => {
                    const cardsVisibles = sec.querySelectorAll('.partido-card:not(.hidden)');
                    sec.classList.toggle('hidden', cardsVisibles.length === 0);
                });
            });
            
            // Botón para limpiar búsqueda
            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', function() {
                    buscadorInput.value = '';
                    buscadorInput.dispatchEvent(new Event('input'));
                    buscadorInput.focus();
                });
            }
        }
    });
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.scrollTo(0, 0);
            }
        });
    </script>
</body>
