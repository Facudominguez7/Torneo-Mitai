<section class="space-y-6" x-data="{ resending: false }">
    <header class="space-y-2">
        <h2 class="text-lg font-semibold text-white">
            {{ __('Información de tu perfil') }}
        </h2>

        <p class="text-sm text-white/70">
            {{ __('Personalizá tus datos para que coincidan con la identidad del torneo y mantené tus medios de contacto actualizados.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}" @submit="resending = true">
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
                <div class="sm:col-span-2 rounded-2xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm text-white">
                    <p class="font-medium">{{ __('Tu correo aún no fue verificado.') }}</p>
                    <p class="mt-1">{{ __('Hacelo ahora para activar las novedades por email y recibir tu código de verificación.') }}</p>
                    <button form="send-verification"
                        :disabled="resending"
                        class="mt-3 inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-white/10 focus:outline-none focus:ring focus:ring-white/40 disabled:opacity-50">
                        <svg x-show="resending" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="resending ? 'Enviando...' : 'Reenviar código'"></span>
                    </button>

                    @if (session('status') === 'verification-code-sent')
                        <p class="mt-3 rounded-xl border border-emerald-200/40 bg-emerald-400/20 px-4 py-2 text-xs font-bold uppercase tracking-[0.1em] text-emerald-100">
                            {{ __('Te enviamos un nuevo código de verificación.') }}
                        </p>
                        <p class="mt-2 text-xs text-white/60">
                            📬 Revisá tu bandeja de entrada y también la <strong>carpeta de spam</strong> si no lo encontrás.
                        </p>
                    @endif

                    @if (session('email-error'))
                        <p class="mt-3 rounded-xl border border-red-400/40 bg-red-500/20 px-4 py-2 text-xs font-bold uppercase tracking-[0.1em] text-red-200">
                            {{ session('email-error') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <x-primary-button-profile>
                {{ __('Guardar cambios') }}
            </x-primary-button-profile>

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
