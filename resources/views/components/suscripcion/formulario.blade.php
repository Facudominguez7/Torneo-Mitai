{{-- Formulario de suscripción: correo y avisos. --}}
@php
    $errores = $errors->getBag('suscripcion');
    $conDatosPrevios = session()->hasOldInput('email');
    $avisosMarcados = $conDatosPrevios ? (array) old('avisos', []) : ['resumen_fecha', 'proxima_fecha'];
    $avisos = [
        'resumen_fecha' => ['Resumen de la fecha', 'Resultados, tablas y goleadores.'],
        'proxima_fecha' => ['Próxima fecha', 'Días, horarios y canchas.'],
        'avisos' => ['Avisos del torneo', 'Cambios de cancha, suspensiones e inscripciones.'],
    ];
    $errorAvisos = $errores->first('avisos') ?: $errores->first('avisos.*');
@endphp

<form method="POST" action="{{ route('suscripcion.store') }}" class="flex flex-col gap-[18px]"
    x-data="{ enviando: false }" x-on:submit="enviando = true" x-on:pageshow.window="enviando = false">
    @csrf

    <x-acceso.campo id="suscripcion-email" label="Correo electrónico" type="email"
        name="email" :value="old('email')" :errores="$errores->get('email')" placeholder="nombre@correo.com" clase-rotulo="text-tema-acento"
        required autocomplete="email" />

    <fieldset class="suscripcion-avisos flex flex-col gap-2" @if ($errorAvisos) data-invalido aria-describedby="suscripcion-avisos-error" @endif>
        <legend class="mb-2 text-[15px] font-semibold text-tema-tinta/90">Qué querés recibir</legend>
        {{-- Sin casillas marcadas el navegador no manda "avisos": este campo vacío indica que no eligió ninguno. --}}
        <input type="hidden" name="avisos" value="">

        @foreach ($avisos as $valor => [$titulo, $detalle])
            <x-suscripcion.opcion nombre="avisos[]" :valor="$valor" :marcado="in_array($valor, $avisosMarcados, true)" :detalle="$detalle">
                {{ $titulo }}
            </x-suscripcion.opcion>
        @endforeach

        @if ($errorAvisos)
            <p id="suscripcion-avisos-error" class="acceso-error text-sm font-semibold">{{ $errorAvisos }}</p>
        @endif
    </fieldset>

    <div class="flex flex-col gap-2">
        <x-acceso.boton x-bind:disabled="enviando">Suscribirme</x-acceso.boton>
        <p class="text-center text-sm text-tema-dim/70">Te mandamos un correo para confirmar.</p>
    </div>
</form>
