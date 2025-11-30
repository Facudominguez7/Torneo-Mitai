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
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/60">
                                <span class="h-2 w-2 rounded-full bg-white/70"></span>
                                Podios memorables
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Subcampeones que dejaron todo</h1>
                            <p class="text-sm sm:text-base text-white/70">Descubrí a los equipos que alcanzaron la final y se quedaron con la plateada en cada categoría de la edición seleccionada.</p>
                        </div>
                        <form method="GET" action="{{ route('subcampeones') }}"
                            class="grid gap-3 rounded-3xl border border-white/10 bg-black/50 px-4 py-4 text-sm text-white/80 shadow-lg sm:px-6 sm:py-5">
                            <label for="idEdicion" class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Edición</label>
                            <div class="relative">
                                <div class="group rounded-2xl bg-gradient-to-r from-[#0e4b5e] via-[#2a6d81] to-[#0e4b5e] p-[1px] transition focus-within:ring-2 focus-within:ring-[#f7f280]/50">
                                    <div class="relative rounded-[1.1rem] bg-[#0e4b5e]/80">
                                        <select id="idEdicion" name="idEdicion" onchange="this.form.submit()"
                                            class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none">
                                            <option value="">Seleccioná una edición</option>
                                            @foreach ($ediciones as $edicion)
                                                <option value="{{ $edicion->id }}" {{ optional($EdicionSeleccionada)->id === $edicion->id ? 'selected' : '' }}>
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
                        </form>
                    </div>

                    @php
                        $bloques = [
                            [
                                'titulo' => 'Subcampeones Copa de Oro',
                                'coleccion' => $subcampeonesOro,
                                'gradiente' => 'from-amber-200/95 via-amber-300/90 to-yellow-200/80',
                                'borde' => 'border-yellow-200/60',
                                'icono' => 'fa-medal',
                            ],
                            [
                                'titulo' => 'Subcampeones Copa de Plata',
                                'coleccion' => $subcampeonesPlata,
                                'gradiente' => 'from-slate-200/95 via-slate-300/90 to-slate-400/85',
                                'borde' => 'border-white/40',
                                'icono' => 'fa-award',
                            ],
                        ];
                    @endphp

                    @foreach ($bloques as $bloque)
                        <div class="mt-12 first:mt-10">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $bloque['titulo'] }}</h2>
                                <span class="hidden sm:inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/50">
                                    <i class="fa-solid {{ $bloque['icono'] }} text-yellow-100"></i>
                                    {{ str_contains($bloque['titulo'], 'Oro') ? 'Perseverancia dorada' : 'Esfuerzo plateado' }}
                                </span>
                            </div>

                            @if ($bloque['coleccion']->isEmpty())
                                <p class="mt-6 rounded-3xl border border-white/10 bg-white/5 px-6 py-8 text-center text-sm font-semibold text-white/60">No se registraron equipos en esta copa para la edición seleccionada.</p>
                            @else
                                <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($bloque['coleccion'] as $equipo)
                                        <article
                                            class="group relative overflow-hidden rounded-3xl border {{ $bloque['borde'] }} bg-gradient-to-br {{ $bloque['gradiente'] }} p-6 text-slate-900 shadow-[0_22px_58px_-34px_rgba(0,0,0,0.7)] transition-transform duration-300 hover:-translate-y-1">
                                            <div class="absolute -right-14 -top-20 h-40 w-40 rounded-full bg-white/50 blur-3xl opacity-60 transition-opacity duration-300 group-hover:opacity-90"></div>
                                            <div class="relative flex h-full flex-col justify-between">
                                                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-slate-600/70">
                                                    <span>{{ $equipo->categoria->nombreCategoria ?? 'Categoría' }}</span>
                                                    <i class="fa-solid {{ $bloque['icono'] }} text-lg text-slate-700/80"></i>
                                                </div>
                                                <div class="mt-6 flex flex-col items-center text-center">
                                                    <div class="h-24 w-24 overflow-hidden rounded-full border border-slate-200/60 bg-white/50 p-2 shadow-inner">
                                                        <img src="{{ asset('fotos/equipos/' . ($equipo->equipo->foto ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                            alt="{{ $equipo->equipo->nombre ?? 'Equipo' }}"
                                                            class="h-full w-full object-contain"
                                                            onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                    </div>
                                                    <h3 class="mt-4 text-xl font-bold text-slate-900">
                                                        {{ $equipo->equipo->nombre ?? 'Equipo por confirmar' }}
                                                    </h3>
                                                </div>
                                                <p class="mt-5 text-center text-sm text-slate-700/80">
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
