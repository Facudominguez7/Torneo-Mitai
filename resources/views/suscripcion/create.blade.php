<x-guest-layout>
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Avisos por correo</h1>
        <p class="text-[15px] text-white/75">
            Recibí el resumen de cada fecha, la próxima fecha y los avisos del torneo. Para los resultados de tu equipo,
            elegilo en <a href="{{ route('home') }}#seguir-equipo-bloque" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Seguir mi equipo</a>.
        </p>
    </div>

    @if (session('suscripcion'))
        <x-acceso.aviso tipo="exito">{{ session('suscripcion') }}</x-acceso.aviso>
    @endif

    <x-suscripcion.formulario />
</x-guest-layout>
