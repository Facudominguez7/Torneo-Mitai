<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[32px]">Avisos por correo</h1>
        <p class="text-[15px] text-brand-navy-deep/75">
            Recibí el resumen de cada fecha, la próxima fecha y los avisos del torneo.
        </p>
    </div>

    @if (session('suscripcion'))
        <x-acceso.aviso tipo="exito">{{ session('suscripcion') }}</x-acceso.aviso>
    @endif

    <x-suscripcion.formulario />
</x-guest-layout>
