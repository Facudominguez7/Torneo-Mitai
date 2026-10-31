<x-guest-layout>
    @php
        $errores = $errors->getBag('suscripcion');
        $conDatosPrevios = session()->hasOldInput();
        $avisos = [
            'resumen_fecha' => ['Resumen de la fecha', 'Resultados, tablas y goleadores.', $suscriptor->recibe_resumen_fecha],
            'proxima_fecha' => ['Próxima fecha', 'Días, horarios y canchas.', $suscriptor->recibe_proxima_fecha],
            'avisos' => ['Avisos del torneo', 'Cambios de cancha, suspensiones e inscripciones.', $suscriptor->recibe_avisos],
        ];
        $avisosMarcados = $conDatosPrevios
            ? (array) old('avisos', [])
            : array_keys(array_filter(array_map(fn (array $aviso): bool => $aviso[2], $avisos)));
        $errorAvisos = $errores->first('avisos') ?: $errores->first('avisos.*');
    @endphp

    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-bold leading-tight sm:text-[28px]">Tus avisos por correo</h1>
        <p class="text-[15px] text-white/75">Llegan a <span class="font-semibold text-white">{{ $suscriptor->email }}</span>.</p>
    </div>

    @if (session('suscripcion'))
        <x-acceso.aviso tipo="exito">{{ session('suscripcion') }}</x-acceso.aviso>
    @elseif (! $suscriptor->estaActivo())
        <x-acceso.aviso>Ahora no recibís avisos. Elegí cuáles querés y guardá para volver a recibirlos.</x-acceso.aviso>
    @endif

    <form method="POST" action="{{ $urlPreferencias }}" class="flex flex-col gap-6"
        x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
        @csrf
        @method('PUT')

        <fieldset class="flex flex-col gap-2" @if ($errorAvisos) aria-describedby="preferencias-avisos-error" @endif>
            <legend class="mb-2 text-[15px] font-semibold text-white/90">Qué querés recibir</legend>
            <input type="hidden" name="avisos" value="">
            @foreach ($avisos as $valor => [$titulo, $detalle])
                <x-suscripcion.opcion nombre="avisos[]" :valor="$valor" :marcado="in_array($valor, $avisosMarcados, true)" :detalle="$detalle">
                    {{ $titulo }}
                </x-suscripcion.opcion>
            @endforeach
            @if ($errorAvisos)
                <p id="preferencias-avisos-error" class="text-sm text-red-300">{{ $errorAvisos }}</p>
            @endif
        </fieldset>

        <x-acceso.boton x-bind:disabled="enviando">Guardar preferencias</x-acceso.boton>
    </form>

    <x-slot:pie>
        <p class="text-center text-[15px] text-white/80">
            ¿No querés recibir más correos?
            <a href="{{ $urlBaja }}" class="font-bold text-brand-teal-light underline-offset-4 hover:text-brand-gold hover:underline">Darme de baja</a>
        </p>
    </x-slot:pie>
</x-guest-layout>
