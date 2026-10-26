@section('tituloSeo', 'Instancias Finales | Torneo Mitai')
@section('descripcionSeo', 'Resultados de las instancias finales de cada edición del Torneo Mitai')
@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
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
                                    <div class="group rounded-2xl bg-gradient-to-r from-brand-navy via-brand-teal to-brand-navy p-[1px] transition focus-within:ring-2 focus-within:ring-brand-gold/50">
                                        <div class="relative rounded-[1.1rem] bg-brand-navy/80">
                                            <select name="idEdicion" id="idEdicion" onchange="this.form.submit()"
                                                class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                style="color-scheme: dark;">
                                                @foreach ($ediciones as $edicion)
                                                    <option value="{{ $edicion->id }}" style="background-color: var(--color-primary); color: white;" {{ $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                                        {{ $edicion->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-gold">
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
                                        <div class="group rounded-2xl bg-gradient-to-r from-brand-navy via-brand-teal to-brand-navy p-[1px] transition focus-within:ring-2 focus-within:ring-brand-gold/50">
                                            <div class="relative rounded-[1.1rem] bg-brand-navy/80">
                                                <select name="categoria" id="categoria" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: var(--color-primary); color: white;">Todas</option>
                                                    @foreach ($categorias as $categoria)
                                                        <option value="{{ $categoria->id }}" style="background-color: var(--color-primary); color: white;" {{ $selectedCategoria == $categoria->id ? 'selected' : '' }}>
                                                            {{ $categoria->nombreCategoria }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-gold">
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
                                        <div class="group rounded-2xl bg-gradient-to-r from-brand-navy via-brand-teal to-brand-navy p-[1px] transition focus-within:ring-2 focus-within:ring-brand-gold/50">
                                            <div class="relative rounded-[1.1rem] bg-brand-navy/80">
                                                <select name="copa" id="copa" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: var(--color-primary); color: white;">Todas</option>
                                                    @foreach ($copas as $copa)
                                                        <option value="{{ $copa->id }}" style="background-color: var(--color-primary); color: white;" {{ $selectedCopa == $copa->id ? 'selected' : '' }}>
                                                            {{ $copa->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-gold">
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
                                        <div class="group rounded-2xl bg-gradient-to-r from-brand-navy via-brand-teal to-brand-navy p-[1px] transition focus-within:ring-2 focus-within:ring-brand-gold/50">
                                            <div class="relative rounded-[1.1rem] bg-brand-navy/80">
                                                <select name="fase" id="fase" onchange="this.form.submit()"
                                                    class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                                    style="color-scheme: dark;">
                                                    <option value="" style="background-color: var(--color-primary); color: white;">Todas</option>
                                                    @foreach ($fases as $fase)
                                                        <option value="{{ $fase->id }}" style="background-color: var(--color-primary); color: white;" {{ $selectedFase == $fase->id ? 'selected' : '' }}>
                                                            {{ $fase->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-gold">
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
                                                        // x-tarjeta-partido espera horario_datetime; instancias_finales
                                                        // solo tiene `horario`. Se normaliza el mismo modelo en vez de
                                                        // duplicar la logica de fecha/estado en esta vista.
                                                        $p->horario_datetime = $p->horario;

                                                        $hayMarcador = !is_null($p->golesEquipoLocal) && !is_null($p->golesEquipoVisitante);

                                                        $penalesPartido = ($hayMarcador &&
                                                                $p->golesEquipoLocal === $p->golesEquipoVisitante &&
                                                                !is_null($p->penalesEquipoLocal) &&
                                                                !is_null($p->penalesEquipoVisitante))
                                                            ? $p->penalesEquipoLocal . ' - ' . $p->penalesEquipoVisitante
                                                            : null;
                                                    @endphp

                                                    <x-tarjeta-partido
                                                        :partido="$p"
                                                        tipo-partido="instancia_final"
                                                        :fecha-label="optional($p->horario ? \Carbon\Carbon::parse($p->horario) : null)->format('d/m/Y') ?? 'Sin fecha'"
                                                        resultado-label-desktop="Resultado"
                                                        :penales="$penalesPartido"
                                                        :resultado-global="$p->resultadoGlobal ?? null"
                                                        :marcador-cuenta-como-finalizado="true"
                                                    />
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
