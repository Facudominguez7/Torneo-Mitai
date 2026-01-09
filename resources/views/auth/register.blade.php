<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-1 text-center">
            <h2 class="text-2xl font-bold text-white">Creá tu cuenta en Mita'í</h2>
            <p class="text-sm text-white/60">Accedé a todos los apartados del torneo y seguí tus equipos favoritos.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5" id="register-form">
            @csrf

            <div class="space-y-2">
                <x-input-label for="name" :value="__('Nombre completo')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="text-sm text-red-400" />
            </div>

            <div class="space-y-2">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="text-sm text-red-400" />
            </div>

            <div class="space-y-2">
                <x-input-label for="password" :value="__('Contraseña')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="text-sm text-red-400" />
            </div>

            <div class="space-y-2">
                <x-input-label for="password_confirmation" :value="__('Confirmar contraseña')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="text-sm text-red-400" />
            </div>

            <div class="space-y-4">
                {{-- Botón con loader --}}
                <button type="submit" id="register-btn"
                    class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-yellow-300 via-amber-300 to-yellow-400 px-6 py-3 text-sm font-semibold text-black shadow-lg shadow-yellow-500/40 transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-yellow-500/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-300 active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
                    {{-- Spinner (oculto por defecto) --}}
                    <svg id="register-spinner" class="hidden animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="register-text">Crear cuenta</span>
                </button>

                <div class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.3em] text-white/40">
                    <span class="h-px flex-1 bg-white/20"></span>
                    o registrate con
                    <span class="h-px flex-1 bg-white/20"></span>
                </div>

                <a class="group inline-flex w-full items-center justify-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:border-white/40 hover:bg-white/10"
                    href="/auth/redirect">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                        <path fill="#fbc02d" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12	s5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24s8.955,20,20,20	s20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z" />
                        <path fill="#e53935" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039	l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z" />
                        <path fill="#4caf50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36	c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z" />
                        <path fill="#1565c0" d="M43.611,20.083L43.595,20L42,20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571	c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z" />
                    </svg>
                    Registrarse con Google
                </a>

                <a class="block text-center text-sm font-semibold text-white/60 transition hover:text-white"
                    href="{{ route('login') }}">
                    {{ __('¿Ya tenés cuenta? Iniciá sesión') }}
                </a>
            </div>
        </form>
    </div>

    {{-- Script para loader --}}
    <script>
        document.getElementById('register-form').addEventListener('submit', function() {
            const btn = document.getElementById('register-btn');
            const spinner = document.getElementById('register-spinner');
            const text = document.getElementById('register-text');
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            text.textContent = 'Creando cuenta...';
        });
    </script>
</x-guest-layout>