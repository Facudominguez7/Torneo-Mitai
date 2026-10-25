@props(['conEquipo' => false])

{{-- Formulario de suscripción: correo y avisos. Con equipo suma los resultados del equipo seguido (welcome.js completa el id). --}}
@php
    $errores = $errors->getBag('suscripcion');
    $conDatosPrevios = session()->hasOldInput('email');
    $avisosMarcados = $conDatosPrevios ? (array) old('avisos', []) : ['resumen_fecha', 'proxima_fecha'];
    $equipoMarcado = ! $conDatosPrevios || ! empty(old('equipos'));
    $avisos = [
        'resumen_fecha' => ['Resumen de la fecha', 'Resultados, tablas y goleadores.'],
        'proxima_fecha' => ['Próxima fecha', 'Días, horarios y canchas.'],
        'avisos' => ['Avisos del torneo', 'Cambios de cancha, suspensiones e inscripciones.'],
    ];
    $errorAvisos = $errores->first('avisos') ?: $errores->first('avisos.*') ?: $errores->first('equipos') ?: $errores->first('equipos.*');
@endphp

<form method="POST" action="{{ route('suscripcion.store') }}" class="flex flex-col gap-[18px]"
    x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
    @csrf

    @if ($conEquipo)
        <p class="flex items-center gap-2 text-lg font-bold text-white">
            <svg aria-hidden="true" class="h-5 w-5 text-brand-teal-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" />
            </svg>
            Avisame por correo
        </p>
    @endif

    <x-acceso.campo id="{{ $conEquipo ? 'suscripcion-email-equipo' : 'suscripcion-email' }}" label="Correo electrónico" type="email"
        name="email" :value="old('email')" :errores="$errores->get('email')" placeholder="nombre@correo.com"
        required autocomplete="email" />

    <fieldset class="flex flex-col gap-2" @if ($errorAvisos) aria-describedby="suscripcion-avisos-error" @endif>
        <legend class="mb-2 text-[15px] font-semibold text-white/90">Qué querés recibir</legend>
        {{-- Sin casillas marcadas el navegador no manda "avisos": este campo vacío indica que no eligió ninguno. --}}
        <input type="hidden" name="avisos" value="">

        @if ($conEquipo)
            <x-suscripcion.opcion nombre="equipos[]" valor="" :marcado="$equipoMarcado" data-rol="equipo-id"
                detalle="Un correo al cerrar cada fecha.">
                Resultados de <span data-rol="equipo-nombre">tu equipo</span>
            </x-suscripcion.opcion>
        @endif

        @foreach ($avisos as $valor => [$titulo, $detalle])
            <x-suscripcion.opcion nombre="avisos[]" :valor="$valor" :marcado="in_array($valor, $avisosMarcados, true)" :detalle="$detalle">
                {{ $titulo }}
            </x-suscripcion.opcion>
        @endforeach

        @if ($errorAvisos)
            <p id="suscripcion-avisos-error" class="text-sm text-red-300">{{ $errorAvisos }}</p>
        @endif
    </fieldset>

    <div class="flex flex-col gap-2">
        <x-acceso.boton x-bind:disabled="enviando">Suscribirme</x-acceso.boton>
        <p class="text-center text-sm text-white/70">Te mandamos un correo para confirmar.</p>
    </div>
</form>
