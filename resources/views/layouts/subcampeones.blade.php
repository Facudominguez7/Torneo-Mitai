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
                        <div class="space-y-3 max-w-2xl">
                            <span
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                <span class="h-2 w-2 rounded-full bg-white/70"></span>
                                Podios memorables
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Subcampeones que dejaron todo</h1>
                            <p class="text-sm sm:text-base text-white/70">Descubrí a los equipos que alcanzaron la final
                                y se quedaron con la plateada en cada categoría de la edición seleccionada.</p>
                        </div>
                        <form method="GET" action="{{ route('subcampeones') }}"
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
                                'titulo' => 'Subcampeones Copa de Oro',
                                'coleccion' => $subcampeonesOro,
                                'card_classes' => 'group relative overflow-hidden rounded-3xl border border-yellow-400/50 bg-gradient-to-br from-black/80 via-black/60 to-black/80 p-6 text-white shadow-[0_0_30px_-10px_rgba(250,204,21,0.15)] transition-transform duration-300 hover:-translate-y-1 hover:border-yellow-400/80 hover:shadow-[0_0_40px_-5px_rgba(250,204,21,0.3)]',
                                'icon_wrapper' => 'text-yellow-400',
                                'shield_classes' => 'h-24 w-24 overflow-hidden rounded-full border border-yellow-400/30 bg-white/5 p-2 shadow-inner shadow-yellow-400/10',
                                'text_muted' => 'text-gray-400',
                                'icono' => 'fa-medal',
                                'badge' => 'Perseverancia dorada',
                                'badge_classes' => 'hidden sm:inline-flex items-center gap-2 rounded-full border border-yellow-400/30 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-yellow-200/80'
                            ],
                            [
                                'titulo' => 'Subcampeones Copa de Plata',
                                'coleccion' => $subcampeonesPlata,
                                'card_classes' => 'group relative overflow-hidden rounded-3xl border border-gray-400/50 bg-gradient-to-br from-black/80 via-black/60 to-black/80 p-6 text-white shadow-[0_0_30px_-10px_rgba(156,163,175,0.15)] transition-transform duration-300 hover:-translate-y-1 hover:border-gray-300/80 hover:shadow-[0_0_40px_-5px_rgba(156,163,175,0.3)]',
                                'icon_wrapper' => 'text-gray-300',
                                'shield_classes' => 'h-24 w-24 overflow-hidden rounded-full border border-gray-400/30 bg-white/5 p-2 shadow-inner shadow-gray-400/10',
                                'text_muted' => 'text-gray-400',
                                'icono' => 'fa-award',
                                'badge' => 'Esfuerzo plateado',
                                'badge_classes' => 'hidden sm:inline-flex items-center gap-2 rounded-full border border-gray-400/30 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-gray-300/80'
                            ],
                            [
                                'titulo' => 'Subcampeones Copa de Bronce',
                                'coleccion' => $subcampeonesBronce,
                                'card_classes' => 'group relative overflow-hidden rounded-3xl border border-bronce-400/50 bg-gradient-to-br from-black/80 via-black/60 to-black/80 p-6 text-white shadow-[0_0_30px_-10px_rgba(205,127,50,0.15)] transition-transform duration-300 hover:-translate-y-1 hover:border-bronce-400/80 hover:shadow-[0_0_40px_-5px_rgba(205,127,50,0.3)]',
                                'icon_wrapper' => 'text-bronce-400',
                                'shield_classes' => 'h-24 w-24 overflow-hidden rounded-full border border-bronce-400/30 bg-white/5 p-2 shadow-inner shadow-bronce-400/10',
                                'text_muted' => 'text-gray-400',
                                'icono' => 'fa-shield-halved',
                                'badge' => 'Espíritu bronce',
                                'badge_classes' => 'hidden sm:inline-flex items-center gap-2 rounded-full border border-bronce-400/30 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-bronce-200/80'
                            ],
                            [
                                'titulo' => "Subcampeones Copa Mita'í",
                                'coleccion' => $subcampeonesMitai,
                                'card_classes' => 'group relative overflow-hidden rounded-3xl border border-emerald-500/50 bg-gradient-to-br from-black/80 via-black/60 to-black/80 p-6 text-white shadow-[0_0_30px_-10px_rgba(16,185,129,0.15)] transition-transform duration-300 hover:-translate-y-1 hover:border-emerald-500/80 hover:shadow-[0_0_40px_-5px_rgba(16,185,129,0.3)]',
                                'icon_wrapper' => 'text-emerald-500',
                                'shield_classes' => 'h-24 w-24 overflow-hidden rounded-full border border-emerald-500/30 bg-white/5 p-2 shadow-inner shadow-emerald-500/10',
                                'text_muted' => 'text-gray-400',
                                'icono' => 'fa-star',
                                'badge' => 'Orgullo mita\'í',
                                'badge_classes' => 'hidden sm:inline-flex items-center gap-2 rounded-full border border-emerald-500/30 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300/80'
                            ],
                        ];
                    @endphp

                    @foreach ($bloques as $bloque)
                        <div class="mt-12 first:mt-10">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $bloque['titulo'] }}</h2>
                                <span class="{{ $bloque['badge_classes'] }}">
                                    <i class="fa-solid {{ $bloque['icono'] }}"></i>
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
                                        <article class="{{ $bloque['card_classes'] }}">
                                            <!-- Glow effect -->
                                            <div class="absolute -right-14 -top-20 h-40 w-40 rounded-full bg-white/5 blur-3xl opacity-60 transition-opacity duration-300 group-hover:opacity-90"></div>
                                            
                                            <div class="relative flex h-full flex-col justify-between">
                                                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-gray-400">
                                                    <span>{{ $equipo->categoria->nombreCategoria ?? 'Categoría' }}</span>
                                                    <i class="fa-solid {{ $bloque['icono'] }} text-lg {{ $bloque['icon_wrapper'] }}"></i>
                                                </div>
                                                <div class="mt-6 flex flex-col items-center text-center">
                                                    <div class="{{ $bloque['shield_classes'] }}">
                                                        <img src="{{ asset('fotos/equipos/' . ($equipo->equipo->foto ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                            alt="{{ $equipo->equipo->nombre ?? 'Equipo' }}"
                                                            class="h-full w-full object-contain"
                                                            onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                    </div>
                                                    <h3 class="mt-4 text-xl font-bold text-white tracking-wide">
                                                        {{ $equipo->equipo->nombre ?? 'Equipo por confirmar' }}
                                                    </h3>
                                                </div>
                                                <p class="mt-5 text-center text-sm {{ $bloque['text_muted'] }}">
                                                    El esfuerzo y la constancia también se celebran en el torneo.
                                                </p>
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