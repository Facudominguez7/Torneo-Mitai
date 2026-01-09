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
                        <div class="space-y-3 max-w-2xl">
                            <span
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-yellow-300/80">
                                <span class="h-2 w-2 rounded-full bg-yellow-300"></span>
                                Historial del torneo
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Campeones de cada categoría</h1>
                            <p class="text-sm sm:text-base text-white/70">Reviví los equipos que levantaron la copa en
                                la edición seleccionada y descubrí quién domina cada categoría.</p>
                        </div>
                        <form method="GET" action="{{ route('campeones') }}"
                            class="grid gap-3 rounded-3xl border border-white/10 bg-black/50 px-4 py-4 text-sm text-white/80 shadow-lg sm:px-6 sm:py-5">
                            <label for="idEdicion"
                                class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Edición</label>
                            <div class="relative">
                                <div
                                    class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                    <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                        <select id="idEdicion" name="idEdicion" onchange="this.form.submit()"
                                            class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                            style="color-scheme: dark;">
                                            <option value="" style="background-color: #0e4b5e; color: white;">Seleccioná
                                                una edición
                                            </option>
                                            @foreach ($ediciones as $edicion)
                                                <option value="{{ $edicion->id }}"
                                                    style="background-color: #0e4b5e; color: white;" {{ optional($EdicionSeleccionada)->id == $edicion->id ? 'selected' : '' }}>
                                                    {{ $edicion->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span
                                            class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                            <i class="fa-solid fa-chevron-down text-xs"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    @php
                        $bloques = [
                            [
                                'titulo' => 'Campeones Copa de Oro',
                                'coleccion' => $campeonesOro,
                                'icono' => 'fa-trophy',
                                'badge' => 'Leyendas doradas',
                                'detalleCopa' => 'Copa de Oro',
                                'card_classes' => 'group relative flex flex-col overflow-hidden rounded-3xl border border-yellow-200/60 bg-gradient-to-br from-yellow-500/95 via-yellow-100/90 to-yellow-500/85 p-6 text-yellow-950 shadow-[0_24px_60px_-30px_rgba(253,224,71,0.9)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[0_32px_70px_-28px_rgba(248,220,82,1)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-yellow-200',
                                'glow_classes' => 'absolute -right-12 -top-12 h-32 w-32 rounded-full bg-yellow-300 blur-3xl transition-opacity duration-300 group-hover:opacity-70',
                                'icon_wrapper' => 'flex h-12 w-12 items-center justify-center rounded-2xl bg-yellow-200/60 text-2xl text-yellow-900',
                                'tag_classes' => 'text-xs font-semibold uppercase tracking-[0.3em] text-yellow-800/80',
                                'shield_wrapper' => 'h-24 w-24 overflow-hidden rounded-full border border-yellow-200/70 bg-white/50 p-2 shadow-inner shadow-yellow-600/20',
                                'title_classes' => 'mt-4 text-2xl font-bold text-yellow-950',
                                'subtitle_classes' => 'mt-2 text-sm text-yellow-900/80',
                                'cta_classes' => 'mt-6 inline-flex items-center gap-2 text-sm font-semibold text-yellow-950',
                            ],
                            [
                                'titulo' => 'Campeones Copa de Plata',
                                'coleccion' => $campeonesPlata,
                                'icono' => 'fa-medal',
                                'badge' => 'Gloria plateada',
                                'detalleCopa' => 'Copa de Plata',
                                'card_classes' => 'group relative flex flex-col overflow-hidden rounded-3xl border border-gray-200/60 bg-gradient-to-br from-gray-500/95 via-gray-200/90 to-gray-500/90 p-6 text-slate-900 shadow-[0_20px_55px_-35px_rgba(148,163,184,0.9)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[0_28px_65px_-30px_rgba(148,163,184,0.6)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gray-200',
                                'glow_classes' => 'absolute -right-12 -top-12 h-32 w-32 rounded-full bg-gray-300 blur-3xl transition-opacity duration-300 group-hover:opacity-90',
                                'icon_wrapper' => 'flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-200/60 text-2xl text-slate-900',
                                'tag_classes' => 'text-xs font-semibold uppercase tracking-[0.3em] text-gray-600/80',
                                'shield_wrapper' => 'h-24 w-24 overflow-hidden rounded-full border border-white/40 bg-white/60 p-2 shadow-inner shadow-slate-900/10',
                                'title_classes' => 'mt-4 text-2xl font-bold text-gray-900',
                                'subtitle_classes' => 'mt-2 text-sm text-gray-800/80',
                                'cta_classes' => 'mt-6 inline-flex items-center gap-2 text-sm font-semibold text-gray-900',
                            ],
                            [
                                'titulo' => 'Campeones Copa de Bronce',
                                'coleccion' => $campeonesBronce,
                                'icono' => 'fa-shield-halved',
                                'badge' => 'Espíritu bronce',
                                'detalleCopa' => 'Copa de Bronce',
                                'card_classes' => 'group relative flex flex-col overflow-hidden rounded-3xl border border-bronce-200/60 bg-gradient-to-br from-bronce-600/95 via-bronce-200/90 to-bronce-600/85 p-6 text-bronce-950 shadow-[0_24px_60px_-30px_rgba(205,127,50,0.9)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[0_32px_70px_-28px_rgba(205,127,50,1)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-bronce-200',
                                'glow_classes' => 'absolute -right-12 -top-12 h-32 w-32 rounded-full bg-bronce-300 blur-3xl transition-opacity duration-300 group-hover:opacity-70',
                                'icon_wrapper' => 'flex h-12 w-12 items-center justify-center rounded-2xl bg-bronce-200/60 text-2xl text-bronce-900',
                                'tag_classes' => 'text-xs font-semibold uppercase tracking-[0.3em] text-bronce-800/80',
                                'shield_wrapper' => 'h-24 w-24 overflow-hidden rounded-full border border-bronce-200/70 bg-white/50 p-2 shadow-inner shadow-bronce-600/20',
                                'title_classes' => 'mt-4 text-2xl font-bold text-bronce-950',
                                'subtitle_classes' => 'mt-2 text-sm text-bronce-900/80',
                                'cta_classes' => 'mt-6 inline-flex items-center gap-2 text-sm font-semibold text-bronce-950',
                            ],
                            [
                              'titulo' => "Campeones Copa Mita'í",
                              'coleccion' => $campeonesMitai,
                              'icono' => 'fa-award',
                              'badge' => 'Orgullo del torneo',
                              'detalleCopa' => "Copa Mita'í",

                              'card_classes' => 'group relative flex flex-col overflow-hidden rounded-3xl border border-emerald-400/25 bg-gradient-to-br from-emerald-950/70 via-emerald-900/45 to-black/70 p-6 text-emerald-50 shadow-[0_24px_60px_-30px_rgba(16,185,129,0.35)] ring-1 ring-emerald-400/15 backdrop-blur-md transition-transform duration-300 hover:-translate-y-1 hover:ring-emerald-300/25 hover:shadow-[0_32px_70px_-28px_rgba(16,185,129,0.5)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-400',

                              'glow_classes' => 'pointer-events-none absolute -right-16 -top-16 h-44 w-44 rounded-full bg-emerald-500/20 blur-3xl opacity-0 transition-opacity duration-300 group-hover:opacity-100',

                              'icon_wrapper' => 'flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 ring-1 ring-emerald-400/20 text-2xl text-emerald-300',

                              'tag_classes' => 'text-xs font-semibold uppercase tracking-[0.35em] text-emerald-200/80',

                              'shield_wrapper' => 'h-24 w-24 overflow-hidden rounded-full bg-white/90 p-2 ring-1 ring-emerald-400/25 shadow-inner shadow-black/30',

                              'title_classes' => 'mt-4 text-2xl font-bold text-white drop-shadow-[0_2px_12px_rgba(0,0,0,0.35)]',

                              'subtitle_classes' => 'mt-2 text-sm text-emerald-200/80',

                              'cta_classes' => 'mt-6 inline-flex items-center gap-2 text-sm font-semibold text-emerald-300 hover:text-emerald-200',
                            ],
                        ];
                    @endphp

                    @foreach ($bloques as $bloque)
                        <div class="mt-12 first:mt-10">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $bloque['titulo'] }}</h2>
                                <span
                                    class="hidden sm:inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/50">
                                    <i class="fa-solid {{ $bloque['icono'] }} text-yellow-200"></i>
                                    {{ $bloque['badge'] }}
                                </span>
                            </div>

                            @if ($bloque['coleccion']->isEmpty())
                                <p
                                    class="mt-6 rounded-3xl border border-white/10 bg-white/5 px-6 py-8 text-center text-sm font-semibold text-white/60">
                                    No se registraron equipos en esta copa para la edición seleccionada.</p>
                            @else
                                <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($bloque['coleccion'] as $equipo)
                                        @php
                                            $edicionNombre = optional($equipo->edicion)->nombre ?? optional($EdicionSeleccionada)->nombre ?? 'vigente';
                                        @endphp
                                        <article class="{{ $bloque['card_classes'] }}">
                                            <div class="{{ $bloque['glow_classes'] }}"></div>
                                            <div class="relative flex h-full flex-col justify-between">
                                                <div class="flex items-center justify-between">
                                                    <div class="{{ $bloque['icon_wrapper'] }}">
                                                        <i class="fa-solid {{ $bloque['icono'] }}"></i>
                                                    </div>
                                                    <span class="{{ $bloque['tag_classes'] }}">
                                                        {{ $equipo->categoria->nombreCategoria ?? 'Categoría' }}
                                                    </span>
                                                </div>
                                                <div class="mt-6 flex flex-col items-center text-center text-black">
                                                    <div class="{{ $bloque['shield_wrapper'] }}">
                                                        <img src="{{ asset('fotos/equipos/' . ($equipo->equipo->foto ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                            alt="{{ $equipo->equipo->nombre ?? 'Equipo' }}"
                                                            class="h-full w-full object-contain"
                                                            onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                    </div>
                                                    <h3 class="{{ $bloque['title_classes'] }}">
                                                        {{ $equipo->equipo->nombre ?? 'Equipo por confirmar' }}
                                                    </h3>
                                                    <p class="{{ $bloque['subtitle_classes'] }}">
                                                        {{ $bloque['detalleCopa'] }}
                                                    </p>
                                                </div>

                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>
</body>