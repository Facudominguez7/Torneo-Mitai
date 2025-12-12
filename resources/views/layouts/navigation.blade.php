<nav class="relative z-50 border-b border-white/10 bg-black/40 backdrop-blur">
    <div
        class="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6 lg:px-8">
        @php
            $edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null;
        @endphp

        @php
            $homeUrl = optional($edicionActiva)->id ? route('home', ['idEdicion' => $edicionActiva->id]) : route('home');
        @endphp
        <a href="{{ $homeUrl }}" class="flex items-center gap-3 text-white transition hover:opacity-90 sm:self-auto">
            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" class="h-10 w-10" alt="MITAI Logo">
            <div class="flex flex-col text-center sm:text-left">
                <span class="text-sm font-semibold uppercase tracking-[0.3em] text-white/60">Torneo Mitai</span>
                <span
                    class="text-lg font-bold text-white">{{ optional($edicionActiva)->nombre ?? 'Edición vigente' }}</span>
            </div>
        </a>

        <div
            class="flex w-full flex-wrap items-center justify-center gap-3 sm:ml-auto sm:w-auto sm:justify-end sm:gap-4">
            @auth
                @if (Auth::user()->rol === 'admin')
                    <a href="{{ route('admin', ['idEdicion' => optional($edicionActiva)->id]) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white/70 transition hover:border-yellow-300 hover:bg-yellow-300/10">
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
                                class="inline-flex items-center gap-3 rounded-full border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
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
                                <svg class="ml-1 h-4 w-4 text-[#f7f280]" xmlns="http://www.w3.org/2000/svg" fill="none"
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

                            {{-- Botón de Notificaciones Push para todos los usuarios autenticados --}}
                            <div class="px-4 py-2 border-t border-white/10">
                                <button id="push-toggle-btn"
                                    class="flex items-center w-full gap-2 px-3 py-2 text-sm text-white/80 hover:bg-white/10 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                                    </svg>
                                    <span>Activar Notificaciones</span>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Cerrar sesión') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-5 py-2 text-sm font-semibold text-white shadow-[0_12px_28px_-18px_rgba(14,75,94,0.7)] transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                    Iniciar sesión
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            @endauth
        </div>
    </div>
</nav>