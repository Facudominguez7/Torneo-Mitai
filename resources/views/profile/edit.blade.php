<x-app-layout :idEdicion="$EdicionSeleccionada ? $EdicionSeleccionada->id : null">
    @php
        $usuario = auth()->user();
        $edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null;
        $locale = app()->getLocale() ?? 'es';
        $fechaRegistro = $usuario?->created_at
            ? $usuario->created_at->copy()->locale($locale)->translatedFormat('d \de F \de Y')
            : null;
    @endphp

    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Fondo cancha"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div class="relative pb-16">
            <section class="mx-auto max-w-6xl px-4 pt-12 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl border border-white/10 bg-white/5 px-6 py-8 shadow-[0_32px_88px_-48px_rgba(0,0,0,0.9)] backdrop-blur">
                    <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                            <div class="relative h-24 w-24 overflow-hidden rounded-3xl border border-white/15 bg-black/40 p-1 shadow-[0_18px_42px_-30px_rgba(255,255,255,0.8)]">
                                @if (!empty($usuario?->google_avatar))
                                    <img src="{{ $usuario->google_avatar }}" alt="Avatar de {{ $usuario->name ?? $usuario->email }}"
                                        class="h-full w-full rounded-[1.25rem] object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center rounded-[1.25rem] bg-gradient-to-br from-yellow-500/60 via-orange-500/50 to-rose-500/60 text-3xl font-bold text-white">
                                        {{ mb_substr($usuario->name ?? $usuario->email, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <div class="space-y-2">
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-white/60">
                                    Perfil
                                </span>
                                <h1 class="text-3xl font-bold text-white">
                                    {{ $usuario->name ?? $usuario->email }}
                                </h1>
                                <div class="flex flex-wrap items-center gap-3 text-sm text-white/60">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fa-regular fa-envelope text-yellow-300"></i>
                                        {{ $usuario->email }}
                                    </span>
                                    @if ($fechaRegistro)
                                        <span class="inline-flex items-center gap-2">
                                            <i class="fa-regular fa-calendar text-emerald-300"></i>
                                            {{ __('Miembro desde') }} {{ $fechaRegistro }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 text-sm text-white/70">
                            <div class="flex flex-wrap gap-3">
                                @if ($edicionActiva)
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                        <i class="fa-solid fa-trophy text-yellow-200"></i>
                                        {{ $edicionActiva->nombre }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
                                    <i class="fa-solid fa-user-shield text-sky-300"></i>
                                    {{ strtoupper($usuario->rol ?? 'jugador') }}
                                </span>
                                @if ($usuario->google_id)
                                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200/40 bg-emerald-400/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.35em] text-emerald-100">
                                        <i class="fa-brands fa-google"></i>
                                        {{ __('Cuenta vinculada con Google') }}
                                    </span>
                                @endif
                            </div>
                            <p class="max-w-md text-sm text-white/60">
                                {{ __('Desde aquí podés administrar tu presencia en el torneo, actualizar tus datos y gestionar la seguridad de tu cuenta.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-10 max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="lg:col-span-2 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_24px_70px_-48px_rgba(0,0,0,0.85)] backdrop-blur">
                        @include('profile.partials.update-profile-information-form')
                    </div>

                    @if (!$usuario->google_id)
                        <div class="lg:col-span-2 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_24px_70px_-48px_rgba(0,0,0,0.85)] backdrop-blur">
                            @include('profile.partials.update-password-form')
                        </div>
                    @endif

                    <div class="lg:col-span-2 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_24px_70px_-48px_rgba(0,0,0,0.85)] backdrop-blur">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
