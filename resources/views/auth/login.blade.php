<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Ingresá a tu cuenta</h1>
        <p class="text-[15px] text-white/75">Fixture, resultados y planillas de tu equipo.</p>
    </div>

    @if (session('aviso'))
        <x-acceso.aviso>{{ session('aviso') }}</x-acceso.aviso>
    @endif
    @if (session('status'))
        <x-acceso.aviso tipo="exito">{{ session('status') }}</x-acceso.aviso>
    @endif
    @if (session('error'))
        <x-acceso.aviso tipo="error">{{ session('error') }}</x-acceso.aviso>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-[18px]"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf

        <x-acceso.campo id="email" label="Correo electrónico" type="email" name="email" :value="old('email')"
            :errores="$errors->get('email')" placeholder="nombre@correo.com" required autofocus autocomplete="username" />

        <x-acceso.campo id="password" label="Contraseña" name="password" revelable
            :errores="$errors->get('password')" required autocomplete="current-password">
            @if (Route::has('password.request'))
                <x-slot:accion>
                    <a href="{{ route('password.request') }}"
                        class="text-sm font-semibold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">¿Olvidaste tu contraseña?</a>
                </x-slot:accion>
            @endif
        </x-acceso.campo>

        <x-acceso.boton x-bind:disabled="enviando">Acceder</x-acceso.boton>
    </form>

    <x-acceso.separador />

    <x-acceso.boton-google>Ingresar con Google</x-acceso.boton-google>

    <x-slot:pie>
        <p class="text-center text-[15px] text-white/80">
            ¿Todavía no tenés cuenta?
            <a href="{{ route('register') }}" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Crear cuenta</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
