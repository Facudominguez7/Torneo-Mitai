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
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-red-300/80">
                                <span class="h-2 w-2 rounded-full bg-red-300"></span>
                                Camino a la gloria
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Instancias finales</h1>
                            <p class="text-sm sm:text-base text-white/70">Reviví cada fase decisiva de la {{ optional($EdicionSeleccionada)->nombre ?? 'vigente' }}. Filtrá por categoría y seguí el recorrido de tus equipos en copas y fases finales.</p>
                        </div>
                        <form method="GET" action="{{ route('instancias-finales') }}"
                            class="grid gap-4 rounded-3xl border border-white/10 bg-black/50 px-4 py-4 text-sm text-white/80 shadow-lg sm:px-6 sm:py-5">
                            
                            <!-- Filtro Edición -->
                            <div class="grid gap-2">
                                <label for="idEdicion" class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Edición</label>
                                <div class="relative">
                                    <div class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                        <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                            <select name="idEdicion" id="idEdicion" onchange="this.form.submit()"
                                                class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                style="color-scheme: dark;">
                                                @foreach ($ediciones as $edicion)
                                                    <option value="{{ $edicion->id }}" style="background-color: #0e4b5e; color: white;" {{ $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                                        {{ $edicion->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                                <i class="fa-solid fa-chevron-down text-xs"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtros en grid responsive -->
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <!-- Filtro Categoría -->
                                <div class="grid gap-2">
                                    <label for="categoria" class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Categoría</label>
                                    <div class="relative">
                                        <div class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                            <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                                <select name="categoria" id="categoria" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: #0e4b5e; color: white;">Todas</option>
                                                    @foreach ($categorias as $categoria)
                                                        <option value="{{ $categoria->id }}" style="background-color: #0e4b5e; color: white;" {{ $selectedCategoria == $categoria->id ? 'selected' : '' }}>
                                                            {{ $categoria->nombreCategoria }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filtro Copa -->
                                <div class="grid gap-2">
                                    <label for="copa" class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Copa</label>
                                    <div class="relative">
                                        <div class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                            <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                                <select name="copa" id="copa" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: #0e4b5e; color: white;">Todas</option>
                                                    @foreach ($copas as $copa)
                                                        <option value="{{ $copa->id }}" style="background-color: #0e4b5e; color: white;" {{ $selectedCopa == $copa->id ? 'selected' : '' }}>
                                                            {{ $copa->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filtro Fase -->
                                <div class="grid gap-2">
                                    <label for="fase" class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Fase</label>
                                    <div class="relative">
                                        <div class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                            <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                                <select name="fase" id="fase" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: #0e4b5e; color: white;">Todas</option>
                                                    @foreach ($fases as $fase)
                                                        <option value="{{ $fase->id }}" style="background-color: #0e4b5e; color: white;" {{ $selectedFase == $fase->id ? 'selected' : '' }}>
                                                            {{ $fase->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botón limpiar filtros -->
                            @if ($selectedCategoria || $selectedCopa || $selectedFase)
                                <a href="{{ route('instancias-finales', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                                    class="mt-2 inline-flex items-center justify-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/70 transition hover:bg-white/10 hover:text-white">
                                    <i class="fa-solid fa-xmark"></i>
                                    Limpiar filtros
                                </a>
                            @endif
                        </form>
                    </div>
                </section>

                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 space-y-12">
                    @foreach ($partidos->groupBy('nombre_categoria') as $categoria => $partidosPorCategoria)
                        <section class="space-y-6">
                            <header class="rounded-3xl border border-white/10 bg-white/5 px-6 py-5 text-center">
                                <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $categoria }}</h2>
                                <p class="mt-2 text-sm text-white/60">Los cruces más determinantes de la copa.</p>
                            </header>

                            @foreach ($partidosPorCategoria->groupBy('nombre_copa')->sortBy(fn($items) => $items->first()->orden_copa) as $copa => $partidosPorCopa)
                                <div class="space-y-6">
                                    <div class="flex flex-col gap-3 rounded-3xl border border-white/10 bg-white/5 px-6 py-5 text-center">
                                        <h3 class="text-xl font-semibold text-white">{{ $copa }}</h3>
                                        <span class="mx-auto inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/50">
                                            <i class="fa-solid fa-trophy text-yellow-300"></i>
                                            Instancias definitorias
                                        </span>
                                    </div>

                                    @foreach ($partidosPorCopa->groupBy('nombre_fase')->sortBy(fn($items) => $items->first()->orden_fase) as $fase => $partidosPorFase)
                                        <div class="space-y-5">
                                            <h4 class="text-lg font-semibold uppercase tracking-[0.3em] text-white/60 text-center">{{ $fase }}</h4>

                                            <div class="grid gap-6">
                                                @foreach ($partidosPorFase as $p)
                                                    @php
                                                        $fechaPartido = $p->horario ? \Carbon\Carbon::parse($p->horario) : null;
                                                        $esFuturo = optional($fechaPartido)->isFuture();
                                                        $hayMarcador = !is_null($p->golesEquipoLocal) && !is_null($p->golesEquipoVisitante);
                                                        $jugado = data_get($p, 'jugado');

                                                        $statusLabel = 'Pendiente';
                                                        $statusClasses = 'bg-[#f7f280]/15 text-[#f7f280] border border-[#f7f280]/40';

                                                        if ($jugado || $hayMarcador) {
                                                            $statusLabel = 'Finalizado';
                                                            $statusClasses = 'bg-gradient-to-r from-[#2a6d81]/90 to-[#0e4b5e]/90 text-[#f7f280] border border-[#f7f280]/45';
                                                        } elseif ($esFuturo) {
                                                            $statusLabel = 'Próximo';
                                                            $statusClasses = 'bg-gradient-to-r from-[#0e4b5e]/70 to-[#2a6d81]/70 text-white border border-[#f7f280]/35';
                                                        }
                                                    @endphp

                                                    <article class="partido-card rounded-3xl border border-[#f7f280]/30 bg-gradient-to-br from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] px-6 py-6 text-white shadow-[0_24px_60px_-40px_rgba(14,75,94,0.75)] transition hover:border-[#f7f280]/45">
                                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                                            <div class="text-sm font-semibold uppercase tracking-[0.3em] text-white/65">
                                                                {{ optional($fechaPartido)->format('d/m/Y') ?? 'Sin fecha' }}
                                                            </div>
                                                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                                <span class="inline-flex h-2 w-2 rounded-full bg-[#f7f280]"></span>
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </div>

                                                        <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4 md:hidden">
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-16 w-16 overflow-hidden rounded-full border border-white/20 bg-black/25 p-2.5">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" alt="{{ $p->equipoLocal->nombre }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <h3 class="text-sm font-bold text-white/95">{{ $p->equipoLocal->nombre }}</h3>
                                                            </div>
                                                            <div class="flex min-w-[80px] flex-col items-center gap-1 text-center">
                                                                <span class="text-[11px] font-semibold uppercase tracking-[0.35em] text-white/60">vs</span>
                                                                <span class="text-2xl font-bold text-white">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                                @if ($hayMarcador &&
                                                                        $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                                                        !is_null($p->penalesEquipoLocal) &&
                                                                        !is_null($p->penalesEquipoVisitante))
                                                                    <span class="text-[11px] font-semibold text-white/70">Penales: {{ $p->penalesEquipoLocal }} - {{ $p->penalesEquipoVisitante }}</span>
                                                                @endif
                                                                @if (!is_null($p->resultadoGlobal))
                                                                    <span class="text-[11px] font-semibold text-white/70">Global: {{ $p->resultadoGlobal }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-16 w-16 overflow-hidden rounded-full border border-white/20 bg-black/25 p-2.5">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" alt="{{ $p->equipoVisitante->nombre }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <h3 class="text-sm font-bold text-white/95">{{ $p->equipoVisitante->nombre }}</h3>
                                                            </div>
                                                        </div>

                                                        <div class="mt-6 hidden md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-6">
                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-20 w-20 overflow-hidden rounded-full border border-white/20 bg-black/25 p-3">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" alt="{{ $p->equipoLocal->nombre }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Local</p>
                                                                <h3 class="text-lg font-bold text-white">{{ $p->equipoLocal->nombre }}</h3>
                                                            </div>

                                                            <div class="flex flex-col items-center justify-center gap-2 text-center">
                                                                <span class="text-xs font-semibold uppercase tracking-[0.3em] text-white/40">Resultado</span>
                                                                <span class="text-3xl font-bold text-white">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</span>
                                                                @if ($hayMarcador &&
                                                                        $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                                                        !is_null($p->penalesEquipoLocal) &&
                                                                        !is_null($p->penalesEquipoVisitante))
                                                                    <span class="text-xs font-semibold text-white/70">Penales: {{ $p->penalesEquipoLocal }} - {{ $p->penalesEquipoVisitante }}</span>
                                                                @endif
                                                                @if (!is_null($p->resultadoGlobal))
                                                                    <span class="text-xs font-semibold text-white/70">Global: {{ $p->resultadoGlobal }}</span>
                                                                @endif
                                                            </div>

                                                            <div class="flex flex-col items-center gap-3 text-center">
                                                                <div class="h-20 w-20 overflow-hidden rounded-full border border-white/20 bg-black/25 p-3">
                                                                    <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" alt="{{ $p->equipoVisitante->nombre }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                                </div>
                                                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Visita</p>
                                                                <h3 class="text-lg font-bold text-white">{{ $p->equipoVisitante->nombre }}</h3>
                                                            </div>
                                                        </div>

                                                         <div class="mt-6 flex flex-col gap-3 text-sm text-white/70 md:flex-row md:items-center md:justify-between md:text-white/60">
                                                             <div class="inline-flex items-center gap-2">
                                                                 <i class="fa-regular fa-clock text-yellow-300"></i>
                                                                 <span>{{ optional($fechaPartido)->format('d/m/Y · H:i') ?? 'Horario a confirmar' }}</span>
                                                             </div>
                                                             <div class="inline-flex items-center gap-2">
                                                                 <i class="fa-solid fa-location-dot text-emerald-300"></i>
                                                                 <span>{{ $p->cancha ? 'Cancha ' . $p->cancha : 'Cancha a confirmar' }}</span>
                                                             </div>
                                                         </div>

                                                         @php
                                                             $publicToken = data_get($p, 'public_token');
                                                             $planillaPublicaDisponible = filled($publicToken);
                                                             $planillaPublicaUrl = $planillaPublicaDisponible
                                                                 ? route('planillas-publicas.show', [
                                                                     'tipoPartido' => 'instancia_final',
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
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            </main>

            @include('layouts.footer')

            <x-planilla-publica-modal />
        </div>
    </div>
</body>
