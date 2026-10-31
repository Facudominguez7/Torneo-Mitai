<x-guest-layout>
    <x-slot:titular><span class="acceso-corto">Todo lo de tu equipo.</span><span class="acceso-largo">Todo lo de tu equipo, en una sola cuenta.</span></x-slot:titular>
    <x-slot:bajada><span class="acceso-corto">Fixture, posiciones y planillas de cada partido.</span><span class="acceso-largo">Fixture, resultados, posiciones y planillas de cada partido de la edición.</span></x-slot:bajada>

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Ingresá a tu cuenta</h1>
        <p class="text-[15px] text-brand-navy-deep/75">Usá el correo con el que te registraste.</p>
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
                        class="acceso-enlace text-sm">¿Olvidaste tu contraseña?</a>
                </x-slot:accion>
            @endif
        </x-acceso.campo>

        <x-acceso.boton x-bind:disabled="enviando">Acceder</x-acceso.boton>
    </form>

    <x-acceso.separador />

    <x-acceso.boton-google>Ingresar con Google</x-acceso.boton-google>

    <x-slot:pie>
        <p class="text-center text-[15px] text-brand-navy-deep/75 lg:text-left">
            ¿Todavía no tenés cuenta?
            <a href="{{ route('register') }}" class="acceso-enlace">Crear cuenta</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
