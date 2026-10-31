<x-guest-layout>
    <x-slot:sobre>Recuperar acceso</x-slot:sobre>
    <x-slot:titular><span class="acceso-corto">Volvé a la cancha.</span><span class="acceso-largo">Volvé a la cancha en un minuto.</span></x-slot:titular>
    <x-slot:bajada>Elegí una contraseña nueva y volvé a seguir a tu equipo.</x-slot:bajada>

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Elegí tu contraseña nueva</h1>
        <p class="text-[15px] text-brand-navy-deep/75">Usá una contraseña que no uses en otros sitios.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-4"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-acceso.campo id="email" label="Correo electrónico" type="email" name="email" :value="old('email', $request->email)"
            :errores="$errors->get('email')" required autofocus autocomplete="username" />

        <x-acceso.campo id="password" label="Contraseña nueva" name="password" revelable
            :errores="$errors->get('password')" required autocomplete="new-password" />

        <x-acceso.campo id="password_confirmation" label="Confirmar contraseña" name="password_confirmation" revelable
            :errores="$errors->get('password_confirmation')" required autocomplete="new-password" />

        <x-acceso.boton x-bind:disabled="enviando">Guardar contraseña</x-acceso.boton>
    </form>
</x-guest-layout>
