@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                fetchpriority="high" decoding="async"
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
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Fixture</h1>
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

                {{-- Panel consolidado de filtros: fechas, categoria, estado y buscador --}}
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    @php
                        $estadoFiltro = $estado ?? 'todos';
                        $equipoBuscado = trim((string) ($equipo ?? ''));
                        $idFechaSolicitada = $idFecha ?? null;
                        $idFechaRelevanteVista = $idFechaRelevante ?? null;
                        $esVistaTodas = (string) $idFechaSolicitada === 'todas';
                        $parametrosBase = collect([
                            'idEdicion' => $idEdicion ?? null,
                            'idCategoria' => $idCategoria ?? null,
                        ])->filter(fn ($valor) => $valor !== null && $valor !== '')->all();

                        $armarUrlFixture = function (array $parametros): string {
                            $query = http_build_query($parametros);

                            return route('fixture').($query === '' ? '' : '?'.$query);
                        };

                        $clasesChip = function (bool $activa): string {
                            $clasesBase = 'inline-flex min-w-max items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f7f280]/40';

                            return $activa
                                ? $clasesBase.' border-[#f7f280] bg-[#f7f280]/20 text-[#f7f280]'
                                : $clasesBase.' border-white/20 bg-white/5 text-white/70 hover:border-[#f7f280]/40 hover:bg-[#f7f280]/10 hover:text-white';
                        };

                        $fechaEsActiva = function ($fecha) use ($idFechaSolicitada, $idFechaRelevanteVista): bool {
                            if ((string) ($idFechaSolicitada ?? '') === (string) $fecha->id) {
                                return true;
                            }

                            return $idFechaSolicitada === null
                                && $idFechaRelevanteVista !== null
                                && (int) $idFechaRelevanteVista === (int) $fecha->id;
                        };

                        $fechaEsRelevante = function ($fecha) use ($esVistaTodas, $idFechaRelevanteVista): bool {
                            return ! $esVistaTodas
                                && $idFechaRelevanteVista !== null
                                && (int) $idFechaRelevanteVista === (int) $fecha->id;
                        };

                        $parametrosChipTodas = $parametrosBase + ['estado' => $estadoFiltro, 'idFecha' => 'todas'];
                    @endphp
                    <div class="mt-8 rounded-3xl border border-[#f7f280]/25 bg-black/30 p-4 sm:p-5 space-y-3">
                        <div role="group" aria-label="Cambiar fecha" class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-x-visible [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <a href="{{ $armarUrlFixture($parametrosChipTodas) }}"
                                @if ($esVistaTodas) aria-current="page" @endif
                                class="{{ $clasesChip($esVistaTodas) }}">
                                Todas
                            </a>
                            @foreach (($fechas ?? collect())->reverse() as $fechaChip)
                                @php
                                    $activaChipFecha = $fechaEsActiva($fechaChip);
                                    $parametrosChipFecha = $parametrosBase + ['estado' => $estadoFiltro];
                                    if (! $fechaEsRelevante($fechaChip)) {
                                        $parametrosChipFecha['idFecha'] = $fechaChip->id;
                                    }
                                @endphp
                                <a href="{{ $armarUrlFixture($parametrosChipFecha) }}"
                                    @if ($activaChipFecha) aria-current="page" @endif
                                    class="{{ $clasesChip($activaChipFecha) }}">
                                    {{ $fechaChip->nombre }}
                                </a>
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div role="group" aria-label="Cambiar categoría" class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-x-visible [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                @foreach ($categorias ?? collect() as $categoriaChip)
                                    @php
                                        $activaChipCategoria = (string) ($idCategoria ?? '') === (string) $categoriaChip->id;
                                        $parametrosChipCategoria = collect([
                                            'idEdicion' => $idEdicion ?? null,
                                            'idCategoria' => $categoriaChip->id,
                                        ])->filter(fn ($valor) => $valor !== null && $valor !== '')->all();
                                    @endphp
                                    <a href="{{ $armarUrlFixture($parametrosChipCategoria) }}"
                                        @if ($activaChipCategoria) aria-current="page" @endif
                                        class="{{ $clasesChip($activaChipCategoria) }}">
                                        {{ $categoriaChip->nombreCategoria }}
                                    </a>
                                @endforeach
                            </div>
                            <form method="GET" action="{{ route('fixture') }}" class="flex items-center gap-2 lg:ml-auto">
                                <input type="hidden" name="idEdicion" value="{{ $idEdicion ?? '' }}">
                                @if (filled($idCategoria ?? null))
                                    <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                                @endif
                                <input type="hidden" name="estado" value="{{ $estadoFiltro }}">
                                <input type="search" name="equipo" value="{{ $equipoBuscado }}" placeholder="Buscar equipo..." aria-label="Buscar equipo por nombre" class="rounded-full border border-white/20 bg-white/5 px-4 py-2 text-sm text-white placeholder:text-white/40 focus:border-[#f7f280]/60 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40 w-full lg:w-48">
                            </form>
                        </div>
                    </div>
                </div>

                <div class="relative">
                @if (($idFecha ?? '') === 'todas' && $fechasNavegacion->isNotEmpty())
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
                                    <a href="{{ $armarUrlFixture($parametrosChipTodas) }}"
                                        @if ($esVistaTodas) aria-current="page" @endif
                                        class="inline-flex min-w-max items-center gap-2 rounded-full border px-4 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40 {{ $esVistaTodas ? 'border-[#f7f280]/45 bg-[#f7f280]/10 text-[#f7f280]' : 'border-white/15 bg-black/15 text-white/70 hover:border-[#f7f280]/40 hover:text-white' }}">
                                        <i class="fa-solid fa-layer-group text-xs"></i>
                                        Todas
                                    </a>

                                    @foreach ($fechasNavegacion->reverse() as $f)
                                        @php $activaChipBarra = $fechaEsActiva($f); @endphp
                                        <a href="#fecha-{{ $f->id }}"
                                            @if ($activaChipBarra) aria-current="page" @endif
                                            class="inline-flex min-w-max items-center gap-2 rounded-full border px-4 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40 {{ $activaChipBarra ? 'border-[#f7f280]/45 bg-[#f7f280]/10 text-[#f7f280]' : 'border-white/15 bg-black/15 text-white/70 hover:border-[#f7f280]/40 hover:text-white' }}">
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
                    <div id="fixture-contenido" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12">
                        @if (filled($equipoBuscado) && $partidos->isEmpty())
                            <p class="rounded-3xl border border-white/10 bg-white/5 px-6 py-10 text-center text-lg font-semibold text-white/70">
                                No se encontraron partidos para <span class="text-[#f7f280]">"{{ $equipoBuscado }}"</span>.
                                Probá con otro nombre o revisá los filtros de estado.
                            </p>
                        @endif
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
                                                            <x-tarjeta-partido :partido="$p" />
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
                                                        <x-tarjeta-partido :partido="$p" :interzonal="true" />
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



        inicializarBarraFechasPersistente();
        window.addEventListener('resize', sincronizarAlturaSpacer);

        // Con hash #fecha-N el browser salta solo al anchor tras el reload del self-heal.
        if (!window.location.hash) {
            window.scrollTo(0, 0);
            setTimeout(function() {
                window.scrollTo(0, 0);
                sincronizarAlturaSpacer();
            }, 100);
        } else {
            setTimeout(sincronizarAlturaSpacer, 100);
        }
    });
        // Self-heal de anchors legacy #fecha-N sin idFecha en la URL: completa el
        // parametro y recarga una sola vez para renderizar esa fecha server-side.
        (function () {
            const coincidencia = window.location.hash.match(/^#fecha-(\d+)$/);
            if (!coincidencia) {
                return;
            }
            const parametros = new URLSearchParams(window.location.search);
            if (parametros.has('idFecha')) {
                return;
            }
            parametros.set('idFecha', coincidencia[1]);
            window.history.replaceState(window.history.state, '', window.location.pathname + '?' + parametros.toString() + window.location.hash);
            window.location.reload();
        })();
    </script>
</body>
