<section class="space-y-6">
    <header class="space-y-2">
        <h2 class="text-lg font-semibold text-white">
            {{ __('Eliminar cuenta') }}
        </h2>

        <p class="text-sm text-white/70">
            {{ __('Esta acción es definitiva. Conservá un respaldo antes de continuar, porque no podremos recuperar tus datos luego.') }}
        </p>
    </header>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-rose-300/50 bg-gradient-to-r from-rose-600/30 via-rose-500/25 to-rose-600/30 px-4 py-4 text-sm text-rose-100">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-rose-200/60 bg-rose-500/40 text-lg text-white shadow-[0_16px_32px_-18px_rgba(244,63,94,0.6)]">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </span>
        <span class="font-semibold text-white/85">{{ __('Si eliminás tu cuenta, se borrarán permanentemente tus datos.') }}</span>
    </div>

    <x-danger-button x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('Eliminar cuenta') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}"
            class="space-y-6 rounded-3xl border border-white/10 bg-black/60 p-6 text-white">
            @csrf
            @method('delete')

            <div class="space-y-2">
                <h2 class="text-lg font-semibold text-white">{{ __('¿Seguro que querés eliminarla?') }}</h2>
                <p class="text-sm text-white/70">
                    {{ __('Esta acción eliminará todos tus datos, plantillas y estadísticas asociadas a tu participación en el torneo Mitai.') }}
                </p>
            </div>

            @if (!auth()->user()->google_id)
                <div class="space-y-2">
                    <x-input-label for="password" value="{{ __('Contraseña') }}" />
                    <x-text-input id="password" name="password" type="password" placeholder="{{ __('Ingresá tu contraseña para confirmar') }}" />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>
            @endif

            <div class="flex flex-wrap justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Eliminar definitivamente') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
