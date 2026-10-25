<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Verificá tu correo</h1>
        <p class="text-[15px] text-white/75">
            Te enviamos un código de 6 dígitos a <strong class="text-white">{{ auth()->user()->email }}</strong>.
            Si no lo ves, revisá la carpeta de spam o pedí uno nuevo.
        </p>
    </div>

    @if (session('status') === 'verification-code-sent')
        <x-acceso.aviso tipo="exito">
            <p>{{ session('email-success', 'Te enviamos un nuevo código de verificación.') }}</p>
            <p class="text-sm font-normal text-emerald-200/90">Revisá tu bandeja de entrada y también la carpeta de spam.</p>
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

    <div class="flex flex-col gap-2 rounded-[10px] border border-white/10 bg-white/[0.04] p-4 text-[15px] text-white/80">
        <h2 class="font-semibold text-white">¿Para qué verificar?</h2>
        <ul class="flex list-disc flex-col gap-1 pl-5">
            <li>Recibir los resultados de los partidos por correo.</li>
            <li>Enterarte de los partidos programados.</li>
            <li>Recibir los anuncios del torneo.</li>
        </ul>
        <p class="text-sm text-white/75">Podés darte de baja en cualquier momento desde el correo.</p>
    </div>

    <x-slot:pie>
        <p class="text-center text-[15px]">
            <a href="{{ route('home') }}" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Volver al inicio</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
