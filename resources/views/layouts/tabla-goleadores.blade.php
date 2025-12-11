@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0">
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
                                <div class="inline-flex items-center gap-2 text-xs text-white/50">
                                    <i class="fa-solid fa-clock text-[#f7f280]/60"></i>
                                    Última actualización: {{ $goleadores_t->max('updated_at')->diffForHumans() }}
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
                                            <span class="truncate">
                                                {{ optional($categorias->firstWhere('id', request('idCategoria')))->nombreCategoria ?? 'Filtrar por categoría' }}
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
                                <p class="text-xs text-white/50">Filtrá al instante por nombre dentro de la tabla.</p>
                            </div>
                            <label for="buscador-goleadores" class="relative w-full md:max-w-sm">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#f7f280]">
                                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                </span>
                                <input id="buscador-goleadores" type="text" placeholder="Buscar por nombre..."
                                    class="w-full rounded-2xl border border-[#f7f280]/35 bg-white/5 px-4 py-3 pl-11 text-sm font-semibold text-white placeholder:text-white/45 focus:border-[#f7f280]/60 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                            </label>
                        </div>
                    </div>

                    @php
                        $sort = request('sort', 'goles');
                        $dir = request('dir', $sort === 'goles' ? 'desc' : 'asc');
                        $toggle = fn($k) => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
                        $arrow = fn($k) => $sort === $k ? ($dir === 'asc' ? '▲' : '▼') : '';
                        $queryBase = array_filter(request()->except(['page']));
                        $posOffset = ($goleadores_t->currentPage() - 1) * $goleadores_t->perPage();
                    @endphp

                    <div class="mt-10 space-y-6">
                        @if ($goleadores_t->isEmpty())
                            <div
                                class="rounded-3xl border border-[#f7f280]/25 bg-black/30 px-6 py-10 text-center text-lg font-semibold text-white/70">
                                No existen registros para la combinación seleccionada.
                            </div>
                        @else
                            <div class="hidden lg:block">
                                <div
                                    class="overflow-hidden rounded-3xl border border-[#f7f280]/25 bg-black/40 shadow-[0_28px_70px_-45px_rgba(14,75,94,0.9)]">
                                    <table class="w-full min-w-[720px] border-collapse">
                                        <thead class="bg-gradient-to-r from-[#0e4b5e]/90 to-[#2a6d81]/90">
                                            <tr>
                                                <th
                                                    class="py-4 pl-6 pr-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    #</th>
                                                <th
                                                    class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-goleadores', array_merge($queryBase, ['sort' => 'nombre', 'dir' => $toggle('nombre')])) }}">
                                                        Nombre <span>{{ $arrow('nombre') }}</span>
                                                    </a>
                                                </th>
                                                <th
                                                    class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-goleadores', array_merge($queryBase, ['sort' => 'equipo', 'dir' => $toggle('equipo')])) }}">
                                                        Equipo <span>{{ $arrow('equipo') }}</span>
                                                    </a>
                                                </th>
                                                <th
                                                    class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-goleadores', array_merge($queryBase, ['sort' => 'categoria', 'dir' => $toggle('categoria')])) }}">
                                                        Categoría <span>{{ $arrow('categoria') }}</span>
                                                    </a>
                                                </th>
                                                <th
                                                    class="py-4 pr-6 pl-3 text-right text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-goleadores', array_merge($queryBase, ['sort' => 'goles', 'dir' => $toggle('goles')])) }}">
                                                        Goles <span>{{ $arrow('goles') }}</span>
                                                    </a>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            @foreach ($goleadores_t as $index => $g)
                                                <tr class="fila-goleador transition hover:bg-white/5"
                                                    data-jugador="{{ $g->nombreJugador }}">
                                                    <td class="py-4 pl-6 pr-3 text-sm font-semibold text-white/80">
                                                        {{ $posOffset + $index + 1 }}
                                                    </td>
                                                    <td class="py-4 px-3 text-sm font-semibold text-white">
                                                        {{ $g->nombreJugador }}
                                                    </td>
                                                    <td class="py-4 px-3 text-sm text-white/80">
                                                        {{ $g->nombreEquipo }}
                                                    </td>
                                                    <td class="py-4 px-3 text-sm text-white/65">
                                                        {{ $g->nombreCategoria }}
                                                    </td>
                                                    <td class="py-4 pr-6 pl-3 text-right text-lg font-bold text-[#f7f280]">
                                                        {{ $g->cantidadGoles }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="grid gap-5 lg:hidden">
                                @foreach ($goleadores_t as $index => $g)
                                    <article
                                        class="card-goleador-movil rounded-3xl border border-[#f7f280]/25 bg-gradient-to-br from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/90 p-5 shadow-[0_24px_60px_-40px_rgba(14,75,94,0.85)]"
                                        data-jugador="{{ $g->nombreJugador }}">
                                        <div
                                            class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-white/60">
                                            <span>#{{ str_pad($posOffset + $index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                            <span>{{ $g->nombreCategoria }}</span>
                                        </div>
                                        <div class="mt-4 space-y-2">
                                            <h3 class="text-lg font-bold text-white">{{ $g->nombreJugador }}</h3>
                                            <p class="text-sm text-white/70">{{ $g->nombreEquipo }}</p>
                                        </div>
                                        <div class="mt-6 flex items-center justify-between">
                                            <span
                                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                                <i class="fa-solid fa-medal text-[#f7f280]"></i>
                                                Goles
                                            </span>
                                            <span class="text-2xl font-bold text-[#f7f280]">{{ $g->cantidadGoles }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <div id="sin-coincidencias"
                                class="hidden rounded-3xl border border-[#f7f280]/25 bg-black/20 px-6 py-8 text-center text-sm font-semibold text-white/70">
                                No encontramos jugadores que coincidan con tu búsqueda en esta página.
                            </div>
                        @endif
                    </div>

                    <div class="mt-8 flex justify-center">
                        {{ $goleadores_t->links() }}
                    </div>
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var idEdicion = "{{ $EdicionSeleccionada->id ?? '' }}";
            var idCategoria = new URLSearchParams(window.location.search).get('idCategoria') || '';
            var url = "{{ route('tabla-goleadores') }}";

            const normalizar = (valor) => valor
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/\p{Diacritic}/gu, '');

            const buscador = document.getElementById('buscador-goleadores');
            const filas = Array.from(document.querySelectorAll('.fila-goleador'));
            const tarjetas = Array.from(document.querySelectorAll('.card-goleador-movil'));
            const mensajeSinCoincidencias = document.getElementById('sin-coincidencias');

            function aplicarFiltro(nombre) {
                const termino = normalizar(nombre.trim());
                let coincidencias = 0;

                const evaluar = (datasetNombre) => {
                    if (!termino) {
                        return true;
                    }
                    return normalizar(datasetNombre).includes(termino);
                };

                filas.forEach(function (fila) {
                    const coincide = evaluar(fila.dataset.jugador || '');
                    fila.classList.toggle('hidden', !coincide);
                    if (coincide) {
                        coincidencias++;
                    }
                });

                tarjetas.forEach(function (card) {
                    const coincide = evaluar(card.dataset.jugador || '');
                    card.classList.toggle('hidden', !coincide);
                    if (coincide) {
                        coincidencias++;
                    }
                });

                if (mensajeSinCoincidencias) {
                    if (termino && coincidencias === 0) {
                        mensajeSinCoincidencias.classList.remove('hidden');
                    } else {
                        mensajeSinCoincidencias.classList.add('hidden');
                    }
                }
            }

            if (buscador) {
                buscador.addEventListener('input', function (event) {
                    aplicarFiltro(event.target.value || '');
                });
            }

            function updateUrl(params) {
                var newUrl = new URL(url);
                Object.keys(params).forEach(function (key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });

                window.location.href = newUrl;
            }

            document.querySelectorAll('.filtro-categoria [data-id]').forEach(function (item) {
                item.addEventListener('click', function () {
                    idCategoria = this.getAttribute('data-id') || '';
                    updateUrl({
                        idCategoria,
                        idEdicion,
                        sort: new URLSearchParams(window.location.search).get('sort') || '',
                        dir: new URLSearchParams(window.location.search).get('dir') || ''
                    });
                });
            });

            aplicarFiltro('');
        });
    </script>
</body>