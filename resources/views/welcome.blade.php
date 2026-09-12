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
                    <div class="rounded-xl bg-red-500/10 border border-red-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down my-3" role="alert">
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
                    <div class="rounded-xl bg-blue-500/10 border border-blue-500/30 p-4 flex items-start sm:items-center gap-4 animate-fade-in-down my-3" role="alert">
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

        <section class="relative overflow-hidden">
            <div class="absolute inset-0">
                <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                    class="h-full w-full object-cover object-center" fetchpriority="high" decoding="async">
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/85 via-black/75 to-slate-950"></div>
                <div class="absolute -left-24 top-16 h-72 w-72 rounded-full bg-yellow-300/20 blur-3xl"></div>
                <div class="absolute -right-20 bottom-10 h-80 w-80 rounded-full bg-emerald-400/20 blur-3xl"></div>
            </div>
            <div
                class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 flex flex-col lg:flex-row gap-10 lg:gap-12">
                <div class="w-full lg:w-7/12 space-y-6 text-white text-center lg:text-left">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        fútbol formativo
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black leading-tight">
                        Viví la pasión de Mita'í en cada categoría
                    </h1>
                    <p class="text-base sm:text-lg text-white/80 max-w-xl mx-auto lg:mx-0">
                        Descubrí fixtures actualizados, instancias decisivas y a los protagonistas de la
                        {{ optional($edicionActual)->nombre ?? 'vigente' }}. Elegí tu categoría favorita y seguí el
                        torneo desde cualquier dispositivo.
                    </p>
                    <div class="flex flex-wrap justify-center gap-2 lg:justify-start">
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                            <i class="fa-solid fa-signal"></i>
                            Actualización en vivo
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                            <i class="fa-solid fa-users"></i>
                            Comunidad Mita'í
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-yellow-300/40 bg-yellow-300/10 px-3 py-1 text-xs font-semibold text-yellow-100">
                            <i class="fa-solid fa-shield-heart"></i>
                            Formación y valores
                        </span>
                    </div>
                    <div class="flex flex-wrap justify-center gap-3 lg:justify-start">
                        @if ($edicionActual)
                            <a href="{{ route('fixture', ['idEdicion' => $edicionActual->id]) }}"
                                class="group flex items-center gap-2 rounded-full bg-yellow-300 px-5 py-2.5 text-sm font-semibold text-black shadow-lg shadow-yellow-500/35 ring-1 ring-yellow-200/80 transition-transform hover:-translate-y-1 hover:bg-yellow-200">
                                <i class="fa-solid fa-calendar-days text-base"></i>
                                Fase de Grupos
                            </a>
                            <a href="{{ route('instancias-finales', ['idEdicion' => $edicionActual->id]) }}"
                                class="group flex items-center gap-2 rounded-full bg-rose-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/40 ring-1 ring-rose-300/40 transition-transform hover:-translate-y-1 hover:bg-rose-500">
                                <i class="fa-solid fa-trophy text-base"></i>
                                Instancias Finales
                            </a>
                            <a href="{{ route('tabla-goleadores', ['idEdicion' => $edicionActual->id]) }}"
                                class="group flex items-center gap-2 rounded-full border border-white/40 bg-black/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-1 hover:border-white hover:bg-white/10">
                                <i class="fa-solid fa-futbol text-base"></i>
                                Goleadores al día
                            </a>
                            <a href="{{ route('tabla-vallas', ['idEdicion' => $edicionActual->id]) }}"
                                class="group flex items-center gap-2 rounded-full border border-emerald-400/60 bg-black/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-1 hover:border-emerald-300 hover:bg-emerald-500/20">
                                <i class="fa-solid fa-shield text-base text-emerald-300"></i>
                                Vallas al día
                            </a>
                        @endif
                    </div>
                </div>
                <div class="w-full lg:w-5/12 max-w-xl mx-auto lg:mx-0">
                    <div class="rounded-3xl border border-white/15 bg-gradient-to-br from-white/15 to-white/5 p-6 sm:p-8 shadow-2xl backdrop-blur">
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-yellow-300/90 text-black text-xl font-bold shadow-lg shadow-yellow-500/40">
                                <i class="fa-solid fa-layer-group"></i>
                            </span>
                            <div>
                                <p class="text-sm uppercase tracking-widest text-white/70">selecciona tu edición</p>
                                <h2 class="text-2xl font-bold text-white">Organizá tu experiencia</h2>
                            </div>
                        </div>
                        <form method="GET" action="{{ route('home') }}" class="mt-6 space-y-4">
                            <div>
                                <label for="idEdicion" class="text-sm font-semibold text-white/80">Edición</label>
                                <div class="relative mt-2">
                                    <select id="idEdicion" name="idEdicion" onchange="this.form.submit()"
                                        class="w-full appearance-none rounded-2xl border border-white/20 bg-slate-950/70 px-4 py-3 text-sm font-medium text-white shadow-inner shadow-black/40 focus:border-yellow-300 focus:outline-none focus:ring focus:ring-yellow-300/30">
                                        <option value="">Seleccione una edición</option>
                                        @foreach ($ediciones as $edicion)
                                            <option value="{{ $edicion->id }}" {{ optional($edicionActual)->id === $edicion->id ? 'selected' : '' }}>
                                                {{ $edicion->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span
                                        class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-white/60">
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </span>
                                </div>
                            </div>
                            <p class="text-xs text-white/60">
                                Elegí la edición para filtrar la información y acceder rápidamente a fixtures, tablas y
                                los protagonistas.
                            </p>
                        </form>
                        <div class="mt-6 grid gap-3 text-sm">
                            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-black/45 px-4 py-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-400/90 text-white">
                                    <i class="fa-solid fa-ranking-star"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-white">Tablas actualizadas</p>
                                    <p class="text-xs text-white/60">Conocé posiciones, goleadores y vallas destacadas.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-black/45 px-4 py-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-400/90 text-white">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-white">Comparte emociones</p>
                                    <p class="text-xs text-white/60">Enviá links directos a tus amistades y familia.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

        @if ($categorias->count() && $edicionActual)
            <section class="relative overflow-hidden bg-gradient-to-b from-slate-950 via-black to-slate-900 py-14 sm:py-16">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"></div>
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-2">
                            <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70">
                                <i class="fa-solid fa-bolt text-yellow-300"></i>
                                accesos rápidos
                            </p>
                            <h2 class="text-2xl sm:text-3xl font-bold text-white">Accesos directos</h2>
                            <p class="text-sm sm:text-base text-white/60 max-w-2xl">Ingresá en segundos a los apartados más
                                consultados del torneo y reviví cada momento decisivo.</p>
                        </div>
                        <a href="{{ route('fixture', ['idEdicion' => $edicionActual->id]) }}"
                            class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white/70 transition hover:border-white hover:bg-white/10">
                            Ver todo el fixture
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                        </a>
                    </div>
                    <div class="mt-8 flex gap-3 overflow-x-auto pb-3 [-ms-overflow-style:'none'] [scrollbar-width:none]"
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

        <section class="bg-gradient-to-t from-slate-950 via-black to-slate-900 py-14 sm:py-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
                    <div class="space-y-3">
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70">
                            <i class="fa-solid fa-medal text-yellow-300"></i>
                            legado del torneo
                        </p>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white">Historial</h2>
                        <p class="text-sm sm:text-base text-white/60 max-w-2xl">Recorré a los protagonistas que hicieron historia en cada edición y reviví el camino de los equipos más destacados.</p>
                    </div>
                </div>
                <div
                    class="mt-6 -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 xl:grid-cols-4">
                    <a href="{{ $edicionActual ? route('campeones', ['idEdicion' => $edicionActual->id]) : route('campeones', ['idEdicion' => optional($ultimaEdicion)->id]) }}"
                        class="group relative flex min-w-[260px] snap-start flex-col isolate transform-gpu overflow-hidden rounded-3xl border border-yellow-200/60 bg-gradient-to-br from-yellow-500/95 via-yellow-100/90 to-yellow-500/85 p-6 text-black shadow-[0_24px_60px_-30px_rgba(253,224,71,0.9)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[0_32px_70px_-28px_rgba(248,220,82,1)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-yellow-200 sm:min-w-0 sm:h-full">
                        <div
                            class="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-yellow-300 blur-3xl transition-opacity duration-300 group-hover:opacity-70">
                        </div>
                        <div class="flex h-full flex-col justify-between">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-yellow-200/60 text-2xl">
                                <i class="fa-solid fa-trophy"></i>
                            </div>
                            <div class="mt-6 space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] ">Historial</p>
                                <h3 class="text-2xl font-bold">Campeones</h3>
                                <p class="mt-2 text-sm">Reviví los equipos que levantaron la copa.</p>
                            </div>
                            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold">Ver más<i
                                    class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                        </div>
                    </a>
                    <a href="{{ $edicionActual ? route('subcampeones', ['idEdicion' => $edicionActual->id]) : route('subcampeones', ['idEdicion' => optional($ultimaEdicion)->id]) }}"
                        class="group relative flex min-w-[260px] snap-start flex-col isolate transform-gpu overflow-hidden rounded-3xl border border-gray-200/60 bg-gradient-to-br from-gray-500/95 via-gray-200/90 to-gray-500/90 p-6 text-slate-900 shadow-[0_20px_55px_-35px_rgba(148,163,184,0.9)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[0_28px_65px_-30px_rgba(16,185,129,0.35)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gray-200 sm:min-w-0 sm:h-full">
                        <div
                            class="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-gray-300 blur-3xl transition-opacity duration-300 group-hover:opacity-90">
                        </div>
                        <div class="flex h-full flex-col justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-200/60 text-2xl">
                                <i class="fa-solid fa-medal"></i>
                            </div>
                            <div class="mt-6 space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 text-black">
                                    Podio</p>
                                <h3 class="text-2xl font-bold text-black">Subcampeones</h3>
                                <p class="mt-2 text-sm text-slate-700 text-black">Los equipos que dejaron todo en la
                                    cancha.</p>
                            </div>
                            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-900 ">Ver
                                más<i
                                    class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                        </div>
                    </a>
                    <a href="{{ $edicionActual ? route('vallas', ['idEdicion' => $edicionActual->id]) : route('vallas', ['idEdicion' => optional($ultimaEdicion)->id]) }}"
                        class="group relative flex min-w-[260px] snap-start flex-col isolate transform-gpu overflow-hidden rounded-3xl border border-green-200/60 bg-gradient-to-br from-green-500/95 via-green-200/90 to-green-500/85 p-6 text-emerald-950 shadow-[0_24px_60px_-30px_rgba(16,185,129,0.6)] transition-transform duration-300 hover:-translate-y-1 hover:border-emerald-500/60 hover:shadow-[0_30px_70px_-28px_rgba(16,185,129,0.75)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-300 sm:min-w-0 sm:h-full">
                        <div
                            class="absolute -right-14 -top-14 h-32 w-32 rounded-full bg-green-300 blur-3xl transition-opacity duration-300 group-hover:opacity-80">
                        </div>
                        <div class="flex h-full flex-col justify-between">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-200/60 text-2xl">
                                <img src="{{ asset('fotos/guante-arquero.png') }}" alt="Guante arquero"
                                    class="h-9 w-9 object-contain" loading="lazy" width="36" height="36">
                            </div>
                            <div class="mt-6 space-y-1">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-700/80 text-black">
                                    Defensa</p>
                                <h3 class="text-2xl font-bold text-black">Vallas menos vencidas</h3>
                                <p class="mt-2 text-sm text-emerald-900/80 text-black">Los arqueros que brillan en cada
                                    fecha.</p>
                            </div>
                            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-black">Ver más<i
                                    class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                        </div>
                    </a>
                    <a href="{{ $edicionActual ? route('goleadores', ['idEdicion' => $edicionActual->id]) : route('goleadores', ['idEdicion' => optional($ultimaEdicion)->id]) }}"
                        class="group relative flex min-w-[260px] snap-start flex-col isolate transform-gpu overflow-hidden rounded-3xl border border-blue-200/60 bg-gradient-to-br from-blue-500/95 via-blue-200/90 to-blue-500/85 p-6 text-blue-950 shadow-[0_24px_60px_-30px_rgba(56,189,248,0.65)] transition-transform duration-300 hover:-translate-y-1 hover:border-sky-400/60 hover:shadow-[0_32px_75px_-28px_rgba(59,130,246,0.75)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-300 sm:min-w-0 sm:h-full">
                        <div
                            class="absolute -right-14 -top-14 h-32 w-32 rounded-full bg-blue-300 blur-3xl transition-opacity duration-300 group-hover:opacity-85">
                        </div>
                        <div class="flex h-full flex-col justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-200/60 text-2xl">
                                <img src="{{ asset('fotos/Botin-con-pelota.png') }}" alt="Botín con pelota"
                                    class="h-10 w-10 object-contain" loading="lazy" width="40" height="40">
                            </div>
                            <div class="mt-6 space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-black">Ataque</p>
                                <h3 class="text-2xl font-bold text-black">Goleadores</h3>
                                <p class="mt-2 text-sm text-black">Reviví a los máximos artilleros del torneo.</p>
                            </div>
                            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-blue-950">Ver
                                más<i
                                    class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i></span>
                        </div>
                    </a>
                </div>
            </div>
        </section>



        <section class="relative overflow-hidden bg-slate-950 py-14 sm:py-16">
            <div class="absolute left-1/2 top-0 h-px w-[90%] -translate-x-1/2 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="space-y-4 text-white/90">
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70">
                            <i class="fa-solid fa-heart text-rose-300"></i>
                            comunidad
                        </p>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white">Sumate a la comunidad Mita'í</h2>
                        <p class="text-sm sm:text-base text-white/60">Seguinos en redes y recibí las novedades de
                            fixture, resultados, tablas y transmisiones en vivo. Compartimos historias, fotos y momentos
                            únicos de cada jornada.</p>
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
                    <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-7 text-white shadow-2xl shadow-black/35">
                        <div
                            class="absolute -right-16 top-1/2 h-44 w-44 -translate-y-1/2 rounded-full bg-yellow-400/20 blur-3xl">
                        </div>
                        <div class="flex items-center gap-4">
                            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo Torneo Mita'í"
                                class="h-20 w-20 object-contain" loading="lazy" width="80" height="80">
                            <div>
                                <p class="text-sm uppercase tracking-widest text-white/60">Sobre el torneo</p>
                                <h3 class="text-xl font-semibold text-white">Formando talentos con pasión</h3>
                            </div>
                        </div>
                        <p class="mt-5 text-sm text-white/70 leading-relaxed">
                            Mita'í reúne a cientos de chicos y chicas que sueñan con vivir el fútbol de manera
                            profesional. Nuestro compromiso es ofrecer un entorno seguro, competitivo y con estadísticas
                            transparentes para familias, clubes y amantes del deporte.
                        </p>
                        <ul class="mt-5 grid gap-3 text-sm text-white/70 sm:grid-cols-2">
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
