@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate flex min-h-screen flex-col overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <main class="relative flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
            <div class="w-full max-w-5xl">
                <div class="grid gap-8 lg:grid-cols-[1.05fr,1fr] items-stretch">
                    <section
                        class="rounded-3xl border border-white/10 bg-white/[0.08] p-8 sm:p-10 shadow-[0_25px_60px_-35px_rgba(0,0,0,0.8)] backdrop-blur">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-4">
                            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo Torneo Mita'í"
                                class="h-16 w-16 object-contain">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/60">Torneo
                                    mita'í</p>
                                <h1 class="text-2xl font-bold text-white">Fútbol formativo con estadísticas
                                    en vivo</h1>
                            </div>
                        </a>
                        <p class="mt-6 text-sm text-white/70 sm:text-base">
                            Ingresá a tu cuenta para seguir fixtures, resultados y protagonistas de todas las categorías.
                            Si todavía no formás parte, registrate en minutos y comenzá a disfrutar la experiencia
                            completa del torneo.
                        </p>
                        <div class="mt-8 grid gap-3 text-sm text-white/70">
                            <div class="flex items-center gap-3 rounded-2xl bg-black/30 px-4 py-3">
                                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-white/10 text-lg text-white shadow-[0_12px_30px_-18px_rgba(255,255,255,0.45)]">
                                    <i class="fa-solid fa-stopwatch"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-white">Actualizaciones al instante</p>
                                    <p class="text-xs text-white/60">Resultados, tablas y jugadores destacados en
                                        un solo lugar.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl bg-black/30 px-4 py-3">
                                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-white/10 text-lg text-white shadow-[0_12px_30px_-18px_rgba(255,255,255,0.45)]">
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-white">Experiencia multipantalla</p>
                                    <p class="text-xs text-white/60">Optimizamos cada sección para móviles, tablets y
                                        escritorio.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section
                        class="rounded-3xl border border-white/10 bg-black/70 p-8 sm:p-10 shadow-[0_30px_80px_-40px_rgba(250,204,21,0.35)] backdrop-blur">
                        {{ $slot }}
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
