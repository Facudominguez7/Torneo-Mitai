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
                    <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
                        <div class="space-y-3">
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-[#f7f280]/80">
                                <span class="h-2 w-2 rounded-full bg-[#f7f280]"></span>
                                Tabla general
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Tabla de posiciones · {{ $nombreCategoria }}</h1>
                            <p class="max-w-2xl text-sm sm:text-base text-white/70">Resumen actualizado de puntos, victorias y diferencia de gol para cada equipo del grupo. El orden prioriza puntaje y, en caso de empate, ubica primero a los equipos que ya jugaron.</p>
                        </div>
                    </div>

                    @if ($EdicionSeleccionada && $categorias->isNotEmpty())
                        <div class="mt-8 space-y-3">
                            <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-x-visible [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Cambiar categoría">
                                @foreach ($categorias as $c)
                                    <a href="{{ route('tabla-posiciones', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $c->id]) }}"
                                        @if ($c->id == $idCategoria) aria-current="page" @endif
                                        class="whitespace-nowrap rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f7f280]/40 {{ $c->id == $idCategoria ? 'border-[#f7f280] bg-[#f7f280]/20 text-[#f7f280]' : 'border-white/20 bg-white/5 text-white/70 hover:border-[#f7f280]/40 hover:bg-[#f7f280]/10 hover:text-white' }}">
                                        {{ $c->nombreCategoria }}
                                    </a>
                                @endforeach
                            </div>

                            @if ($grupos->count() > 1)
                                <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-x-visible [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Cambiar grupo">
                                    @foreach ($grupos as $g)
                                        <a href="{{ route('tabla-posiciones', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria, 'idGrupo' => $g->id]) }}"
                                            @if ($g->id == $idGrupo) aria-current="page" @endif
                                            class="whitespace-nowrap rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f7f280]/40 {{ $g->id == $idGrupo ? 'border-[#f7f280] bg-[#f7f280]/20 text-[#f7f280]' : 'border-white/20 bg-white/5 text-white/70 hover:border-[#f7f280]/40 hover:bg-[#f7f280]/10 hover:text-white' }}">
                                            {{ $g->nombre }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($EdicionSeleccionada)
                        <div class="mt-6 flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.25em] text-white/60">
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <i class="fa-regular fa-calendar-days text-[#f7f280]"></i>
                                {{ $EdicionSeleccionada->nombre }}
                            </span>
                            @if ($GrupoSeleccionado)
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                    <i class="fa-solid fa-layer-group text-[#f7f280]"></i>
                                    {{ $GrupoSeleccionado->nombre }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="mt-10 rounded-3xl border border-[#f7f280]/25 bg-black/35 px-4 py-5 shadow-[0_28px_70px_-45px_rgba(14,75,94,0.9)] sm:px-6 sm:py-6">
                        <div class="overflow-hidden rounded-2xl border border-white/5 bg-black/40">
                            <table class="hidden w-full border-collapse text-sm text-white/80 md:table">
                                <thead class="bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/90 text-xs font-semibold uppercase tracking-[0.3em] text-white/70">
                                    <tr>
                                        <th class="hidden md:table-cell px-4 py-4 text-left">Puesto</th>
                                        <th class="px-4 py-4 text-left">Equipo</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">Pts</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">J</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">G</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">E</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">P</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">GF</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">GC</th>
                                        <th class="hidden md:table-cell px-4 py-4 text-center">DF</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-sm">
                                    @php $posicion = 1; @endphp
                                    @foreach ($tablaPosiciones as $filaTabla)
                                        <tr class="bg-white/5 text-white transition hover:bg-white/8">
                                            <td class="hidden md:table-cell px-4 py-4 text-left font-semibold text-white/80">
                                                {{ $posicion }}°
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-black/40 p-2 shadow-inner shadow-black/40">
                                                        <img src="{{ asset('fotos/equipos/' . $filaTabla->fotoEquipo) }}" alt="logo {{ $filaTabla->nombreEquipo }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <div class="flex items-center gap-2">
                                                            <span class="md:hidden rounded-full border border-[#f7f280]/40 bg-[#f7f280]/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.25em] text-[#f7f280]">{{ $posicion }}°</span>
                                                            <h3 class="text-base font-bold text-white">{{ $filaTabla->nombreEquipo }}</h3>
                                                        </div>
                                                        <div class="mt-1 grid grid-cols-4 gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/50 md:hidden">
                                                            <span>Pts {{ $filaTabla->puntos }}</span>
                                                            <span>J {{ $filaTabla->jugado }}</span>
                                                            <span>GF {{ $filaTabla->golesFavor }}</span>
                                                            <span>DF {{ $filaTabla->diferenciaGoles }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center font-semibold text-[#f7f280]">
                                                {{ $filaTabla->puntos }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->jugado }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->ganado }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->empatado }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->perdido }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->golesFavor }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->golesContra }}
                                            </td>
                                            <td class="hidden md:table-cell px-4 py-4 text-center text-white/80">
                                                {{ $filaTabla->diferenciaGoles }}
                                            </td>
                                        </tr>
                                        @php $posicion++; @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="md:hidden">
                            <table class="w-full border-collapse text-sm text-white/80">
                                <thead class="bg-gradient-to-r from-[#0e4b5e]/95 via-[#2a6d81]/90 to-[#0e4b5e]/95 text-xs font-semibold uppercase tracking-[0.3em] text-white/70">
                                    <tr>
                                        <th class="px-3 py-3 text-left">#</th>
                                        <th class="px-3 py-3 text-left">Equipo</th>
                                        <th class="px-3 py-3 text-center">J</th>
                                        <th class="px-3 py-3 text-center">DG</th>
                                        <th class="px-3 py-3 text-center">Pts</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @php $posicion = 1; @endphp
                                    @foreach ($tablaPosiciones as $filaTabla)
                                        <tr class="bg-black/50 text-white transition hover:bg-white/10">
                                            <td class="px-3 py-3 text-left text-sm font-semibold text-white/70">
                                                {{ $posicion }}
                                            </td>
                                            <td class="px-3 py-3 text-left">
                                                <div class="flex items-center gap-3">
                                                    <img src="{{ asset('fotos/equipos/' . $filaTabla->fotoEquipo) }}" alt="logo {{ $filaTabla->nombreEquipo }}" class="h-9 w-9 flex-shrink-0 rounded-full border border-white/15 bg-black/40 object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                    <span class="text-sm font-semibold text-white">{{ $filaTabla->nombreEquipo }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-center text-sm font-semibold text-white/70">
                                                {{ $filaTabla->jugado }}
                                            </td>
                                            <td class="px-3 py-3 text-center text-sm font-semibold text-white">
                                                {{ $filaTabla->diferenciaGoles >= 0 ? '+' : '' }}{{ $filaTabla->diferenciaGoles }}
                                            </td>
                                            <td class="px-3 py-3 text-center text-base font-bold text-[#f7f280]">
                                                {{ $filaTabla->puntos }}
                                            </td>
                                        </tr>
                                        @php $posicion++; @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>
</body>
