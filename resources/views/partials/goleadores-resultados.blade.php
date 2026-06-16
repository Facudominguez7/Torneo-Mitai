@php
    $sort = $sortKey;
    $toggle = fn($k) => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = fn($k) => $sort === $k ? ($dir === 'asc' ? '▲' : '▼') : '';
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
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ route('tabla-goleadores', ['sort' => 'nombre', 'dir' => $toggle('nombre')]) }}">
                                    Nombre <span>{{ $arrow('nombre') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ route('tabla-goleadores', ['sort' => 'equipo', 'dir' => $toggle('equipo')]) }}">
                                    Equipo <span>{{ $arrow('equipo') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 px-3 text-left text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ route('tabla-goleadores', ['sort' => 'categoria', 'dir' => $toggle('categoria')]) }}">
                                    Categoría <span>{{ $arrow('categoria') }}</span>
                                </a>
                            </th>
                            <th
                                class="py-4 pr-6 pl-3 text-right text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                <a class="orden-goleadores inline-flex items-center gap-2 hover:text-white"
                                    href="{{ route('tabla-goleadores', ['sort' => 'goles', 'dir' => $toggle('goles')]) }}">
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
                    class="card-goleador-movil rounded-3xl border border-[#f7f280]/25 bg-gradient-to-br from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/90 p-5 shadow-[0_24px_60px_-40px_rgba(14,75,94,0.85)]">
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
    @endif
</div>

<div class="mt-8 flex justify-center">
    {{ $goleadores_t->links() }}
</div>
