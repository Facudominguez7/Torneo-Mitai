<x-guest-layout compacto>
    <x-slot:sobre>Último paso</x-slot:sobre>
    <x-slot:titular><span class="acceso-corto">Verificá tu correo.</span><span class="acceso-largo">Un código y ya estás en la cancha.</span></x-slot:titular>
    <x-slot:bajada>Con el correo verificado recibís los resultados, los partidos programados y los anuncios del torneo.</x-slot:bajada>

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Verificá tu correo</h1>
        <p class="text-[15px] text-brand-navy-deep/75">
            Te enviamos un código de 6 dígitos a <strong class="text-brand-navy-deep">{{ auth()->user()->email }}</strong>.
            Si no lo ves, revisá la carpeta de spam o pedí uno nuevo.
        </p>
    </div>

    @if (session('status') === 'verification-code-sent')
        <x-acceso.aviso tipo="exito">
            <p>{{ session('email-success', 'Te enviamos un nuevo código de verificación.') }}</p>
            <p class="text-sm font-normal text-brand-navy-deep/80">Revisá tu bandeja de entrada y también la carpeta de spam.</p>
        </x-acceso.aviso>
    @endif

    @if (session('warning'))
        <x-acceso.aviso>{{ session('warning') }}</x-acceso.aviso>
    @endif

    @if (session('email-error'))
        <x-acceso.aviso tipo="error">{{ session('email-error') }}</x-acceso.aviso>
    @endif

    <form method="POST" action="{{ route('verification.code') }}" class="flex flex-col gap-[18px]">
        @csrf

        <x-acceso.campo id="code" label="Código de verificación" name="code" :value="old('code')"
            :errores="$errors->get('code')" inputmode="numeric" pattern="[0-9]*" maxlength="6"
            autocomplete="one-time-code" placeholder="123456" required />

        <x-acceso.boton>Confirmar código</x-acceso.boton>
    </form>

    <form method="POST" action="{{ route('verification.send') }}"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf
        <x-acceso.boton variante="secundario" x-bind:disabled="enviando">
            <span x-show="! enviando">Reenviar código</span>
            <span x-show="enviando" x-cloak>Enviando…</span>
        </x-acceso.boton>
    </form>

    <div class="flex flex-col gap-2 border border-brand-navy/15 bg-white p-4 text-[15px] text-brand-navy-deep/80">
        <h2 class="font-bold text-brand-navy-deep">¿Para qué verificar?</h2>
        <ul class="flex list-disc flex-col gap-1 pl-5">
            <li>Recibir los resultados de los partidos por correo.</li>
            <li>Enterarte de los partidos programados.</li>
            <li>Recibir los anuncios del torneo.</li>
        </ul>
        <p class="text-sm text-brand-navy-deep/70">Podés darte de baja en cualquier momento desde el correo.</p>
    </div>

    <x-slot:pie>
        <p class="text-center text-[15px] lg:text-left">
            <a href="{{ route('home') }}" class="acceso-enlace">Volver al inicio</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
