<section class="space-y-6">
    <header class="space-y-2">
        <h2 class="text-lg font-semibold text-white">
            {{ __('Información de tu perfil') }}
        </h2>

        <p class="text-sm text-white/70">
            {{ __('Personalizá tus datos para que coincidan con la identidad del torneo y mantené tus medios de contacto actualizados.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-8 space-y-6">
        @csrf
        @method('patch')

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-2">
                <x-input-label for="name" :value="__('Nombre completo')" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="space-y-2">
                <x-input-label for="email" :value="__('Correo electrónico')" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="sm:col-span-2 rounded-2xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                    <p class="font-medium">{{ __('Tu correo aún no fue verificado.') }}</p>
                    <p class="mt-1">{{ __('Hacelo ahora para asegurarte de recibir todas las novedades del torneo.') }}</p>
                    <button form="send-verification"
                        class="mt-3 inline-flex items-center gap-2 rounded-full border border-amber-200/50 bg-amber-400/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.3em] text-amber-50 transition hover:bg-amber-400/30 focus:outline-none focus:ring focus:ring-amber-300/40">
                        {{ __('Reenviar correo de verificación') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-3 rounded-full border border-emerald-200/40 bg-emerald-400/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-100">
                            {{ __('Te enviamos un nuevo enlace de verificación.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <x-primary-button class="inline-flex items-center justify-center gap-2 rounded-full border border-[#f7f280]/40 bg-gradient-to-r from-[#0e4b5e]/90 via-[#2a6d81]/85 to-[#0e4b5e]/85 px-5 py-2 text-sm font-semibold text-white shadow-[0_12px_28px_-18px_rgba(14,75,94,0.7)] transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#f7f280]/40">
                {{ __('Guardar cambios') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-emerald-200"
                >{{ __('Cambios guardados') }}</p>
            @endif
        </div>
    </form>
</section>
