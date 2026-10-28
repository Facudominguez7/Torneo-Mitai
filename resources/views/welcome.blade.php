@section('tituloSeo', 'Torneo Mitai | Fútbol Infantil')
@section('descripcionSeo', 'Fixture, tablas de posiciones, goleadores y campeones del Torneo Mitai de fútbol infantil')
<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home min-h-screen bg-tema-fondo text-tema-tinta antialiased transition-colors duration-300">
    @include('layouts.navigation')
    <main class="tm-velo-host relative">
        {{-- Marca de agua: logo difuminado detrás de todo (opacidad según tema) --}}
        <div class="tm-marca-agua" aria-hidden="true">
            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="" loading="lazy">
        </div>

        {{-- Alertas del sistema --}}
        @if (session()->has('warning') || session()->has('success') || session()->has('error') || session()->has('info') || session()->has('status') || request()->query('verified') == '1')
            <div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
                @if (request()->query('verified') == '1')
                    <div class="mb-3 flex items-start gap-4 rounded-2xl border border-green-200 bg-white p-4 shadow-lg" role="alert">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                            <i class="fa-solid fa-envelope-circle-check text-lg text-green-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 text-sm font-bold text-green-700">¡Email verificado correctamente!</h3>
                            <p class="text-sm leading-relaxed text-slate-600">Ahora recibirás notificaciones sobre las novedades del torneo.</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 transition hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="mb-3 flex items-start gap-4 rounded-2xl border border-yellow-200 bg-white p-4 shadow-lg" role="alert">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
                            <i class="fa-solid fa-triangle-exclamation text-lg text-yellow-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 text-sm font-bold text-yellow-700">Atención</h3>
                            <p class="text-sm leading-relaxed text-slate-600">{{ session('warning') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 transition hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('success') || session('status'))
                    <div class="mb-3 flex items-start gap-4 rounded-2xl border border-green-200 bg-white p-4 shadow-lg" role="alert">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                            <i class="fa-solid fa-check text-lg text-green-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 text-sm font-bold text-green-700">¡Éxito!</h3>
                            <p class="text-sm leading-relaxed text-slate-600">{{ session('success') ?? session('status') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 transition hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-3 flex items-start gap-4 rounded-2xl border border-red-200 bg-white p-4 shadow-lg" role="alert">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                            <i class="fa-solid fa-circle-xmark text-lg text-red-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 text-sm font-bold text-red-700">Error</h3>
                            <p class="text-sm leading-relaxed text-slate-600">{{ session('error') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 transition hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="mb-3 flex items-start gap-4 rounded-2xl border border-blue-200 bg-white p-4 shadow-lg" role="alert">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                            <i class="fa-solid fa-circle-info text-lg text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 text-sm font-bold text-blue-700">Información</h3>
                            <p class="text-sm leading-relaxed text-slate-600">{{ session('info') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 transition hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @php
            $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion;
        @endphp

        {{-- Hero: buscador protagonista (artboard 06) --}}
        <section class="relative mx-auto max-w-4xl px-4 pt-16 text-center sm:px-6">
            <p class="tm-anim-in font-mono text-xs font-bold uppercase tracking-[0.22em] text-tema-acento">
                {{ optional($edicionActual)->nombre ?? "Torneos Mita'í" }}
            </p>
            <h1 class="tm-anim-in tm-d1 mt-4 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                ¿De qué equipo sos?
            </h1>
            <p class="tm-anim-in tm-d1 mx-auto mt-4 max-w-2xl text-base text-tema-dim/70 sm:text-lg">
                Elegí tu equipo y mirá sus partidos, resultados y posición sin buscar nada.
            </p>

            {{-- Selector de edición: misma función GET que la home anterior --}}
            <form method="GET" action="{{ route('home') }}"
                class="tm-anim-in tm-d1 mt-7 inline-flex items-center gap-3 rounded-full border border-tema-linea/20 bg-[var(--tm-fila-bg)] px-5 py-2.5">
                <label for="idEdicion" class="font-mono text-[11px] font-bold uppercase tracking-[0.18em] text-tema-dim/70">
                    Edición
                </label>
                <select id="idEdicion" name="idEdicion" onchange="this.form.submit()"
                    class="cursor-pointer bg-transparent text-sm font-semibold text-tema-tinta focus:outline-none focus:ring-2 focus:ring-brand-teal-light/60">
                    <option value="" class="text-slate-900">{{ __('Seleccione una edición') }}</option>
                    @foreach ($ediciones as $edicion)
                        <option value="{{ $edicion->id }}" class="text-slate-900"
                            @selected(optional($edicionActual)->id === $edicion->id)>
                            {{ $edicion->nombre }}
                        </option>
                    @endforeach
                </select>
            </form>
        </section>

        @if (isset($equiposDeLaEdicion) && $edicionActual && $equiposDeLaEdicion->isNotEmpty())
            {{-- Buscador del equipo seguido: mismos IDs que consume resources/js/welcome.js --}}
            <section id="seguir-equipo-bloque" data-id-edicion="{{ $edicionActual->id }}" class="relative mx-auto max-w-3xl px-4 pb-16 sm:px-6">
                <div id="seguir-equipo-selector" class="tm-anim-in tm-d2 relative z-50 mx-auto mt-8 max-w-2xl text-left">
                    <label for="seguir-equipo-buscador" class="sr-only">Buscar tu equipo</label>
                    <div class="tm-campo flex items-center gap-3 rounded-[22px] bg-tema-campo-fondo px-5 py-4 text-tema-campo-tinta shadow-[0_30px_70px_-30px_rgba(255,204,51,0.35)] transition-transform duration-300">
                        <svg width="22" height="22" viewBox="0 0 22 22" aria-hidden="true" class="flex-shrink-0">
                            <circle cx="9.5" cy="9.5" r="6.5" fill="none" stroke="#2A6D81" stroke-width="2.2"/>
                            <path d="M14.5 14.5L19 19" stroke="#2A6D81" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                        <input id="seguir-equipo-buscador" type="search" autocomplete="off"
                            placeholder="Buscá tu equipo, ej: Talleres"
                            aria-describedby="seguir-equipo-sin-resultados" aria-expanded="false"
                            class="w-full flex-1 border-0 bg-transparent p-0 text-base font-semibold text-tema-campo-tinta shadow-none placeholder:text-tema-campo-placeholder focus:border-transparent focus:ring-0 sm:text-lg">
                        <kbd class="hidden rounded-md border border-[var(--tm-campo-borde)] px-2 py-1 font-mono text-[11px] font-bold sm:block">Enter</kbd>
                    </div>
                    <ul id="seguir-equipo-lista" role="listbox" aria-label="Equipos que coinciden"
                        class="absolute inset-x-0 top-full z-50 mt-2 hidden overflow-hidden rounded-2xl border border-black/10 bg-white text-slate-900 shadow-2xl">
                    </ul>
                    <p id="seguir-equipo-sin-resultados" class="mt-3 hidden text-sm text-tema-dim/70">
                        No encontramos equipos con ese nombre.
                    </p>
                </div>
                <p class="tm-anim-in tm-d2 mt-4 text-center text-sm text-tema-dim/70">
                    También podés explorar todo el torneo abajo.
                </p>

                {{-- Panel del equipo seguido: lo arma resources/js/welcome.js con /equipo-seguido --}}
                <div id="seguir-equipo-card" aria-live="polite" class="mx-auto mt-8 hidden max-w-2xl"></div>

                <script type="application/json" id="seguir-equipo-datos">@json($equiposDeLaEdicion->values())</script>
            </section>
        @endif

        {{-- Accesos al torneo --}}
        @if ($edicionActual)
            <section aria-label="Accesos al torneo" class="relative mx-auto max-w-2xl px-4 pb-24 sm:px-6">
                <h2 class="tm-anim-in tm-d4 mb-4 text-xs font-bold uppercase tracking-[0.18em] text-tema-acento">
                    O explorá todo el torneo
                </h2>
                <div class="tm-anim-in tm-d4 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('tabla-posiciones', ['idEdicion' => $edicionActual->id]) }}"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-5 py-4 text-sm font-semibold transition hover:bg-tema-superficie/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light">
                        Tabla de posiciones
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                            <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="{{ route('tabla-goleadores', ['idEdicion' => $edicionActual->id]) }}"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-5 py-4 text-sm font-semibold transition hover:bg-tema-superficie/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light">
                        Goleadores
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                            <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="{{ route('instancias-finales', ['idEdicion' => $edicionActual->id]) }}"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-5 py-4 text-sm font-semibold transition hover:bg-tema-superficie/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light">
                        Finales
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                            <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="{{ route('tabla-vallas', ['idEdicion' => $edicionActual->id]) }}"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-5 py-4 text-sm font-semibold transition hover:bg-tema-superficie/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light">
                        Vallas
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                            <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </section>
        @endif
    </main>

    {{-- Tab bar móvil (artboard 07): oculta desde sm --}}
    <nav class="tabbar-movil sticky bottom-0 z-30 flex items-center justify-around border-t border-tema-linea/15 bg-tema-fondo pb-[calc(10px+env(safe-area-inset-bottom))] pt-2.5 sm:hidden"
        aria-label="Navegación inferior">
        <a href="{{ route('home') }}" aria-current="page">
            <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M13 13l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Buscar
        </a>
        @if (optional($edicionActual)->id)
            <a href="{{ route('fixture', ['idEdicion' => $edicionActual->id]) }}">
                <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 8.5h14M7 2.5v3M13 2.5v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Fixture
            </a>
            <a href="{{ route('tabla-posiciones', ['idEdicion' => $edicionActual->id]) }}">
                <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 17V9M10 17V4M17 17v-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Tablas
            </a>
            <a href="{{ route('campeones', ['idEdicion' => $edicionActual->id]) }}">
                <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M6 17h8M8 13h4M10 3l1.6 3.3 3.6.5-2.6 2.6.6 3.6L10 11.2 6.8 13l.6-3.6L4.8 6.8l3.6-.5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                Campeones
            </a>
        @endif
    </nav>

    <footer class="border-t border-tema-linea/15 bg-black/80 text-white">
        <div class="mx-auto max-w-6xl px-4 py-8 text-center sm:px-6 lg:px-8">
            <p class="text-sm md:text-base">&copy; Mita'í {{ date('Y') }}. Todos los derechos reservados.</p>
            <p class="mt-2 text-sm md:text-base">Sitio desarrollado por <span class="font-semibold">Ing. Facundo Díaz Domínguez</span>.</p>
            <p class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="https://www.instagram.com/torneos.mitaii/" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold transition hover:-translate-y-0.5 hover:bg-white/20">
                    <i class="fa-brands fa-instagram"></i> Instagram
                </a>
                <a href="https://www.facebook.com/ligamitaijb" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold transition hover:-translate-y-0.5 hover:bg-white/20">
                    <i class="fa-brands fa-facebook"></i> Facebook
                </a>
                <a href="https://www.linkedin.com/in/facundo-diaz-dominguez-0a826b230" target="_blank" rel="noopener noreferrer" lang="es"
                    class="inline-flex items-center gap-2 rounded-full border border-blue-400/40 bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-300 transition hover:-translate-y-0.5 hover:bg-blue-500/20">
                    <i class="fa-brands fa-linkedin"></i> Conéctate conmigo en LinkedIn
                </a>
            </p>
        </div>
    </footer>
</body>

</html>
