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
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300/80">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                                Muralla del torneo
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Vallas menos vencidas</h1>
                            <p class="text-sm sm:text-base text-white/70">Los arqueros que mantienen el arco en cero
                                jornada tras jornada. Filtrá por edición y descubrí quién lidera la tabla defensiva.</p>
                        </div>
                        <form method="GET" action="{{ route('vallas') }}"
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

                    @if (count($vallas) > 0)
                        <div class="mt-10 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($vallas as $valla)
                                <article
                                    class="group relative overflow-hidden rounded-3xl border border-emerald-200/40 bg-gradient-to-br from-emerald-200/95 via-emerald-300/85 to-green-300/80 p-6 text-emerald-950 shadow-[0_24px_65px_-36px_rgba(16,185,129,0.7)] transition-transform duration-300 hover:-translate-y-1">
                                    <div
                                        class="absolute -right-14 -top-16 h-36 w-36 rounded-full bg-white/50 blur-3xl opacity-60 transition-opacity duration-300 group-hover:opacity-90">
                                    </div>
                                    <div class="relative flex h-full flex-col justify-between">
                                        <div
                                            class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-emerald-800/80">
                                            <span>{{ $valla['categoria_nombre'] }}</span>
                                            <i class="fa-solid fa-shield-halved text-lg"></i>
                                        </div>
                                        <div class="mt-6 flex flex-col items-center text-center">
                                            <div
                                                class="h-24 w-24 overflow-hidden rounded-full border border-emerald-600/30 bg-white/60 p-2 shadow-inner">
                                                <img src="{{ asset('fotos/equipos/' . ($valla['foto_equipo'] ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                    alt="{{ $valla['nombre_equipo'] }}" class="h-full w-full object-contain"
                                                    onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                            </div>
                                            <h3 class="mt-4 text-xl font-bold text-emerald-950">
                                                {{ $valla['nombre_jugador'] ?? 'Arquero destacado' }}
                                            </h3>
                                            <p class="mt-1 text-sm text-emerald-900/80">{{ $valla['nombre_equipo'] }}</p>
                                        </div>
                                        <p
                                            class="mt-5 text-center text-xs font-semibold uppercase tracking-[0.3em] text-emerald-900/70">
                                            {{ $valla['goles_recibidos'] ?? 0 }} goles recibidos
                                        </p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p
                            class="mt-10 rounded-3xl border border-white/10 bg-white/5 px-6 py-10 text-center text-lg font-semibold text-white/70">
                            Sin registros para la edición seleccionada.
                        </p>
                    @endif
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>
</body>