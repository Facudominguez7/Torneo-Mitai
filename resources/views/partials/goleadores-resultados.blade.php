@php
    $sort = $sortKey;
    $toggle = fn($k) => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = fn($k) => $sort === $k ? ($dir === 'asc' ? '▲' : '▼') : '';
    $posOffset = ($goleadores_t->currentPage() - 1) * $goleadores_t->perPage();

    $queryBase = array_filter([
        'idEdicion' => $idEdicion ?? null,
        'idCategoria' => $idCategoria ?? null,
        'search_value' => $searchValue ?? null,
    ]);
    $urlOrden = fn($k) => route('tabla-goleadores', array_merge($queryBase, ['sort' => $k, 'dir' => $toggle($k), 'page' => 1]));
    $urlLimpiarBusqueda = route('tabla-goleadores', array_filter([
        'idEdicion' => $idEdicion ?? null,
        'idCategoria' => $idCategoria ?? null,
        'sort' => $sort,
        'dir' => $dir,
        'page' => 1,
    ]));

    // El podio real solo existe en el ranking canónico (goles desc) de la
    // primera página; cualquier otro orden o página lista filas compactas.
    $esRankingCanonica = $sort === 'goles' && $dir === 'desc' && $goleadores_t->currentPage() === 1;
    $itemsGoleadores = collect($goleadores_t->items());
    $podio = $esRankingCanonica ? $itemsGoleadores->take(3) : collect();
    $resto = $esRankingCanonica ? $itemsGoleadores->slice(3)->values() : $itemsGoleadores;
    $cantidadPodio = $podio->count();
@endphp

<div class="mt-10 space-y-6">
    @if ($goleadores_t->isEmpty())
        @if ($searchValue !== '')
            <div data-estado-vacio="busqueda"
                class="rounded-3xl border border-brand-gold/25 bg-black/30 px-6 py-10 text-center">
                <p class="text-lg font-semibold text-white/70">
                    No encontramos goleadores para «{{ $searchValue }}».
                </p>
                <a href="{{ $urlLimpiarBusqueda }}"
                    class="limpiar-busqueda-goleadores mt-6 inline-flex items-center gap-2 rounded-2xl border border-brand-gold/40 bg-gradient-to-r from-brand-navy/90 via-brand-teal/85 to-brand-navy/85 px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                    <i class="fa-solid fa-eraser"></i>
                    Limpiar búsqueda
                </a>
            </div>
        @else
            <div data-estado-vacio="filtros"
                class="rounded-3xl border border-brand-gold/25 bg-black/30 px-6 py-10 text-center text-lg font-semibold text-white/70">
                No existen registros para la combinación seleccionada.
            </div>
        @endif
    @else
        <div class="hidden lg:block">
            <div
                class="overflow-hidden rounded-3xl border border-brand-gold/25 bg-black/40 shadow-[0_28px_70px_-45px_rgba(var(--brand-navy)_/_0.9)]">
                <table class="w-full min-w-[720px] border-collapse">
                    <thead class="bg-gradient-to-r from-brand-navy/90 to-brand-teal/90">
                        <tr>
                            <th
                                class="py-4 pl-6 pr-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                #</th>
                            <th
                                class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ $urlOrden('nombre') }}">
                                    Nombre <span>{{ $arrow('nombre') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ $urlOrden('equipo') }}">
                                    Equipo <span>{{ $arrow('equipo') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ $urlOrden('categoria') }}">
                                    Categoría <span>{{ $arrow('categoria') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 pr-6 pl-3 text-right text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ $urlOrden('goles') }}">
                                    Goles <span>{{ $arrow('goles') }}</span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($goleadores_t as $index => $g)
                            <tr class="transition hover:bg-white/5">
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
                                <td class="py-4 pr-6 pl-3 text-right text-lg font-bold text-brand-gold">
                                    {{ $g->cantidadGoles }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-5 lg:hidden" data-seccion="ranking-movil">
            @if ($podio->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2" data-zona="podio">
                    @foreach ($podio as $index => $g)
                        @php
                            $posicion = $index + 1;
                            $esPrimerPuesto = $posicion === 1;
                        @endphp
                        <article
                            data-variante="podio" data-posicion="{{ $posicion }}"
                            data-destacado="{{ $esPrimerPuesto ? 'principal' : 'secundario' }}"
                            class="rounded-3xl border p-5 {{ $esPrimerPuesto ? 'sm:col-span-2 border-brand-gold/70 bg-gradient-to-br from-brand-gold/15 via-brand-navy/90 to-brand-teal/90 shadow-[0_28px_70px_-40px_rgba(var(--brand-gold)_/_0.45)]' : 'border-white/20 bg-gradient-to-br from-brand-navy/85 to-brand-teal/85' }}">
                            <div
                                class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-[0.35em] {{ $esPrimerPuesto ? 'text-brand-gold' : 'text-white/60' }}">
                                <span class="inline-flex items-center gap-2">
                                    <i class="fa-solid fa-medal"></i>
                                    {{ $posicion }}° puesto
                                </span>
                                <span class="truncate">{{ $g->nombreCategoria }}</span>
                            </div>
                            <div class="mt-4 flex items-end justify-between gap-4">
                                <div class="min-w-0 space-y-1">
                                    <h3
                                        class="{{ $esPrimerPuesto ? 'text-2xl' : 'text-lg' }} font-bold text-white">
                                        {{ $g->nombreJugador }}
                                    </h3>
                                    <p class="text-sm text-white/70">{{ $g->nombreEquipo }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="block text-3xl font-bold text-brand-gold">{{ $g->cantidadGoles }}</span>
                                    <span class="text-[10px] uppercase tracking-[0.3em] text-white/60">Goles</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @foreach ($resto as $index => $g)
                @php $posicionCompacta = $posOffset + $cantidadPodio + $index + 1; @endphp
                <article
                    data-variante="compacta" data-posicion="{{ $posicionCompacta }}"
                    class="card-goleador-movil flex items-center justify-between gap-4 rounded-2xl border border-brand-gold/20 bg-black/30 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="w-6 shrink-0 text-center text-sm font-bold text-white/60">{{ $posicionCompacta }}</span>
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-bold text-white">{{ $g->nombreJugador }}</h3>
                            <p class="truncate text-xs text-white/60">{{ $g->nombreEquipo }} · {{ $g->nombreCategoria }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-lg font-bold text-brand-gold">{{ $g->cantidadGoles }}</span>
                </article>
            @endforeach
        </div>
    @endif
</div>

<div class="mt-8 flex justify-center">
    {{ $goleadores_t->links() }}
</div>
