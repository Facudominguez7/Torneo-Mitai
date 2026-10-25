<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Confirmá tu contraseña</h1>
        <p class="text-[15px] text-white/75">Esta sección es protegida. Ingresá tu contraseña para continuar.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-[18px]"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf

        <x-acceso.campo id="password" label="Contraseña" name="password" revelable
            :errores="$errors->get('password')" required autocomplete="current-password" />

        <x-acceso.boton x-bind:disabled="enviando">Confirmar</x-acceso.boton>
    </form>
</x-guest-layout>
