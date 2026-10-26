{{--
Vista Tabla de Vallas Menos Vencidas al Día

Muestra los equipos ordenados por menor cantidad de goles recibidos
en tiempo real. Considera goles de fase de grupos e instancias finales.
Datos obtenidos dinámicamente de equipo_ediciones.golesContra.
--}}
@section('tituloSeo', 'Tabla de Vallas | Torneo Mitai')
@section('descripcionSeo', 'Tabla completa de vallas menos vencidas por categoría y edición del Torneo Mitai')
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
                    {{-- Header --}}
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-3 max-w-3xl">
                            <span
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-emerald-300/80">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                                Ranking defensivo
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Vallas menos vencidas</h1>
                            <p class="text-sm sm:text-base text-white/70">
                                Ranking de equipos con menos goles recibidos en la edición seleccionada.
                                Incluye fase de grupos e instancias finales.
                            </p>
                        </div>
                        <div class="grid gap-3 text-sm text-white/70">
                            @if ($EdicionSeleccionada)
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                    <i class="fa-solid fa-shield text-emerald-300"></i>
                                    {{ $EdicionSeleccionada->nombre }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Filtros --}}
                    <div
                        class="mt-10 rounded-3xl border border-emerald-300/25 bg-black/30 px-5 py-6 shadow-[0_24px_60px_-40px_rgba(16,185,129,0.5)] sm:px-6 sm:py-7">
                        <div class="grid gap-4 md:items-center">
                            {{-- Filtro por categoría --}}
                            @if ($categorias->isNotEmpty())
                                <div class="filtro-categoria">
                                    <x-dropdown align="right" width="52">
                                        <x-slot name="trigger">
                                            <button
                                                class="inline-flex w-full items-center justify-between gap-2 rounded-2xl border border-emerald-300/40 bg-gradient-to-r from-emerald-800/90 via-emerald-700/85 to-emerald-800/85 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-emerald-300/40">
                                                <span class="truncate">
                                                    {{ optional($categorias->firstWhere('id', request('idCategoria')))->nombreCategoria ?? 'Filtrar por categoría' }}
                                                </span>
                                                <i class="fa-solid fa-chevron-down text-xs"></i>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <div class="max-h-72 overflow-y-auto">
                                                <div class="cursor-pointer px-4 py-2 text-sm text-white/80 transition hover:bg-emerald-300/15"
                                                    data-id="" data-nombre="Todas las categorías">
                                                    Todas las categorías
                                                </div>
                                                @foreach ($categorias as $c)
                                                    <div class="cursor-pointer px-4 py-2 text-sm text-white/90 transition hover:bg-emerald-300/15"
                                                        data-id="{{ $c->id }}" data-nombre="{{ $c->nombreCategoria }}">
                                                        {{ $c->nombreCategoria }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </x-slot>
                                    </x-dropdown>
                                </div>
                            @endif
                        </div>

                        {{-- Buscador --}}
                        <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-white/70">Buscar
                                    equipo</h2>
                                <p class="text-xs text-white/50">Filtrá al instante por nombre dentro de la tabla.</p>
                            </div>
                            <label for="buscador-equipos" class="relative w-full md:max-w-sm">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-emerald-300">
                                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                </span>
                                <input id="buscador-equipos" type="text" placeholder="Buscar por nombre..."
                                    class="w-full rounded-2xl border border-emerald-300/35 bg-white/5 px-4 py-3 pl-11 text-sm font-semibold text-white placeholder:text-white/45 focus:border-emerald-300/60 focus:outline-none focus:ring-2 focus:ring-emerald-300/40">
                            </label>
                        </div>
                    </div>

                    @php
                        $sort = request('sort', 'goles');
                        $dir = request('dir', 'asc');
                        $toggle = fn($k) => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
                        $arrow = fn($k) => $sort === $k ? ($dir === 'asc' ? '▲' : '▼') : '';
                        $queryBase = array_filter(request()->except(['page']));
                        $posOffset = ($equipos->currentPage() - 1) * $equipos->perPage();
                    @endphp

                    {{-- Tabla de Equipos --}}
                    <div class="mt-10 space-y-6">
                        @if ($equipos->isEmpty())
                            <div
                                class="rounded-3xl border border-emerald-300/25 bg-black/30 px-6 py-10 text-center text-lg font-semibold text-white/70">
                                No existen equipos registrados para la edición seleccionada.
                            </div>
                        @else
                            {{-- Vista Desktop --}}
                            <div class="hidden lg:block">
                                <div
                                    class="overflow-hidden rounded-3xl border border-emerald-300/25 bg-black/40 shadow-[0_28px_70px_-45px_rgba(16,185,129,0.6)]">
                                    <table class="w-full min-w-[720px] border-collapse">
                                        <thead class="bg-gradient-to-r from-emerald-800/90 to-emerald-700/90">
                                            <tr>
                                                <th
                                                    class="py-4 pl-6 pr-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    #</th>
                                                <th
                                                    class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-vallas', array_merge($queryBase, ['sort' => 'equipo', 'dir' => $toggle('equipo')])) }}">
                                                        Equipo <span>{{ $arrow('equipo') }}</span>
                                                    </a>
                                                </th>
                                                <th
                                                    class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-vallas', array_merge($queryBase, ['sort' => 'categoria', 'dir' => $toggle('categoria')])) }}">
                                                        Categoría <span>{{ $arrow('categoria') }}</span>
                                                    </a>
                                                </th>
                                                <th
                                                    class="py-4 pr-6 pl-3 text-right text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                                    <a class="inline-flex items-center gap-2 hover:text-white"
                                                        href="{{ route('tabla-vallas', array_merge($queryBase, ['sort' => 'goles', 'dir' => $toggle('goles')])) }}">
                                                        Goles en Contra <span>{{ $arrow('goles') }}</span>
                                                    </a>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            @foreach ($equipos as $index => $equipo)
                                                <tr class="fila-equipo transition hover:bg-white/5"
                                                    data-equipo="{{ $equipo->nombreEquipo }}">
                                                    <td class="py-4 pl-6 pr-3 text-sm font-semibold text-white/80">
                                                        {{ $posOffset + $index + 1 }}
                                                    </td>
                                                    <td class="py-4 px-3">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="h-10 w-10 overflow-hidden rounded-full border border-emerald-300/30 bg-white/10 p-1">
                                                                <img src="{{ asset('fotos/equipos/' . ($equipo->fotoEquipo ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                                    alt="{{ $equipo->nombreEquipo }}"
                                                                    class="h-full w-full object-contain"
                                                                    onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                            </div>
                                                            <span
                                                                class="text-sm font-semibold text-white">{{ $equipo->nombreEquipo }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="py-4 px-3 text-sm text-white/65">
                                                        {{ $equipo->nombreCategoria }}
                                                    </td>
                                                    <td class="py-4 pr-6 pl-3 text-right text-lg font-bold text-emerald-300">
                                                        {{ $equipo->golesContra }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Vista Mobile --}}
                            <div class="grid gap-5 lg:hidden">
                                @foreach ($equipos as $index => $equipo)
                                    <article
                                        class="card-equipo-movil rounded-3xl border border-emerald-300/25 bg-gradient-to-br from-emerald-800/90 via-emerald-700/85 to-emerald-800/90 p-5 shadow-[0_24px_60px_-40px_rgba(16,185,129,0.6)]"
                                        data-equipo="{{ $equipo->nombreEquipo }}">
                                        <div
                                            class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-white/60">
                                            <span>#{{ str_pad($posOffset + $index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                            <span>{{ $equipo->nombreCategoria }}</span>
                                        </div>
                                        <div class="mt-4 flex items-center gap-4">
                                            <div
                                                class="h-16 w-16 overflow-hidden rounded-full border border-emerald-300/30 bg-white/10 p-2">
                                                <img src="{{ asset('fotos/equipos/' . ($equipo->fotoEquipo ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                    alt="{{ $equipo->nombreEquipo }}" class="h-full w-full object-contain"
                                                    onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                            </div>
                                            <h3 class="text-lg font-bold text-white">{{ $equipo->nombreEquipo }}</h3>
                                        </div>
                                        <div class="mt-6 flex items-center justify-between">
                                            <span
                                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                                <i class="fa-solid fa-shield text-emerald-300"></i>
                                                Goles en Contra
                                            </span>
                                            <span class="text-2xl font-bold text-emerald-300">{{ $equipo->golesContra }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            {{-- Mensaje sin coincidencias --}}
                            <div id="sin-coincidencias"
                                class="hidden rounded-3xl border border-emerald-300/25 bg-black/20 px-6 py-8 text-center text-sm font-semibold text-white/70">
                                No encontramos equipos que coincidan con tu búsqueda en esta página.
                            </div>
                        @endif
                    </div>

                    {{-- Paginación --}}
                    <div class="mt-8 flex justify-center">
                        {{ $equipos->links() }}
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
            var url = "{{ route('tabla-vallas') }}";

            // Normalizar texto para búsqueda
            const normalizar = (valor) => valor
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/\p{Diacritic}/gu, '');

            const buscador = document.getElementById('buscador-equipos');
            const filas = Array.from(document.querySelectorAll('.fila-equipo'));
            const tarjetas = Array.from(document.querySelectorAll('.card-equipo-movil'));
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
                    const coincide = evaluar(fila.dataset.equipo || '');
                    fila.classList.toggle('hidden', !coincide);
                    if (coincide) {
                        coincidencias++;
                    }
                });

                tarjetas.forEach(function (card) {
                    const coincide = evaluar(card.dataset.equipo || '');
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
                var newUrl = new URL(url, window.location.origin);
                Object.keys(params).forEach(function (key) {
                    if (params[key]) {
                        newUrl.searchParams.set(key, params[key]);
                    } else {
                        newUrl.searchParams.delete(key);
                    }
                });
                window.location.href = newUrl;
            }

            // Event listeners para filtro de categoría
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