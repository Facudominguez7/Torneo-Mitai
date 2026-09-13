<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="bg-slate-950 text-white antialiased">
    @include('layouts.navigation')
    <main>
        {{-- Alertas del sistema --}}
        @if (session()->has('warning') || session()->has('success') || session()->has('error') || session()->has('info') || session()->has('status') || request()->query('verified') == '1')
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-2">
                {{-- Alerta de email verificado exitosamente --}}
                @if(request()->query('verified') == '1')
                    <div class="rounded-xl bg-green-500/10 border border-green-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down my-3" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-envelope-circle-check text-green-400 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-green-400 mb-1">¡Email verificado correctamente! 🎉</h3>
                            <p class="text-sm text-green-100/90 leading-relaxed">Ahora recibirás notificaciones sobre las novedades del torneo.</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-400/60 hover:text-green-400 transition">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="rounded-xl bg-yellow-500/10 border border-yellow-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-triangle-exclamation text-yellow-400 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-yellow-400 mb-1">Atención</h3>
                            <p class="text-sm text-yellow-100/90 leading-relaxed">{{ session('warning') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-yellow-400/60 hover:text-yellow-400 transition">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('success') || session('status'))
                    <div class="rounded-xl bg-green-500/10 border border-green-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down my-3" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-check text-green-400 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-green-400 mb-1">¡Éxito!</h3>
                            <p class="text-sm text-green-100/90 leading-relaxed">{{ session('success') ?? session('status') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-400/60 hover:text-green-400 transition">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-xl bg-red-500/10 border border-red-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-circle-xmark text-red-400 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-red-400 mb-1">Error</h3>
                            <p class="text-sm text-red-100/90 leading-relaxed">{{ session('error') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-400/60 hover:text-red-400 transition">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="rounded-xl bg-blue-500/10 border border-blue-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-circle-info text-blue-400 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-blue-400 mb-1">Información</h3>
                            <p class="text-sm text-blue-100/90 leading-relaxed">{{ session('info') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-blue-400/60 hover:text-blue-400 transition">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @php
            $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion;
        @endphp

        {{-- Hero mínimo: edición actual + selector + 2 CTAs --}}
        <section class="relative overflow-hidden">
            <div class="absolute inset-0">
                <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                    class="h-full w-full object-cover object-center" fetchpriority="high" decoding="async">
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/85 via-black/75 to-slate-950"></div>
                <div class="absolute -left-24 top-16 h-72 w-72 rounded-full bg-yellow-300/20 blur-3xl"></div>
                <div class="absolute -right-20 bottom-10 h-80 w-80 rounded-full bg-emerald-400/20 blur-3xl"></div>
            </div>
            <div
                class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 flex flex-col gap-8 text-white text-center lg:text-left">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                    {{ optional($edicionActual)->nombre ?? "Torneos Mita'í" }}
                </h1>
                    <form method="GET" action="{{ route('home') }}" class="mx-auto w-full max-w-sm lg:mx-0">
                        <label for="idEdicion" class="text-sm font-semibold text-white/80">Edición</label>
                        <div class="relative mt-2">
                            <select id="ediciones-select" name="idEdicion" onchange="this.form.submit()"
                                class="w-full appearance-none rounded-2xl border border-[#f7f280]/40 bg-slate-950/90 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-black/30 transition hover:border-[#f7f280] focus:border-[#f7f280] focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                                <option value="" class="bg-slate-950">Seleccione una edición</option>
                                @foreach ($ediciones as $edicion)
                                    <option value="{{ $edicion->id }}" class="bg-slate-950" {{ optional($edicionActual)->id === $edicion->id ? 'selected' : '' }}>
                                        {{ $edicion->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-[#f7f280]">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
                        </div>
                    </form>
                @if ($edicionActual)
                    <div class="flex flex-wrap justify-center gap-3 lg:justify-start">
                        <a href="{{ route('fixture', ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex items-center gap-2 rounded-full bg-yellow-300 px-5 py-2.5 text-sm font-semibold text-black shadow-lg shadow-yellow-500/35 ring-1 ring-yellow-200/80 transition-transform hover:-translate-y-1 hover:bg-yellow-200">
                            <i class="fa-solid fa-calendar-days text-base"></i>
                            Ver partidos
                        </a>
                    </div>
                @endif
            </div>
        </section>

        
        @if (isset($equiposDeLaEdicion) && $edicionActual && $equiposDeLaEdicion->isNotEmpty())
            <section id="seguir-equipo-bloque" data-id-edicion="{{ $edicionActual->id }}"
                class="bg-gradient-to-b from-slate-950 via-black to-slate-900 py-14 sm:py-16">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div
                        class="max-w-2xl mx-auto rounded-3xl border border-white/15 bg-gradient-to-br from-white/10 to-white/5 p-6 sm:p-8 shadow-2xl backdrop-blur">
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-400/90 text-white text-xl shadow-lg shadow-emerald-500/40">
                                <i class="fa-solid fa-star"></i>
                            </span>
                            <div>
                                <p class="text-sm uppercase tracking-widest text-white/70">tu equipo, siempre a la
                                    vista</p>
                                <h2 class="text-2xl font-bold text-white">Seguir mi equipo</h2>
                            </div>
                        </div>
                        <div id="seguir-equipo-selector" class="mt-6">
                            <label for="seguir-equipo-buscador" class="text-sm font-semibold text-white/80">
                                Elegí tu equipo de {{ optional($edicionActual)->nombre }}
                            </label>
                            <input type="search" id="seguir-equipo-buscador" autocomplete="off"
                                placeholder="Escribí el nombre del equipo..."
                                class="mt-2 w-full rounded-2xl border border-white/20 bg-slate-950/70 px-4 py-3 text-sm font-medium text-white placeholder-white/40 shadow-inner shadow-black/40 focus:border-emerald-300 focus:outline-none focus:ring focus:ring-emerald-300/30">
                            <ul id="seguir-equipo-lista" class="mt-3 grid max-h-72 gap-2 overflow-y-auto pr-1"></ul>
                            <p id="seguir-equipo-sin-resultados" class="mt-3 hidden text-sm text-white/60">
                                No encontramos equipos con ese nombre.
                            </p>
                        </div>
                        <div id="seguir-equipo-card" aria-live="polite" class="mt-6 hidden"></div>
                    </div>
                </div>
                <script type="application/json" id="seguir-equipo-datos">@json($equiposDeLaEdicion->values())</script>
            </section>
        @endif

        {{-- Accesos secundarios: 4 chips --}}
        @if ($edicionActual)
            <section id="accesos-secundarios" class="bg-gradient-to-b from-slate-950 via-black to-slate-900 py-14 sm:py-16">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <a href="{{ route('tabla-posiciones', ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex flex-col items-center gap-2 rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 px-5 py-5 text-white transition hover:-translate-y-1 hover:border-yellow-300/70 hover:bg-yellow-300/10">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-yellow-300/80 text-black">
                                <i class="fa-solid fa-ranking-star"></i>
                            </span>
                            <span class="text-sm font-semibold uppercase tracking-widest">Posiciones</span>
                        </a>
                        <a href="{{ route('tabla-goleadores', ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex flex-col items-center gap-2 rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 px-5 py-5 text-white transition hover:-translate-y-1 hover:border-sky-400/70 hover:bg-sky-400/10">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-400/80 text-black">
                                <i class="fa-solid fa-futbol"></i>
                            </span>
                            <span class="text-sm font-semibold uppercase tracking-widest">Goleadores</span>
                        </a>
                        <a href="{{ route('instancias-finales', ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex flex-col items-center gap-2 rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 px-5 py-5 text-white transition hover:-translate-y-1 hover:border-rose-400/70 hover:bg-rose-400/10">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-rose-500/90 text-white">
                                <i class="fa-solid fa-trophy"></i>
                            </span>
                            <span class="text-sm font-semibold uppercase tracking-widest">Finales</span>
                        </a>
                        <a href="{{ route('tabla-vallas', ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex flex-col items-center gap-2 rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 px-5 py-5 text-white transition hover:-translate-y-1 hover:border-emerald-300/70 hover:bg-emerald-500/10">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-400/90 text-white">
                                <i class="fa-solid fa-shield"></i>
                            </span>
                            <span class="text-sm font-semibold uppercase tracking-widest">Vallas</span>
                        </a>
                    </div>
                </div>
            </section>
        @endif

        @if ($categorias->count() && $edicionActual)
            <section class="relative overflow-hidden bg-gradient-to-b from-slate-950 via-black to-slate-900 py-14 sm:py-16">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"></div>
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex gap-3 overflow-x-auto pb-3 [-ms-overflow-style:'none'] [scrollbar-width:none]"
                        style="scrollbar-width: none;">
                        @foreach ($categorias as $categoria)
                            <a href="{{ route('fixture', ['idEdicion' => $edicionActual->id, 'idCategoria' => $categoria->id]) }}"
                                class="group flex min-w-[200px] flex-col justify-between rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 px-5 py-4 text-white transition hover:-translate-y-1 hover:border-yellow-300/70 hover:bg-yellow-300/10">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-yellow-300/80 text-black">
                                        <i class="fa-solid fa-shirt"></i>
                                    </span>
                                    <div class="text-left">
                                        <p class="text-xs uppercase tracking-widest text-white/50">Categoría</p>
                                        <p class="text-lg font-semibold">{{ $categoria->nombreCategoria }}</p>
                                    </div>
                                </div>
                                <span
                                    class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-white/60">Ir
                                    al fixture<i
                                        class="fa-solid fa-arrow-right text-[10px] transition-transform group-hover:translate-x-1"></i></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Historial reducido a links --}}
        <section class="bg-gradient-to-t from-slate-950 via-black to-slate-900 py-14 sm:py-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                    <a href="{{ route('campeones', ['idEdicion' => $edicionActual?->id ?? $ultimaEdicion?->id]) }}"
                        class="group flex flex-col gap-3 rounded-3xl border border-yellow-200/60 bg-gradient-to-br from-yellow-500/95 via-yellow-100/90 to-yellow-500/85 p-6 text-black shadow-[0_24px_60px_-30px_rgba(253,224,71,0.9)] transition-transform duration-300 hover:-translate-y-1">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-yellow-200/60 text-2xl">
                            <i class="fa-solid fa-trophy"></i>
                        </span>
                        <h3 class="text-xl font-bold">Campeones</h3>
                        <span class="mt-auto inline-flex items-center gap-2 text-sm font-semibold">Ver más<i
                                class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                    </a>
                    <a href="{{ route('subcampeones', ['idEdicion' => $edicionActual?->id ?? $ultimaEdicion?->id]) }}"
                        class="group flex flex-col gap-3 rounded-3xl border border-gray-200/60 bg-gradient-to-br from-gray-500/95 via-gray-200/90 to-gray-500/90 p-6 text-slate-900 shadow-[0_20px_55px_-35px_rgba(148,163,184,0.9)] transition-transform duration-300 hover:-translate-y-1">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-200/60 text-2xl">
                            <i class="fa-solid fa-medal"></i>
                        </span>
                        <h3 class="text-xl font-bold text-black">Subcampeones</h3>
                        <span class="mt-auto inline-flex items-center gap-2 text-sm font-semibold text-slate-900">Ver más<i
                                class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                    </a>
                    <a href="{{ route('vallas', ['idEdicion' => $edicionActual?->id ?? $ultimaEdicion?->id]) }}"
                        class="group flex flex-col gap-3 rounded-3xl border border-green-200/60 bg-gradient-to-br from-green-500/95 via-green-200/90 to-green-500/85 p-6 text-emerald-950 shadow-[0_24px_60px_-30px_rgba(16,185,129,0.6)] transition-transform duration-300 hover:-translate-y-1">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-200/60">
                            <img src="{{ asset('fotos/guante-arquero.png') }}" alt="Guante arquero"
                                class="h-9 w-9 object-contain" loading="lazy" width="36" height="36">
                        </span>
                        <h3 class="text-xl font-bold text-black">Vallas menos vencidas</h3>
                        <span class="mt-auto inline-flex items-center gap-2 text-sm font-semibold text-black">Ver más<i
                                class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                    </a>
                    <a href="{{ route('goleadores', ['idEdicion' => $edicionActual?->id ?? $ultimaEdicion?->id]) }}"
                        class="group flex flex-col gap-3 rounded-3xl border border-blue-200/60 bg-gradient-to-br from-blue-500/95 via-blue-200/90 to-blue-500/85 p-6 text-blue-950 shadow-[0_24px_60px_-30px_rgba(56,189,248,0.65)] transition-transform duration-300 hover:-translate-y-1">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-200/60">
                            <img src="{{ asset('fotos/Botin-con-pelota.png') }}" alt="Botín con pelota"
                                class="h-10 w-10 object-contain" loading="lazy" width="40" height="40">
                        </span>
                        <h3 class="text-xl font-bold text-black">Goleadores</h3>
                        <span class="mt-auto inline-flex items-center gap-2 text-sm font-semibold text-blue-950">Ver más<i
                                class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                    </a>
                </div>
            </div>
        </section>

        {{-- Comunidad + institucional recortado a bullets --}}
        <section class="relative overflow-hidden bg-slate-950 py-14 sm:py-16">
            <div class="absolute left-1/2 top-0 h-px w-[90%] -translate-x-1/2 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="space-y-4 text-white/90">
                        <h2 class="text-2xl sm:text-3xl font-bold text-white">Sumate a la comunidad Mita'í</h2>
                        <div class="flex flex-wrap gap-3">
                            <a href="https://www.instagram.com/torneos.mitaii/" target="_blank"
                                rel="noopener noreferrer"
                                class="group inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-pink-500 via-red-500 to-yellow-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-pink-500/30 transition-transform hover:-translate-y-1">
                                <i class="fa-brands fa-instagram text-base"></i>
                                Instagram
                            </a>
                            <a href="https://www.facebook.com/ligamitaijb" target="_blank" rel="noopener noreferrer"
                                class="group inline-flex items-center gap-2 rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition-transform hover:-translate-y-1">
                                <i class="fa-brands fa-facebook text-base"></i>
                                Facebook
                            </a>
                            <a href="mailto:info@torneosmitai.com"
                                class="group inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-1 hover:border-white hover:bg-white/10">
                                <i class="fa-solid fa-envelope text-base"></i>
                                Contacto directo
                            </a>
                        </div>
                    </div>
                    <ul class="grid content-center gap-3 text-sm text-white/70 sm:grid-cols-2">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-400"></i>
                            Partidos organizados por categorías y jornadas
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-400"></i>
                            Estadísticas y rankings actualizados
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-400"></i>
                            Cobertura multimedia y fotos oficiales
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-400"></i>
                            Comunidad activa de familias y clubes
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
    <footer class="mt-10 border-t border-white/10 bg-black/80">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-center text-white/80">
            <p class="text-sm md:text-base">&copy; Mita'í {{ date('Y') }}. Todos los derechos reservados.</p>
            <p class="mt-2 text-sm md:text-base">Sitio desarrollado por <span class="font-semibold text-white">Ing. Facundo Díaz
                    Domínguez</span>.</p>
            <p class="mt-4">
                <a href="https://www.linkedin.com/in/facundo-diaz-dominguez-0a826b230"
                    class="inline-flex items-center gap-2 rounded-full border border-blue-400/40 bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-300 transition hover:-translate-y-0.5 hover:bg-blue-500/20"
                    target="_blank" rel="noopener noreferrer" lang="es">
                    <i class="fa-brands fa-linkedin"></i>
                    Conéctate conmigo en LinkedIn
                </a>
            </p>
        </div>
    </footer>
</body>

</html>
