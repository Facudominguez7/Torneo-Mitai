<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Creá tu cuenta</h1>
        <p class="text-[15px] text-white/75">Seguí a tu equipo y recibí los avisos del torneo.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf

        <x-acceso.campo id="name" label="Nombre completo" name="name" :value="old('name')"
            :errores="$errors->get('name')" required autofocus autocomplete="name" />

        <x-acceso.campo id="email" label="Correo electrónico" type="email" name="email" :value="old('email')"
            :errores="$errors->get('email')" placeholder="nombre@correo.com" required autocomplete="username" />

        <x-acceso.campo id="password" label="Contraseña" name="password" revelable
            :errores="$errors->get('password')" required autocomplete="new-password" />

        <x-acceso.campo id="password_confirmation" label="Confirmar contraseña" name="password_confirmation" revelable
            :errores="$errors->get('password_confirmation')" required autocomplete="new-password" />

        <div class="flex flex-col gap-2">
            <div class="flex items-start gap-3 rounded-[10px] border border-white/10 bg-white/[0.04] p-3.5">
                <input id="terminos" type="checkbox" name="terminos" value="1" required @checked(old('terminos'))
                    @if ($errors->has('terminos')) aria-invalid="true" aria-describedby="terminos-error" @endif
                    class="mt-0.5 h-[22px] w-[22px] shrink-0 rounded border-white/30 bg-white/[0.06] text-brand-gold focus:ring-2 focus:ring-brand-gold/40 focus:ring-offset-0">
                <label for="terminos" class="text-[15px] leading-snug text-white/90">
                    Leí y acepto los
                    <a href="{{ route('terminos') }}" target="_blank" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Términos y condiciones</a>
                    y la
                    <a href="{{ route('privacidad') }}" target="_blank" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Política de privacidad</a>.
                </label>
            </div>
            @error('terminos')
                <p id="terminos-error" class="text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <x-acceso.boton x-bind:disabled="enviando">
            <span x-show="! enviando">Crear cuenta</span>
            <span x-show="enviando" x-cloak>Creando cuenta…</span>
        </x-acceso.boton>
    </form>

    <x-acceso.separador />

    <x-acceso.boton-google>Registrarme con Google</x-acceso.boton-google>
    <p class="text-sm leading-snug text-white/75">Si te registrás con Google, también aceptás los Términos y condiciones y la Política de privacidad.</p>

    <x-slot:pie>
        <p class="text-center text-[15px] text-white/80">
            ¿Ya tenés cuenta?
            <a href="{{ route('login') }}" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Ingresá</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
