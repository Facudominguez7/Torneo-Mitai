@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main class="flex-1">
                <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-3 max-w-3xl">
                            <span
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-[#f7f280]/80">
                                <span class="h-2 w-2 rounded-full bg-[#f7f280]"></span>
                                Ranking oficial
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Tabla de goleadores</h1>
                            <p class="text-sm sm:text-base text-white/70">Explorá el listado completo de artilleros de
                                la edición seleccionada, filtrá por categoría y ordená por goles, nombre o equipo para
                                encontrar a tu jugador preferido.</p>
                        </div>
                        <div class="grid gap-3 text-sm text-white/70">
                            @if ($EdicionSeleccionada)
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                    <i class="fa-solid fa-trophy text-[#f7f280]"></i>
                                    {{ $EdicionSeleccionada->nombre }}
                                </div>
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
                                <div class="inline-flex items-center gap-2 text-xs text-white/50">
                                    <i class="fa-solid fa-clock text-[#f7f280]/60"></i>
                                    Última actualización: {{ $diaAbreviado }} {{ $ultimaActualizacion->format('d/m/Y H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div
                        class="mt-10 rounded-3xl border border-[#f7f280]/25 bg-black/30 px-5 py-6 shadow-[0_24px_60px_-40px_rgba(14,75,94,0.85)] sm:px-6 sm:py-7">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,260px)_auto] md:items-center">
                            <div class="filtro-categoria">
                                <x-dropdown align="right" width="52">
                                    <x-slot name="trigger">
                                        <button
                                            class="inline-flex w-full items-center justify-between gap-2 rounded-2xl border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                            <span id="label-categoria" class="truncate">
                                                {{ optional($categorias->firstWhere('id', $idCategoria))->nombreCategoria ?? 'Filtrar por categoría' }}
                                            </span>
                                            <i class="fa-solid fa-chevron-down text-xs"></i>
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <div class="max-h-72 overflow-y-auto">
                                            <div class="cursor-pointer px-4 py-2 text-sm text-white/80 transition hover:bg-[#f7f280]/15"
                                                data-id="" data-nombre="Todas las categorías">
                                                Todas las categorías
                                            </div>
                                            @foreach ($categorias as $c)
                                                <div class="cursor-pointer px-4 py-2 text-sm text-white/90 transition hover:bg-[#f7f280]/15"
                                                    data-id="{{ $c->id }}" data-nombre="{{ $c->nombreCategoria }}">
                                                    {{ $c->nombreCategoria }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-white/70">Buscar
                                    jugador</h2>
                                <p class="text-xs text-white/50">Buscá por nombre en toda la tabla con paginación.</p>
                            </div>
                            <form id="form-busqueda-goleadores" method="GET" action="{{ route('tabla-goleadores') }}"
                                class="w-full md:max-w-sm">
                                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                                <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                                <input type="hidden" name="sort" value="{{ $sortKey }}">
                                <input type="hidden" name="dir" value="{{ $dir }}">
                                <label for="buscador-goleadores" class="relative block">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#f7f280]">
                                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                    </span>
                                    <input id="buscador-goleadores" name="search_value" type="text"
                                        value="{{ $searchValue }}" placeholder="Buscar por nombre..."
                                        class="w-full rounded-2xl border border-[#f7f280]/35 bg-white/5 px-4 py-3 pl-11 text-sm font-semibold text-white placeholder:text-white/45 focus:border-[#f7f280]/60 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                </label>
                            </form>
                        </div>
                    </div>

                    <div id="resultados-goleadores">
                        @include('partials.goleadores-resultados')
                    </div>
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
            var url = "{{ route('tabla-goleadores') }}";

            const buscador = document.getElementById('buscador-goleadores');
            const contenedor = document.getElementById('resultados-goleadores');
            const formBusqueda = document.getElementById('form-busqueda-goleadores');
            const triggerCategoria = document.getElementById('label-categoria');
            let secuenciaCargas = 0;

            // Pide los resultados por AJAX y reemplaza solo el contenedor. El
            // estado vive en la URL: se parte de los filtros actuales y se aplican
            // encima solo los que cambian. Cada cambio apila una entrada de
            // historial, salvo que la navegación venga de popstate.
            async function loadResults(params, opciones = {}) {
                const miTurno = ++secuenciaCargas;

                if (!contenedor) {
                    return;
                }

                const search = new URLSearchParams(window.location.search);

                Object.entries(params).forEach(function ([clave, valor]) {
                    if (valor === undefined || valor === null || valor === '') {
                        search.delete(clave);
                    } else {
                        search.set(clave, valor);
                    }
                });

                const qs = search.toString();
                const destino = url + (qs ? '?' + qs : '');

                try {
                    const response = await fetch(destino, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const html = await response.text();

                    // Si una carga más nueva empezó mientras esta esperaba, esta respuesta
                    // llegó tarde: se descarta entera para no pisar tabla ni historial.
                    if (miTurno !== secuenciaCargas) {
                        return;
                    }

                    contenedor.innerHTML = html;

                    if (opciones.desdeHistorial) {
                        history.replaceState(null, '', destino);
                    } else {
                        history.pushState(null, '', destino);
                    }
                } catch (error) {
                    // Ante un fallo de red se deja el contenido actual intacto.
                }
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

            document.querySelectorAll('.filtro-categoria [data-id]').forEach(function (item) {
                item.addEventListener('click', function () {
                    const idCategoria = this.getAttribute('data-id') || '';

                    if (triggerCategoria) {
                        triggerCategoria.textContent = this.getAttribute('data-nombre') || 'Filtrar por categoría';
                    }

                    loadResults({ idCategoria, idEdicion, page: 1 });
                });
            });

            // Delegación: los links de orden y de paginación viven dentro del
            // parcial que se reemplaza, así que se interceptan desde el padre.
            if (contenedor) {
                contenedor.addEventListener('click', function (event) {
                    const link = event.target.closest('a.orden-goleadores, .pagination a, nav a');

                    if (!link || !contenedor.contains(link)) {
                        return;
                    }

                    event.preventDefault();

                    const params = Object.fromEntries(new URL(link.href).searchParams.entries());
                    loadResults(params);
                });
            }

            // Los controles de filtro viven fuera del contenedor que reemplaza
            // loadResults, así que al restaurar historial hay que realinearlos.
            function sincronizarControles() {
                const search = new URLSearchParams(window.location.search);

                if (buscador) {
                    buscador.value = search.get('search_value') || '';
                }

                if (triggerCategoria) {
                    const idCategoriaUrl = search.get('idCategoria') || '';
                    const opcion = Array.from(document.querySelectorAll('.filtro-categoria [data-id]'))
                        .find(function (item) {
                            return (item.getAttribute('data-id') || '') === idCategoriaUrl;
                        });

                    triggerCategoria.textContent = opcion
                        ? opcion.getAttribute('data-nombre')
                        : 'Filtrar por categoría';
                }
            }

            // Atrás/Adelante: se repide el parcial con los filtros de la URL restaurada.
            window.addEventListener('popstate', function () {
                sincronizarControles();
                loadResults({}, { desdeHistorial: true });
            });
        });
    </script>
</body>
