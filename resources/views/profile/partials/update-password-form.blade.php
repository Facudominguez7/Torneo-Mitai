@if (!Auth::user()->google_id)
    <section class="space-y-6">
        <header class="space-y-2">
            <h2 class="text-lg font-semibold text-white">
                {{ __('Actualizá tu contraseña') }}
            </h2>

            <p class="text-sm text-white/70">
                {{ __('Elegí una clave robusta para proteger tu cuenta y evitar accesos no autorizados.') }}
            </p>
        </header>

        <form method="post" action="{{ route('password.update') }}" class="mt-8 space-y-6">
            @csrf
            @method('put')
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2 sm:col-span-2">
                    <x-input-label-profile for="update_password_current_password" :value="__('Contraseña actual')" />
                    <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>
                <div class="space-y-2">
                    <x-input-label-profile for="update_password_password" :value="__('Nueva contraseña')" />
                    <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>
                <div class="space-y-2">
                    <x-input-label-profile for="update_password_password_confirmation" :value="__('Confirmar contraseña')" />
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <x-primary-button-profile>{{ __('Guardar nueva contraseña') }}</x-primary-button-profile>

                @if (session('status') === 'password-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm font-semibold text-emerald-200">{{ __('Contraseña actualizada') }}</p>
                @endif
            </div>
        </form>
    </section>
@endif
