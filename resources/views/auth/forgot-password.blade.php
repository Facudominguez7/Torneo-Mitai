<x-guest-layout>
    <x-slot:sobre>Recuperar acceso</x-slot:sobre>
    <x-slot:titular><span class="acceso-corto">Volvé a la cancha.</span><span class="acceso-largo">Volvé a la cancha en un minuto.</span></x-slot:titular>
    <x-slot:bajada><span class="acceso-corto">Te mandamos un enlace para elegir una contraseña nueva.</span><span class="acceso-largo">Te mandamos un enlace a tu correo para que elijas una contraseña nueva.</span></x-slot:bajada>

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">
            {{ isset($restablecer) ? 'Restablecé tu contraseña' : '¿Olvidaste tu contraseña?' }}
        </h1>
        <p class="text-[15px] text-brand-navy-deep/75">
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
        <a href="{{ route('login') }}" class="acceso-volver inline-flex items-center gap-2 self-center text-[15px] font-bold text-brand-navy hover:text-brand-teal lg:self-start">
            <span aria-hidden="true">←</span> Volver a ingresar
        </a>
    </x-slot:pie>
</x-guest-layout>
