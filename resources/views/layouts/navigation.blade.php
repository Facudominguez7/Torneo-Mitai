<div id="nav-centinela" class="h-px" aria-hidden="true"></div>
<nav aria-label="Navegación principal" id="nav-principal" class="sticky top-0 z-50 border-b border-white/10 bg-black/40 backdrop-blur">
        @php
            $navInlineSecciones = ! (auth()->check() && (Auth::user()->rol ?? '') === 'admin');
            $itemsSeccion = [
            ['label' => 'Inicio', 'ruta' => 'home', 'patterns' => ['home']],
            ['label' => 'Partidos', 'ruta' => 'fixture', 'patterns' => ['fixture']],
            ['label' => 'Posiciones', 'ruta' => 'tabla-posiciones', 'patterns' => ['tabla-posiciones']],
            ['label' => 'Goleadores', 'ruta' => 'tabla-goleadores', 'patterns' => ['tabla-goleadores']],
            ['label' => 'Finales', 'ruta' => 'instancias-finales', 'patterns' => ['instancias-finales']],
        ];
    @endphp

    <div
        class="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6 lg:gap-6 lg:px-8 [.nav-compacta_&]:py-2">
        @php
            $edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null;
        @endphp

        @php
            $homeUrl = optional($edicionActiva)->id ? route('home', ['idEdicion' => $edicionActiva->id]) : route('home');
        @endphp
        <a href="{{ $homeUrl }}"
            class="flex shrink-0 items-center gap-3 whitespace-nowrap rounded-lg text-white transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40 sm:self-auto [.nav-compacta_&]:hidden">
            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" class="h-10 w-10" alt="MITAI Logo">
            <div class="flex flex-col text-center sm:text-left">
                <span class="text-sm font-semibold uppercase tracking-[0.3em] text-white/60">Torneo Mitai</span>
                <span
                    class="text-lg font-bold text-white">{{ optional($edicionActiva)->nombre ?? 'Edición vigente' }}</span>
            </div>
        </a>

        <div class="{{ $navInlineSecciones ? 'hidden min-w-0 overflow-hidden xl:flex flex-1 items-center justify-center gap-6' : 'hidden' }}">
            @foreach ($itemsSeccion as $item)
                <a href="{{ route($item['ruta'], ['idEdicion' => optional($edicionActiva)->id]) }}"
                    @if (request()->routeIs($item['patterns']))
                        aria-current="page"
                    @endif
                    class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40 {{ request()->routeIs($item['patterns']) ? 'border-brand-gold text-white' : 'border-transparent text-white/70 hover:text-brand-gold' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <div
            class="flex w-full flex-wrap items-center justify-center gap-3 sm:ml-auto sm:w-auto sm:justify-end sm:gap-4 shrink-0 whitespace-nowrap [.nav-compacta_&]:hidden">
            @auth
                @if (Auth::user()->rol === 'admin')
                    <a href="{{ route('admin', ['idEdicion' => optional($edicionActiva)->id]) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/70 transition hover:border-yellow-300 hover:bg-yellow-300/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40">
                        Panel de administración
                    </a>
                @endif
            @endauth

            @auth
                <div x-data="{ open: false }" class="relative">
                    @php
                        $nombreUsuario = Auth::user()->name ?? Auth::user()->email;
                    @endphp
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button
                                class="inline-flex items-center gap-3 rounded-full border border-brand-gold/40 bg-gradient-to-r from-brand-navy/90 via-brand-teal/85 to-brand-navy/85 px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                                <div class="flex items-center gap-2">
                                    @if (!empty(Auth::user()->google_avatar))
                                        <img class="h-9 w-9 rounded-2xl border border-white/20 object-cover"
                                            src="{{ Auth::user()->google_avatar }}" alt="Avatar de {{ $nombreUsuario }}">
                                    @else
                                        <span
                                            class="flex h-9 w-9 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-sm font-bold uppercase text-white">
                                            {{ mb_substr($nombreUsuario, 0, 1) }}
                                        </span>
                                    @endif
                                    <span class="text-sm font-semibold text-white">{{ $nombreUsuario }}</span>
                                </div>
                                <svg class="ml-1 h-4 w-4 text-brand-gold" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 20 20">
                                    <path fill="currentColor" fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="px-4 py-3 text-sm text-white/80">
                                <p class="font-semibold">{{ $nombreUsuario }}</p>
                                <p class="text-white/60">{{ Auth::user()->email }}</p>
                            </div>
                            @if ($edicionActiva)
                                <x-dropdown-link :href="route('profile.edit', ['idEdicion' => $edicionActiva->id])">
                                    {{ __('Perfil') }}
                                </x-dropdown-link>
                            @else
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Perfil') }}
                                </x-dropdown-link>
                            @endif

                            <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10">
                                @csrf
                                <button type="submit"
                                    class="block w-full px-4 py-2 text-start text-sm font-semibold text-white/80 transition duration-150 ease-in-out hover:bg-brand-gold/15 hover:text-white focus:outline-none focus:bg-brand-gold/20 focus:text-white">
                                    {{ __('Cerrar sesión') }}
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-brand-gold/40 bg-gradient-to-r from-brand-navy/90 via-brand-teal/85 to-brand-navy/85 px-5 py-2 text-sm font-semibold text-white shadow-[0_12px_28px_-18px_rgba(var(--brand-navy)_/_0.7)] transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                    Iniciar sesión
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            @endauth
        </div>
    </div>

    <div id="nav-secciones-mobile"
        class="mx-auto flex max-w-6xl flex-wrap justify-center gap-x-6 gap-y-2 overflow-x-auto border-t border-white/10 px-4 pt-3 pb-4 sm:flex-nowrap sm:px-6 {{ $navInlineSecciones ? 'xl:hidden' : '' }} xl:px-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($itemsSeccion as $item)
            <a href="{{ route($item['ruta'], ['idEdicion' => optional($edicionActiva)->id]) }}"
                @if (request()->routeIs($item['patterns']))
                    aria-current="page"
                @endif
                class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40 {{ request()->routeIs($item['patterns']) ? 'border-brand-gold text-white' : 'border-transparent text-white/70 hover:text-brand-gold' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var fila = document.getElementById('nav-secciones-mobile');
            if (fila && fila.scrollWidth > fila.clientWidth) {
                var activo = fila.querySelector('a[aria-current="page"]');
                if (activo) {
                    activo.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            }
        });
    </script>
    <script>
        // Al scrollear la barra queda fija solo con la navegación de
        // secciones: el bloque de logo/sesión se oculta vía .nav-compacta.
        document.addEventListener('DOMContentLoaded', function () {
            var barra = document.getElementById('nav-principal');
            var centinela = document.getElementById('nav-centinela');
            if (!barra || !centinela || !('IntersectionObserver' in window)) {
                return;
            }
            new IntersectionObserver(function (entrada) {
                barra.classList.toggle('nav-compacta', !entrada[0].isIntersecting);
            }).observe(centinela);
        });
    </script>
</nav>
