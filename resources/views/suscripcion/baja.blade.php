<x-guest-layout>
    @if ($suscriptor->estaActivo())
        <div class="flex flex-col gap-1.5">
            <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">¿Darte de baja?</h1>
            <p class="text-[15px] text-brand-navy-deep/75">
                Dejás de recibir todos los avisos en <span class="font-semibold text-brand-navy-deep">{{ $suscriptor->email }}</span>.
            </p>
        </div>

        <form method="POST" action="{{ $urlBaja }}" x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
            @csrf
            <x-acceso.boton x-bind:disabled="enviando">Darme de baja</x-acceso.boton>
        </form>

        <p class="text-center text-[15px] text-brand-navy-deep/75">
            ¿Preferís recibir menos correos?
            <a href="{{ $urlPreferencias }}" class="acceso-enlace">Elegí cuáles</a>
        </p>
    @else
        <div class="flex flex-col gap-1.5">
            <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Avisos por correo</h1>
            <p class="text-[15px] text-brand-navy-deep/75">{{ $suscriptor->email }}</p>
        </div>

        <x-acceso.aviso tipo="exito">Ya estás dado de baja.</x-acceso.aviso>

        <p class="text-center text-[15px] text-brand-navy-deep/75">
            ¿Querés volver a recibirlos?
            <a href="{{ $urlPreferencias }}" class="acceso-enlace">Elegí tus avisos</a>
        </p>
    @endif
</x-guest-layout>
