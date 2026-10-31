@section('tituloSeo', 'Torneo Mitai | Fútbol Infantil')
@section('descripcionSeo', 'Fixture, tablas de posiciones, goleadores y campeones del Torneo Mitai de fútbol infantil')
<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home min-h-screen bg-tema-fondo text-tema-tinta antialiased transition-colors duration-300">
    @include('layouts.navigation')
    <main class="tm-velo-host relative">
        <x-marca-agua-portal />

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

        @if (session('suscripcion') || $errors->suscripcion->any())
            <div id="suscripcion-aviso" class="mx-auto max-w-6xl px-4 pt-6 sm:px-6 lg:px-8">
                @if (session('suscripcion'))
                    <x-acceso.aviso tipo="exito">{{ session('suscripcion') }}</x-acceso.aviso>
                @else
                    <x-acceso.aviso tipo="error">
                        <a href="#seguir-equipo-bloque" class="underline underline-offset-4">Revisá los datos de la suscripción.</a>
                    </x-acceso.aviso>
                @endif
            </div>
        @endif

        @php
            $edicionActual = $EdicionSeleccionada ?? $ultimaEdicion;
        @endphp

        {{-- Selector de edición: dropdown diseñado (artboard 06); welcome.js navega a data-url --}}
        <div class="mx-auto max-w-4xl px-4 pt-16 text-center sm:px-6">
            <div id="selector-edicion" class="tm-selector-edicion tm-anim-in tm-d1">
                <span id="etiqueta-edicion"
                    class="font-mono text-[11px] font-bold uppercase tracking-[0.18em] text-tema-dim/70">
                    {{ __('Edición') }}
                </span>
                <button type="button" id="idEdicion" class="tm-selector-disparador"
                    aria-haspopup="listbox" aria-expanded="false"
                    aria-labelledby="etiqueta-edicion idEdicion">
                    <span class="tm-selector-texto">{{ optional($edicionActual)->nombre ?? __('Seleccione una edición') }}</span>
                    <svg class="tm-selector-flecha" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M3 5l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <ul id="lista-ediciones" class="tm-selector-lista" role="listbox"
                    aria-label="{{ __('Seleccionar edición del torneo') }}">
                    <li role="option" id="opcion-edicion-vacia" data-url="{{ route('home') }}"
                        aria-selected="{{ $edicionActual ? 'false' : 'true' }}">
                        {{ __('Seleccione una edición') }}
                        <svg class="tm-selector-check" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M2.5 7.5l3 3 6-6.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </li>
                    @foreach ($ediciones as $edicion)
                        <li role="option" id="opcion-edicion-{{ $edicion->id }}"
                            data-url="{{ route('home', ['idEdicion' => $edicion->id]) }}"
                            aria-selected="{{ optional($edicionActual)->id === $edicion->id ? 'true' : 'false' }}">
                            {{ $edicion->nombre }}
                            <svg class="tm-selector-check" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M2.5 7.5l3 3 6-6.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Hero: buscador protagonista (artboard 06) --}}
        <section class="relative mx-auto max-w-4xl px-4 text-center sm:px-6">
            <h1 class="tm-anim-in tm-d1 mt-4 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                ¿De qué equipo sos?
            </h1>
            <p class="tm-anim-in tm-d1 mx-auto mt-4 max-w-2xl text-base text-tema-dim/70 sm:text-lg">
                Elegí tu equipo y mirá sus partidos, resultados y posición sin buscar nada.
            </p>
        </section>

        @if (isset($equiposDeLaEdicion) && $edicionActual && $equiposDeLaEdicion->isNotEmpty())
            {{-- Buscador del equipo seguido: mismos IDs que consume resources/js/welcome.js --}}
            <section id="seguir-equipo-bloque" data-id-edicion="{{ $edicionActual->id }}" class="relative mx-auto max-w-3xl px-4 pb-16 sm:px-6">
                <div id="seguir-equipo-selector" class="tm-anim-in tm-d2 relative z-50 mx-auto mt-8 max-w-2xl text-left">
                    <label for="seguir-equipo-buscador" class="sr-only">Buscar tu equipo</label>
                    <div class="tm-campo flex items-center gap-3 rounded-[22px] bg-tema-campo-fondo px-5 py-4 text-tema-campo-tinta shadow-[0_30px_70px_-30px_rgba(255,204,51,0.35)] transition-transform duration-300">
                        <svg width="22" height="22" viewBox="0 0 22 22" aria-hidden="true" class="flex-shrink-0 text-brand-teal">
                            <circle cx="9.5" cy="9.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/>
                            <path d="M14.5 14.5L19 19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                        <input id="seguir-equipo-buscador" type="search" autocomplete="off"
                            placeholder="Buscá tu equipo, ej: River Plate"
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
                {{-- Panel del equipo seguido: lo arma resources/js/welcome.js con /equipo-seguido --}}
                <div id="seguir-equipo-card" aria-live="polite" class="mx-auto mt-8 hidden max-w-2xl"></div>

                <script type="application/json" id="seguir-equipo-datos">@json($equiposDeLaEdicion->values())</script>
                {{-- welcome.js lo copia dentro del panel del equipo seguido. --}}
                <template id="seguir-equipo-suscripcion">
                    <div class="mt-6 border-t border-tema-linea/15 pt-6">
                        <x-suscripcion.formulario :con-equipo="true" />
                    </div>
                </template>
            </section>
        @endif

        {{-- Accesos al torneo --}}
        @if ($edicionActual)
            <section aria-label="Accesos al torneo" class="relative mx-auto max-w-2xl px-4 pb-24 sm:px-6">
                <h2 class="tm-anim-in tm-d4 mb-4 text-xs font-bold uppercase tracking-[0.18em] text-tema-acento">
                    O explorá todo el torneo
                </h2>
                <section class="tm-anim-in tm-d4">
                    <h3 class="mb-3 text-sm font-bold text-tema-dim">Partidos y tablas</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
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
                        Vallas menos vencidas
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                            <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    </div>
                </section>
                <section class="tm-anim-in tm-d4 mt-6">
                    <h3 class="mb-3 text-sm font-bold text-tema-dim">Premios de la edición</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['campeones' => 'Campeones', 'subcampeones' => 'Subcampeones', 'goleadores' => 'Goleadores premiados', 'vallas' => 'Vallas premiadas'] as $ruta => $etiqueta)
                        <a href="{{ route($ruta, ['idEdicion' => $edicionActual->id]) }}"
                            class="group flex items-center justify-between gap-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-5 py-4 text-sm font-semibold transition hover:bg-tema-superficie/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light">
                            {{ $etiqueta }}
                            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="text-brand-teal-light transition-transform group-hover:translate-x-1">
                                <path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    @endforeach
                    </div>
                </section>
            </section>
        @endif
    </main>

    <x-links-legales />
    <x-tab-bar-movil :edicion="$edicionActual" />
</body>

</html>
