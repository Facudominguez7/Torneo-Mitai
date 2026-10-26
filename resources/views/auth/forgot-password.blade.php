<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">
            {{ isset($restablecer) ? 'Restablecé tu contraseña' : '¿Olvidaste tu contraseña?' }}
        </h1>
        <p class="text-[15px] text-white/75">
            @if (isset($restablecer))
                Por tu seguridad tenés que elegir una contraseña nueva. Ingresá tu correo y te enviamos un enlace para hacerlo.
            @else
                Ingresá tu correo y te enviamos un enlace para elegir una contraseña nueva.
            @endif
        </p>
    </div>

    @if (session('status'))
        <x-acceso.aviso tipo="exito">{{ session('status') }}</x-acceso.aviso>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-[18px]"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf

        <x-acceso.campo id="email" label="Correo electrónico" type="email" name="email" :value="old('email')"
            :errores="$errors->get('email')" placeholder="nombre@correo.com" required autofocus autocomplete="username" />

        <x-acceso.boton x-bind:disabled="enviando">Enviar enlace</x-acceso.boton>
    </form>

    <x-slot:pie>
        <p class="text-center text-[15px] text-white/80">
            <a href="{{ route('login') }}" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Volver a ingresar</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
