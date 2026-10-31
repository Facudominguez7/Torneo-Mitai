<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="goleadores-pagina fixture-tablero">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main class="flex-1">
                <section class="goleadores-contenido">
                    <div class="goleadores-intro">
                        <div>
                            <span class="goleadores-rotulo">Estadísticas del torneo</span>
                            <h1>Tabla de goleadores</h1>
                            <p>Los goles de cada categoría, en una sola tabla.</p>
                        </div>
                        <div class="goleadores-edicion">
                            @if ($EdicionSeleccionada)
                                <span class="goleadores-rotulo">Edición</span>
                                <strong>{{ $EdicionSeleccionada->nombre }}</strong>
                            @endif
                            @if ($goleadores_t->isNotEmpty() && $goleadores_t->first()->updated_at)
                                @php
                                    $ultimaActualizacion = $goleadores_t->max('updated_at');
                                    $diasAbreviados = [
                                        'Sun' => 'Dom.',
                                        'Mon' => 'Lun.',
                                        'Tue' => 'Mar.',
                                        'Wed' => 'Mie.',
                                        'Thu' => 'Jue.',
                                        'Fri' => 'Vie.',
                                        'Sat' => 'Sab.',
                                    ];
                                    $diaAbreviado = $diasAbreviados[$ultimaActualizacion->copy()->locale('en')->translatedFormat('D')] ?? $ultimaActualizacion->format('D').'.';
                                @endphp
                                <div class="goleadores-actualizacion">
                                    <i class="fa-solid fa-clock text-brand-gold/60"></i>
                                    Última actualización: {{ $diaAbreviado }} {{ $ultimaActualizacion->format('d/m/Y H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="goleadores-filtros">
                        <div id="goleadores-origen-categoria">
                            <form method="GET" action="{{ route('tabla-goleadores') }}" id="form-categoria-goleadores">
                                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                                <input type="hidden" name="search_value" value="{{ $searchValue }}">
                                <input type="hidden" name="sort" value="{{ $sortKey }}"><input type="hidden" name="dir" value="{{ $dir }}">
                                <label class="goleadores-rotulo" for="categoria-goleadores">Categoría</label>
                                <select id="categoria-goleadores" name="idCategoria">
                                    <option value="">Todas las categorías</option>
                                    @foreach ($categorias as $c)
                                        <option value="{{ $c->id }}" @selected((string) $c->id === (string) $idCategoria)>{{ $c->nombreCategoria }}</option>
                                    @endforeach
                                </select>
                                <noscript><button type="submit">Aplicar categoría</button></noscript>
                            </form>
                        </div>

                        <div id="goleadores-origen-buscar" class="goleadores-busqueda">
                            <form id="form-busqueda-goleadores" method="GET" action="{{ route('tabla-goleadores') }}"
                                >
                                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                                <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                                <input type="hidden" name="sort" value="{{ $sortKey }}">
                                <input type="hidden" name="dir" value="{{ $dir }}">
                                <label for="buscador-goleadores" class="goleadores-rotulo">Buscar jugador</label>
                                    <input id="buscador-goleadores" name="search_value" type="text"
                                        value="{{ $searchValue }}" placeholder="Buscar por nombre..."
                                        >
                            </form>
                        </div>
                        <div id="goleadores-origen-orden"><form method="GET" action="{{ route('tabla-goleadores') }}" id="form-orden-goleadores">
                            <input type="hidden" name="idEdicion" value="{{ $idEdicion }}"><input type="hidden" name="idCategoria" value="{{ $idCategoria }}"><input type="hidden" name="search_value" value="{{ $searchValue }}">
                            <label class="goleadores-rotulo" for="orden-goleadores">Ordenar por</label>
                            <select id="orden-goleadores" name="orden">
                                @foreach (['goles' => 'Goles', 'nombre' => 'Nombre', 'equipo' => 'Equipo', 'categoria' => 'Categoría'] as $clave => $etiqueta)
                                    @foreach (['desc' => ($clave === 'goles' ? 'mayor a menor' : 'Z a A'), 'asc' => ($clave === 'goles' ? 'menor a mayor' : 'A a Z')] as $direccion => $texto)
                                        <option value="{{ $clave }}:{{ $direccion }}" @selected($sortKey === $clave && $dir === $direccion)>{{ $etiqueta }} · {{ $texto }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <noscript><div class="goleadores-orden-alternativo">@foreach (['goles' => 'Goles', 'nombre' => 'Nombre', 'equipo' => 'Equipo', 'categoria' => 'Categoría'] as $clave => $etiqueta) @foreach (['asc' => 'ascendente', 'desc' => 'descendente'] as $direccion => $texto) <a href="{{ route('tabla-goleadores', array_filter(['idEdicion' => $idEdicion, 'idCategoria' => $idCategoria, 'search_value' => $searchValue, 'sort' => $clave, 'dir' => $direccion, 'page' => 1])) }}">{{ $etiqueta }} {{ $texto }}</a> @endforeach @endforeach</div></noscript>
                        </form></div>
                    </div>
                    <div id="goleadores-filtros-sentinel" aria-hidden="true"></div>
                    <div id="goleadores-compacto" class="goleadores-compacto goleadores-compacto-inicial-movil" hidden>
                        <div class="goleadores-compacto-interior">
                            <div class="goleadores-compacto-botones" role="group" aria-label="Filtros de goleadores">
                                <button type="button" aria-expanded="false" aria-controls="goleadores-panel-categoria">Categoría ▾</button>
                                <button type="button" aria-expanded="false" aria-controls="goleadores-panel-orden">Orden ▾</button>
                                <button type="button" aria-expanded="false" aria-controls="goleadores-panel-buscar" aria-label="Buscar jugador"><svg width="20" height="20" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
                            </div>
                            <div id="goleadores-panel-categoria" class="goleadores-compacto-panel" hidden></div>
                            <div id="goleadores-panel-orden" class="goleadores-compacto-panel" hidden></div>
                            <div id="goleadores-panel-buscar" class="goleadores-compacto-panel" hidden></div>
                        </div>
                    </div>
                    <p class="goleadores-nota">Filtrá por categoría o buscá por nombre para encontrar un jugador.</p>

                    <div id="estado-goleadores" class="fixed inset-x-4 top-4 z-50 md:static md:inset-auto md:mt-6">
                        <span id="estado-carga-anuncio" role="status" aria-live="polite" aria-atomic="true"
                            class="sr-only"></span>
                        <p id="estado-carga-goleadores" class="hidden">
                            <span
                                class="flex items-center gap-3 rounded-2xl border border-brand-gold/30 bg-black/90 px-4 py-3 text-sm font-semibold text-white/80 shadow-xl backdrop-blur-md">
                                <i class="fa-solid fa-spinner fa-spin text-brand-gold"></i>
                                Actualizando goleadores…
                            </span>
                        </p>
                        <div id="estado-error-goleadores" role="alert"
                            class="hidden rounded-2xl border border-red-400/40 bg-red-950/95 px-4 py-4 text-sm text-red-100 shadow-xl backdrop-blur-md">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <span class="font-semibold">
                                    No pudimos actualizar el listado. Verificá tu conexión e intentá nuevamente.
                                </span>
                                <button type="button" id="reintentar-goleadores"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl border border-red-300/40 bg-red-500/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-500/30">
                                    <i class="fa-solid fa-rotate-right"></i>
                                    Reintentar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="resultados-goleadores" aria-busy="false">
                        @include('partials.goleadores-resultados')
                    </div>
                </section>
            </main>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
            var url = "{{ route('tabla-goleadores') }}";

            const buscador = document.getElementById('buscador-goleadores');
            const contenedor = document.getElementById('resultados-goleadores');
            const formBusqueda = document.getElementById('form-busqueda-goleadores');
            const categoria = document.getElementById('categoria-goleadores');
            const orden = document.getElementById('orden-goleadores');
            const estadoCarga = document.getElementById('estado-carga-goleadores');
            const estadoCargaAnuncio = document.getElementById('estado-carga-anuncio');
            const estadoError = document.getElementById('estado-error-goleadores');
            const botonReintentar = document.getElementById('reintentar-goleadores');
            let secuenciaCargas = 0;
            let ultimoIntento = null;

            const compacta = document.getElementById('goleadores-compacto');
            const sentinel = document.getElementById('goleadores-filtros-sentinel');
            const navegacion = document.getElementById('nav-principal');
            const movil = window.matchMedia('(max-width: 639px)');
            const controlesCompactos = [...compacta.querySelectorAll('[aria-controls]')].map(function (boton) {
                const nombre = boton.getAttribute('aria-controls').replace('goleadores-panel-', '');
                const origen = document.getElementById('goleadores-origen-' + nombre);
                return { boton, panel: document.getElementById(boton.getAttribute('aria-controls')), origen, formulario: origen.querySelector('form') };
            });

            function cerrarCompacta() {
                controlesCompactos.forEach(function ({ boton, panel, origen, formulario }) {
                    origen.append(formulario);
                    boton.setAttribute('aria-expanded', 'false');
                    panel.hidden = true;
                });
            }

            controlesCompactos.forEach(function ({ boton, panel, formulario }) {
                boton.addEventListener('click', function () {
                    const abrir = panel.hidden;
                    cerrarCompacta();
                    if (abrir) {
                        panel.append(formulario);
                        panel.hidden = false;
                        boton.setAttribute('aria-expanded', 'true');
                        formulario.querySelector('select, input:not([type="hidden"])').focus({ preventScroll: true });
                    }
                });
            });

            function enfocarBuscador() {
                const control = controlesCompactos.find(function (item) { return item.formulario === formBusqueda; });
                if (!compacta.hidden && control.panel.hidden) {
                    cerrarCompacta();
                    control.panel.append(formBusqueda);
                    control.panel.hidden = false;
                    control.boton.setAttribute('aria-expanded', 'true');
                }
                buscador.focus({ preventScroll: true });
            }

            function actualizarCompacta() {
                const alturaNav = movil.matches ? 0 : Math.max(0, navegacion.getBoundingClientRect().bottom);
                compacta.style.setProperty('--goleadores-nav-altura', alturaNav + 'px');
                const fijar = sentinel.getBoundingClientRect().bottom <= alturaNav;
                const mostrar = movil.matches || fijar;
                compacta.classList.toggle('goleadores-compacto-fijo', fijar);
                if (!mostrar && !compacta.hidden) {
                    const enfocado = document.activeElement;
                    const control = controlesCompactos.find(function (item) { return item.formulario.contains(enfocado); });
                    const boton = compacta.contains(enfocado);
                    cerrarCompacta();
                    if (control) enfocado.focus({ preventScroll: true });
                    else if (boton) categoria.focus({ preventScroll: true });
                }
                compacta.hidden = !mostrar;
            }
            window.addEventListener('scroll', actualizarCompacta, { passive: true });
            window.addEventListener('resize', actualizarCompacta);
            if ('ResizeObserver' in window) new ResizeObserver(actualizarCompacta).observe(navegacion);
            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') return;
                const activo = controlesCompactos.find(function (item) { return !item.panel.hidden; });
                if (activo) { cerrarCompacta(); activo.boton.focus({ preventScroll: true }); }
            });
            document.body.classList.add('goleadores-js');
            actualizarCompacta();

            function establecerEstado(estado) {
                if (contenedor) {
                    contenedor.setAttribute('aria-busy', estado === 'cargando' ? 'true' : 'false');
                }

                if (estadoCarga) {
                    estadoCarga.classList.toggle('hidden', estado !== 'cargando');
                }

                if (estadoError) {
                    estadoError.classList.toggle('hidden', estado !== 'error');
                }

                // La región viva vive siempre en el árbol (sr-only, sin display:none) y el
                // texto se inserta recién al iniciar cada carga: alternar display con texto
                // estático no anuncia de forma confiable.
                if (estadoCargaAnuncio) {
                    estadoCargaAnuncio.textContent = estado === 'cargando' ? 'Actualizando goleadores…' : '';
                }
            }

            async function fetchResultados(destino, desdeHistorial, focoPosterior) {
                const miTurno = ++secuenciaCargas;

                ultimoIntento = { destino, desdeHistorial };
                establecerEstado('cargando');

                try {
                    const response = await fetch(destino, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    // Si una carga más nueva empezó mientras esta esperaba, esta respuesta
                    // llegó tarde: se descarta entera para no pisar tabla, estados ni historial.
                    if (miTurno !== secuenciaCargas) {
                        return;
                    }

                    if (!response.ok) {
                        establecerEstado('error');
                        return;
                    }

                    const html = await response.text();

                    if (miTurno !== secuenciaCargas) {
                        return;
                    }

                    contenedor.innerHTML = html;
                    establecerEstado('inactivo');

                    if (focoPosterior) {
                        if (focoPosterior === buscador) enfocarBuscador();
                        else focoPosterior.focus();
                    }

                    if (desdeHistorial) {
                        history.replaceState(null, '', destino);
                    } else {
                        history.pushState(null, '', destino);
                    }
                    const actual = new URL(destino, window.location.href).searchParams;
                    orden.value = (actual.get('sort') || 'goles') + ':' + (actual.get('dir') || 'desc');
                    document.querySelectorAll('#form-busqueda-goleadores input[type="hidden"], #form-categoria-goleadores input[type="hidden"], #form-orden-goleadores input[type="hidden"]').forEach(function (input) {
                        if (['idCategoria', 'search_value', 'sort', 'dir'].includes(input.name)) {
                            input.value = actual.get(input.name) || (input.name === 'sort' ? 'goles' : input.name === 'dir' ? 'desc' : '');
                        }
                    });
                } catch (error) {
                    if (miTurno !== secuenciaCargas) {
                        return;
                    }

                    establecerEstado('error');
                }
            }

            // Pide los resultados por AJAX y reemplaza solo el contenedor. El
            // estado vive en la URL: se parte de los filtros actuales y se aplican
            // encima solo los que cambian. Cada cambio apila una entrada de
            // historial, salvo que la navegación venga de popstate.
            async function loadResults(params, opciones = {}) {
                if (!contenedor) {
                    return;
                }

                let destino = opciones.destinoHref || null;

                if (!destino) {
                    const search = new URLSearchParams(window.location.search);

                    Object.entries(params ?? {}).forEach(function ([clave, valor]) {
                        if (valor === undefined || valor === null || valor === '') {
                            search.delete(clave);
                        } else {
                            search.set(clave, valor);
                        }
                    });

                    const qs = search.toString();
                    destino = url + (qs ? '?' + qs : '');
                }

                await fetchResultados(destino, opciones.desdeHistorial ?? false, opciones.focoPosterior ?? null);
            }

            // El reintento repite el destino exacto del intento fallido; la entrada de
            // historial se apila una sola vez, cuando la carga finalmente tiene éxito.
            if (botonReintentar) {
                botonReintentar.addEventListener('click', function () {
                    if (!ultimoIntento) {
                        return;
                    }

                    // El foco pasa a un control externo estable antes de reintentar: ocultar
                    // el error no debe dejar el foco caído en body.
                    if (buscador) {
                        enfocarBuscador();
                    }

                    fetchResultados(ultimoIntento.destino, ultimoIntento.desdeHistorial);
                });
            }

            if (buscador && formBusqueda) {
                // Se evita el submit nativo (que recargaría la página).
                formBusqueda.addEventListener('submit', function (event) {
                    event.preventDefault();
                });

                let debounceBusqueda = null;
                buscador.addEventListener('input', function () {
                    clearTimeout(debounceBusqueda);
                    debounceBusqueda = setTimeout(function () {
                        loadResults({ search_value: buscador.value, page: 1 });
                    }, 350);
                });
            }

            categoria.addEventListener('change', function () {
                loadResults({ idCategoria: categoria.value, idEdicion, page: 1 });
            });
            orden.addEventListener('change', function () {
                const [sort, dir] = orden.value.split(':');
                loadResults({ sort, dir, page: 1 });
            });

            // Delegación: los links de orden y de paginación viven dentro del
            // parcial que se reemplaza, así que se interceptan desde el padre.
            if (contenedor) {
                contenedor.addEventListener('click', function (event) {
                    const link = event.target.closest('a.orden-goleadores, a.limpiar-busqueda-goleadores, .pagination a, nav a');

                    if (!link || !contenedor.contains(link)) {
                        return;
                    }

                    event.preventDefault();

                    const esLimpieza = link.classList.contains('limpiar-busqueda-goleadores');

                    // El input vive fuera del parcial reemplazado: se sincroniza su valor
                    // al destino antes del AJAX y se le devuelve el foco tras el reemplazo.
                    if (esLimpieza && buscador) {
                        buscador.value = '';
                    }

                    loadResults(null, {
                        destinoHref: link.href,
                        focoPosterior: esLimpieza ? buscador : null,
                    });
                });
            }

            // Los controles de filtro viven fuera del contenedor que reemplaza
            // loadResults, así que al restaurar historial hay que realinearlos.
            function sincronizarControles() {
                const search = new URLSearchParams(window.location.search);

                if (buscador) {
                    buscador.value = search.get('search_value') || '';
                }

                categoria.value = search.get('idCategoria') || '';
                orden.value = (search.get('sort') || 'goles') + ':' + (search.get('dir') || 'desc');
            }

            // Atrás/Adelante: se repide el parcial con los filtros de la URL restaurada.
            window.addEventListener('popstate', function () {
                sincronizarControles();
                loadResults({}, { desdeHistorial: true });
            });
        });
    </script>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
</body>

</html>
