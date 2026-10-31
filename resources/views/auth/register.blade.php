<x-guest-layout compacto>
    <x-slot:sobre>Nueva cuenta</x-slot:sobre>
    <x-slot:titular><span class="acceso-corto">Sumate al torneo.</span><span class="acceso-largo">Seguí a tu equipo fecha a fecha.</span></x-slot:titular>
    <x-slot:bajada>Creá tu cuenta para recibir los avisos del torneo y ver las planillas de cada partido.</x-slot:bajada>

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Creá tu cuenta</h1>
        <p class="text-[15px] text-brand-navy-deep/75">Seguí a tu equipo y recibí los avisos del torneo.</p>
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
            <div class="flex items-start gap-3 border border-brand-navy/20 bg-white p-3.5">
                <input id="terminos" type="checkbox" name="terminos" value="1" required @checked(old('terminos'))
                    @if ($errors->has('terminos')) aria-invalid="true" aria-describedby="terminos-error" @endif
                    class="mt-0.5 h-[22px] w-[22px] shrink-0 rounded-sm border-brand-navy/40 bg-white text-brand-navy focus:ring-2 focus:ring-brand-navy/30 focus:ring-offset-0">
                <label for="terminos" class="text-[15px] leading-snug text-brand-navy-deep">
                    Leí y acepto los
                    <a href="{{ route('terminos') }}" target="_blank" class="acceso-enlace">Términos y condiciones</a>
                    y la
                    <a href="{{ route('privacidad') }}" target="_blank" class="acceso-enlace">Política de privacidad</a>.
                </label>
            </div>
            @error('terminos')
                <p id="terminos-error" class="acceso-error text-sm font-semibold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-acceso.boton x-bind:disabled="enviando">
            <span x-show="! enviando">Crear cuenta</span>
            <span x-show="enviando" x-cloak>Creando cuenta…</span>
        </x-acceso.boton>
    </form>

    <x-acceso.separador />

    <x-acceso.boton-google>Registrarme con Google</x-acceso.boton-google>
    <p class="-mt-2 text-[13px] leading-snug text-brand-navy-deep/70">Si te registrás con Google, también aceptás los Términos y condiciones y la Política de privacidad.</p>

    <x-slot:pie>
        <p class="text-center text-[15px] text-brand-navy-deep/75 lg:text-left">
            ¿Ya tenés cuenta?
            <a href="{{ route('login') }}" class="acceso-enlace">Ingresá</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
