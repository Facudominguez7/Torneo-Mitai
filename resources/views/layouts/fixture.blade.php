<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="fixture-tablero bg-tema-fondo text-tema-tinta antialiased">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                fetchpriority="high" decoding="async"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div x-data="crearPlanillaPublica()" class="relative flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main class="fixture-estructura flex-1">
                <h1 class="sr-only">Fixture</h1>

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

                {{-- Panel consolidado de filtros: fechas, categoria y buscador --}}
                <div class="fixture-filtros">
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

                        // Goles marcados por fecha: solo con partidos ya cargados en la vista y sin
                        // filtros de estado ni de equipo, para no mostrar totales parciales.
                        $golesPorFecha = ($estadoFiltro === 'todos' && $equipoBuscado === '')
                            ? $partidos
                                ->filter(fn ($partido) => $partido->jugado && ! is_null($partido->golesEquipoLocal) && ! is_null($partido->golesEquipoVisitante))
                                ->groupBy('idFechas')
                                ->map(fn ($jugados) => (int) $jugados->sum(fn ($partido) => $partido->golesEquipoLocal + $partido->golesEquipoVisitante))
                            : collect();
                    @endphp
                    <div class="fixture-filtros-interior">
                        <nav aria-label="Cambiar fecha" class="fixture-timeline">
                            <ol class="fixture-timeline-lista">
                                <li>
                                    <a href="{{ $armarUrlFixture($parametrosChipTodas) }}"
                                        @if ($esVistaTodas) aria-current="page" @endif
                                        class="fixture-chip">
                                        Todas
                                    </a>
                                </li>
                                @foreach (($fechas ?? collect())->reverse() as $fechaChip)
                                    @php
                                        $activaChipFecha = $fechaEsActiva($fechaChip);
                                        $parametrosChipFecha = $parametrosBase + ['estado' => $estadoFiltro];
                                        if (! $fechaEsRelevante($fechaChip)) {
                                            $parametrosChipFecha['idFecha'] = $fechaChip->id;
                                        }
                                        $golesFecha = $golesPorFecha->get($fechaChip->id);
                                    @endphp
                                    <li>
                                        <a href="{{ $armarUrlFixture($parametrosChipFecha) }}"
                                            @if ($activaChipFecha) aria-current="page" @endif
                                            class="fixture-chip">
                                            {{ $fechaChip->nombre }}
                                            @if (! is_null($golesFecha))
                                                <span class="fixture-timeline-goles">{{ $golesFecha }} {{ $golesFecha === 1 ? 'gol' : 'goles' }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                        <div class="fixture-controles">
                            <div role="group" aria-label="Cambiar categoría" class="fixture-categorias">
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
                                        class="fixture-chip">
                                        {{ $categoriaChip->nombreCategoria }}
                                    </a>
                                @endforeach
                            </div>
                            <div id="fixture-buscador-origen"><form method="GET" action="{{ route('fixture') }}" class="fixture-buscador-form">
                                <input type="hidden" name="idEdicion" value="{{ $idEdicion ?? '' }}">
                                @if (filled($idCategoria ?? null))
                                    <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                                @endif
                                <input type="hidden" name="estado" value="{{ $estadoFiltro }}">
                                <label class="fixture-buscador">
                                    <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    <input type="search" name="equipo" value="{{ $equipoBuscado }}" placeholder="Buscar equipo..." aria-label="Buscar equipo por nombre">
                                </label>
                                <p id="fixture-busqueda-estado" role="status" aria-live="polite"></p>
                            </form></div>
                            <div id="fixture-filtros-sentinel" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>

                <div id="fixture-compacto" class="fixture-compacto" hidden>
                    <div class="fixture-compacto-interior">
                        <div class="fixture-compacto-botones" role="group" aria-label="Filtros del fixture">
                            <button type="button" aria-expanded="false" aria-controls="fixture-panel-fecha">{{ $esVistaTodas ? 'Todas las fechas' : ($nombreFecha ?: 'Fechas') }} ▾</button>
                            <button type="button" aria-expanded="false" aria-controls="fixture-panel-categoria">{{ optional(($categorias ?? collect())->firstWhere('id', $idCategoria ?? null))->nombreCategoria ?? 'Categorías' }} ▾</button>
                            <button type="button" aria-expanded="false" aria-controls="fixture-panel-buscar" aria-label="Buscar equipo"><svg width="20" height="20" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
                        </div>
                        <div id="fixture-panel-fecha" class="fixture-compacto-panel" hidden>
                            <nav aria-label="Cambiar fecha" class="fixture-timeline">
                                <ol class="fixture-timeline-lista">
                                    <li><a class="fixture-chip" href="{{ $armarUrlFixture($parametrosChipTodas) }}" @if ($esVistaTodas) aria-current="page" @endif>Todas</a></li>
                                    @foreach (($fechas ?? collect())->reverse() as $fechaChip)
                                        @php
                                            $parametrosFechaCompacta = $parametrosBase + ['estado' => $estadoFiltro];
                                            if (! $fechaEsRelevante($fechaChip)) {
                                                $parametrosFechaCompacta['idFecha'] = $fechaChip->id;
                                            }
                                        @endphp
                                        <li><a class="fixture-chip" href="{{ $armarUrlFixture($parametrosFechaCompacta) }}" @if ($fechaEsActiva($fechaChip)) aria-current="page" @endif>{{ $fechaChip->nombre }}</a></li>
                                    @endforeach
                                </ol>
                            </nav>
                            @if ($esVistaTodas && $fechasNavegacion->isNotEmpty())
                                <nav aria-label="Saltar a una fecha" class="fixture-timeline">
                                    <p class="fixture-kicker">Saltar a fecha</p>
                                    <div class="fixture-timeline-lista">
                                        @foreach ($fechasNavegacion->reverse() as $fechaSalto)
                                            <a class="fixture-chip" href="#fecha-{{ $fechaSalto->id }}">{{ $fechaSalto->nombre }}</a>
                                        @endforeach
                                    </div>
                                </nav>
                            @endif
                        </div>
                        <div id="fixture-panel-categoria" class="fixture-compacto-panel" hidden></div>
                        <div id="fixture-panel-buscar" class="fixture-compacto-panel" hidden></div>
                    </div>
                </div>
                <div class="fixture-resultados relative" aria-live="polite">
                @if (($idFecha ?? '') === 'todas' && $fechasNavegacion->isNotEmpty())
                    <div id="fixture-fechas-sentinel" aria-hidden="true" class="h-px w-full"></div>
                    <div id="fixture-fechas-spacer" class="hidden lg:hidden"></div>
                    <section id="fixture-fechas-bar" class="relative lg:hidden z-40 border-b border-tema-linea/15 bg-tema-fondo/95 backdrop-blur-xl">
                        <div class="py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="fixture-kicker">Fechas</p>
                                    <p class="mt-1 text-sm text-tema-dim/80">Saltá rápido entre jornadas sin recorrer todo el fixture.</p>
                                </div>
                                @if ($nombreFecha)
                                    <span class="hidden sm:inline-flex items-center gap-2 rounded-full border border-tema-linea/15 bg-tema-fila-bg px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-tema-dim/80">
                                        <i class="fa-regular fa-calendar-days fixture-oro"></i>
                                        {{ $nombreFecha }}
                                    </span>
                                @endif
                            </div>

                            <div class="-mx-4 mt-3 overflow-x-auto px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <div class="flex w-max gap-2 py-1">
                                    <a href="{{ $armarUrlFixture($parametrosChipTodas) }}"
                                        @if ($esVistaTodas) aria-current="page" @endif
                                        class="fixture-chip">
                                        <i class="fa-solid fa-layer-group text-xs"></i>
                                        Todas
                                    </a>

                                    @foreach ($fechasNavegacion->reverse() as $f)
                                        @php $activaChipBarra = $fechaEsActiva($f); @endphp
                                        <a href="#fecha-{{ $f->id }}"
                                            @if ($activaChipBarra) aria-current="page" @endif
                                            class="fixture-chip">
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
                    <div id="fixture-contenido" class="space-y-10">
                        @if (filled($equipoBuscado) && $partidos->isEmpty())
                            <p class="fixture-vacio">
                                No se encontraron partidos para <span class="fixture-oro">"{{ $equipoBuscado }}"</span>.
                                Probá con otro nombre o revisá los filtros de estado.
                            </p>
                        @endif
                        @foreach ($fechasNavegacion as $fechaNavegacion)
                            @php
                                $partidosFecha = $partidos->where('idFechas', $fechaNavegacion->id);
                                $partidosInterzonalesFecha = $partidosFecha->whereNull('idGrupo');

                                // El dia sale de los horarios cargados; sin horarios no se muestra fecha.
                                $diasFecha = $partidosFecha
                                    ->pluck('horario_datetime')
                                    ->filter()
                                    ->map(fn ($horario) => \Carbon\Carbon::parse($horario)->startOfDay())
                                    ->unique(fn ($dia) => $dia->toDateString())
                                    ->sort()
                                    ->values();
                                $textoDiaFecha = match (true) {
                                    $diasFecha->isEmpty() => null,
                                    $diasFecha->count() === 1 => ucfirst($diasFecha->first()->locale('es')->isoFormat('dddd D [de] MMMM')),
                                    default => $diasFecha->first()->locale('es')->isoFormat('D [de] MMMM').' al '.$diasFecha->last()->locale('es')->isoFormat('D [de] MMMM'),
                                };
                            @endphp

                            @if ($partidosFecha->isNotEmpty())
                                <section id="fecha-{{ $fechaNavegacion->id }}" class="fixture-fecha scroll-mt-28">
                                    <header class="fixture-fecha-cabecera">
                                        @if ($EdicionSeleccionada)
                                            <p class="fixture-kicker">{{ $EdicionSeleccionada->nombre }}</p>
                                        @endif
                                        <h2 class="fixture-fecha-titulo">{{ $fechaNavegacion->nombre }}@if ($textoDiaFecha) <span>· {{ $textoDiaFecha }}</span>@endif</h2>
                                        <p class="fixture-resumen">
                                            <span class="fixture-resumen-n">{{ $partidosFecha->count() }}</span>
                                            <span class="fixture-resumen-t">{{ $partidosFecha->count() === 1 ? 'partido' : 'partidos' }} en la fecha</span>
                                        </p>
                                    </header>

                                    @foreach ($grupos as $g)
                                        @php $partidosGrupo = $partidosFecha->where('idGrupo', $g->id); @endphp

                                        @if ($partidosGrupo->isNotEmpty())
                                            <section>
                                                <div class="fixture-grupo">
                                                    <h3>{{ $g->nombre }}</h3>
                                                    @if (!empty($g->categoria_nombre))
                                                        <span>{{ $g->categoria_nombre }}</span>
                                                    @endif
                                                </div>

                                                <div class="fixture-partidos">
                                                    @foreach ($partidosGrupo as $p)
                                                        <x-tarjeta-partido :partido="$p" :compacto="true" />
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif
                                    @endforeach

                                    @if ($partidosInterzonalesFecha->isNotEmpty())
                                        <section>
                                            <div class="fixture-grupo fixture-grupo--interzonal">
                                                <h3>Partidos Interzonales</h3>
                                                <span>Enfrentamientos entre equipos de diferentes grupos</span>
                                            </div>

                                            <div class="fixture-partidos">
                                                @foreach ($partidosInterzonalesFecha as $p)
                                                    <x-tarjeta-partido :partido="$p" :interzonal="true" :compacto="true" />
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif
                                </section>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="py-16">
                        <div class="fixture-vacio">
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
                </div>
            </main>


            <x-planilla-publica-modal />
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const compacta = document.getElementById('fixture-compacto');
        const filtros = document.querySelector('.fixture-filtros');
        const sentinel = document.getElementById('fixture-filtros-sentinel');
        const navegacion = document.getElementById('nav-principal');
        const estructura = document.querySelector('.fixture-estructura');
        const botonesCompactos = compacta.querySelector('.fixture-compacto-botones');
        const origen = document.getElementById('fixture-buscador-origen');
        const formulario = origen.querySelector('form');
        const buscar = formulario.querySelector('[name="equipo"]');
        const aviso = document.getElementById('fixture-busqueda-estado');
        const resultados = document.querySelector('.fixture-resultados');
        const movil = window.matchMedia('(max-width: 639px)');
        const controles = [...compacta.querySelectorAll('[aria-controls]')].map(boton => ({ boton, panel: document.getElementById(boton.getAttribute('aria-controls')) }));
        const panelFecha = document.getElementById('fixture-panel-fecha');
        const panelCategoria = document.getElementById('fixture-panel-categoria');
        panelCategoria.append(document.querySelector('.fixture-categorias').cloneNode(true));
        let ajusteFecha;
        function asegurarFechaVisible(fecha, inicio) {
            actualizarCompacta();
            const limite = compacta.hidden ? (movil.matches ? 0 : navegacion.getBoundingClientRect().bottom) : compacta.getBoundingClientRect().bottom;
            const diferencia = limite + 16 - fecha.getBoundingClientRect().top;
            if (diferencia > 0) window.scrollBy({ top: -diferencia, behavior: 'instant' });
            if (performance.now() - inicio < 500) ajusteFecha = requestAnimationFrame(() => asegurarFechaVisible(fecha, inicio));
        }
        panelFecha.addEventListener('click', event => {
            const enlace = event.target.closest('a[href^="#fecha-"]');
            if (!enlace) return;
            const fecha = document.getElementById(enlace.getAttribute('href').slice(1));
            cerrar();
            cancelAnimationFrame(ajusteFecha);
            if (fecha) ajusteFecha = requestAnimationFrame(() => asegurarFechaVisible(fecha, performance.now()));
        });
        window.addEventListener('wheel', () => cancelAnimationFrame(ajusteFecha), { passive: true });
        window.addEventListener('touchstart', () => cancelAnimationFrame(ajusteFecha), { passive: true });
        let temporizador;
        let solicitud;
        let version = 0;

        function cerrar() {
            origen.append(formulario);
            controles.forEach(({ boton, panel }) => { boton.setAttribute('aria-expanded', 'false'); panel.hidden = true; });
        }
        controles.forEach(({ boton, panel }) => boton.addEventListener('click', () => {
            const abrir = panel.hidden;
            cerrar();
            if (abrir) {
                panel.hidden = false;
                boton.setAttribute('aria-expanded', 'true');
                if (panel.id === 'fixture-panel-buscar') { panel.append(formulario); buscar.focus({ preventScroll: true }); }
            }
        }));
        function actualizarCompacta() {
            const alturaNav = movil.matches ? 0 : navegacion.getBoundingClientRect().bottom;
            compacta.style.setProperty('--fixture-nav-altura', `${alturaNav}px`);
            const mostrar = sentinel.getBoundingClientRect().bottom <= alturaNav;
            if (!mostrar && !compacta.hidden) {
                const focoBuscar = formulario.contains(document.activeElement);
                const focoCompacta = compacta.contains(document.activeElement);
                cerrar();
                if (focoBuscar) buscar.focus({ preventScroll: true });
                else if (focoCompacta) filtros.querySelector('a, input')?.focus({ preventScroll: true });
            }
            compacta.hidden = !mostrar;
            const estilo = getComputedStyle(compacta);
            const bordeYPadding = parseFloat(estilo.paddingTop) + parseFloat(estilo.paddingBottom)
                + parseFloat(estilo.borderTopWidth) + parseFloat(estilo.borderBottomWidth);
            const alturaBarra = mostrar ? botonesCompactos.getBoundingClientRect().height + bordeYPadding : 0;
            estructura.style.setProperty('--fixture-salto-altura', `${alturaNav + alturaBarra + 16}px`);
        }
        if ('ResizeObserver' in window) {
            const observadorAltura = new ResizeObserver(actualizarCompacta);
            observadorAltura.observe(navegacion);
            observadorAltura.observe(botonesCompactos);
        }
        window.addEventListener('scroll', actualizarCompacta, { passive: true });
        window.addEventListener('resize', actualizarCompacta);
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            const activo = controles.find(({ panel }) => !panel.hidden);
            if (activo) { cerrar(); activo.boton.focus(); }
        });
        actualizarCompacta();

        async function buscarEnServidor(url, guardarHistorial) {
            const actual = ++version;
            solicitud?.abort();
            solicitud = new AbortController();
            aviso.textContent = 'Buscando partidos…';
            try {
                const respuesta = await fetch(url, { signal: solicitud.signal, headers: { 'Accept': 'text/html' } });
                if (!respuesta.ok) throw new Error('Error de respuesta');
                const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                const nuevo = pagina.querySelector('.fixture-resultados');
                const nuevaLinea = pagina.querySelector('.fixture-filtros .fixture-timeline');
                const nuevoPanel = pagina.getElementById('fixture-panel-fecha');
                const nuevaCategoria = pagina.querySelector('.fixture-filtros .fixture-categorias');
                const nuevosBotones = pagina.querySelectorAll('.fixture-compacto-botones button');
                if (!nuevo || !nuevaLinea || !nuevoPanel || !nuevaCategoria || nuevosBotones.length !== controles.length || actual !== version) throw new Error('Respuesta inválida');
                const enlaceEnfocado = document.activeElement?.closest('#fixture-panel-fecha a, #fixture-panel-categoria a');
                const hrefEnfocado = enlaceEnfocado?.getAttribute('href');
                const panelEnfocado = enlaceEnfocado?.closest('.fixture-compacto-panel');
                document.querySelector('.fixture-filtros .fixture-timeline').replaceWith(nuevaLinea);
                document.querySelector('.fixture-filtros .fixture-categorias').replaceWith(nuevaCategoria);
                panelFecha.replaceChildren(...nuevoPanel.childNodes);
                panelCategoria.replaceChildren(nuevaCategoria.cloneNode(true));
                controles.slice(0, 2).forEach(({ boton }, indice) => { boton.textContent = nuevosBotones[indice].textContent; });
                if (hrefEnfocado && panelEnfocado) {
                    [...panelEnfocado.querySelectorAll('a')].find(enlace => enlace.getAttribute('href') === hrefEnfocado)?.focus({ preventScroll: true });
                }
                resultados.replaceChildren(...nuevo.childNodes);
                document.dispatchEvent(new Event('fixture-resultados-actualizados'));
                if (guardarHistorial) history.replaceState(history.state, '', respuesta.url);
                aviso.textContent = '';
            } catch (error) {
                if (error.name !== 'AbortError' && actual === version) aviso.textContent = 'No se pudo actualizar la búsqueda. Presioná Enter para volver a intentar.';
            }
        }
        buscar.addEventListener('input', () => {
            if (!movil.matches) return;
            clearTimeout(temporizador);
            version++;
            solicitud?.abort();
            aviso.textContent = 'Buscando partidos…';
            temporizador = setTimeout(() => {
                const url = new URL(formulario.action);
                url.search = new URLSearchParams(new FormData(formulario)).toString();
                buscarEnServidor(url, true);
            }, 300);
        });
        formulario.addEventListener('submit', () => { clearTimeout(temporizador); solicitud?.abort(); });
        window.addEventListener('popstate', () => { window.location.reload(); });
    });
    document.addEventListener('DOMContentLoaded', function() {
        let barraFechas;
        let barraFechasSpacer;
        let barraFechasSentinel;
        let observer;

        function fijarBarraFechas() {
            if (!barraFechas || !barraFechasSpacer || document.getElementById('fixture-compacto')) {
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
            observer?.disconnect();
            barraFechas = document.getElementById('fixture-fechas-bar');
            barraFechasSpacer = document.getElementById('fixture-fechas-spacer');
            barraFechasSentinel = document.getElementById('fixture-fechas-sentinel');
            if (!barraFechas || !barraFechasSpacer || !barraFechasSentinel || !('IntersectionObserver' in window)) {
                return;
            }

            observer = new IntersectionObserver(
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
        document.addEventListener('fixture-resultados-actualizados', inicializarBarraFechasPersistente);
        window.addEventListener('resize', function () {
            soltarBarraFechas();
            inicializarBarraFechasPersistente();
            sincronizarAlturaSpacer();
        });

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
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
</body>

</html>
