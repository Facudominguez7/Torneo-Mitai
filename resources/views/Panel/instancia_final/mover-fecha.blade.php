@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Mover a una fecha" />

    @include('Panel.fragment._errores-formulario')

    <div class="mb-4 mt-6">
        <a href="{{ route('instancia_final.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-pa-superficie px-3 py-2 text-sm font-semibold text-pa-tinta-media shadow hover:bg-pa-fila-hover">
            Volver al listado
        </a>
    </div>

    <form action="{{ route('instancia_final.mover-fecha.store', ['instancia_final' => $partido->id, ...$parametrosRegreso]) }}" method="POST" class="mt-6">
        @csrf
        <x-ui.form-card>
            <div class="flex flex-col gap-4">
                <div>
                    <p class="text-base font-semibold text-pa-tinta-media">
                        {{ $partido->equipoLocal?->nombre }} vs {{ $partido->equipoVisitante?->nombre }}
                    </p>
                    <p class="text-sm text-pa-tinta-media">
                        {{ $partido->categoria?->nombreCategoria }} · {{ $partido->fase?->nombre }}
                        @if ($partido->golesEquipoLocal !== null)
                            · Resultado {{ $partido->golesEquipoLocal }} a {{ $partido->golesEquipoVisitante }}
                        @endif
                    </p>
                </div>

                <x-ui.select label="Fecha destino" name="idFechas" required>
                    <option value="">Elegí una fecha</option>
                    @foreach ($fechas as $fecha)
                        <option value="{{ $fecha->id }}" @selected((int) old('idFechas') === $fecha->id)>{{ $fecha->nombre }}</option>
                    @endforeach
                </x-ui.select>

                @if ($requiereInterzonal)
                    <label class="flex items-start gap-2 text-sm text-pa-tinta-media">
                        <input type="checkbox" name="confirmar_interzonal" value="1" class="mt-0.5 rounded" @checked(old('confirmar_interzonal'))>
                        <span>Los equipos son de grupos distintos. Confirmo que es un partido interzonal.</span>
                    </label>
                @endif

                <p class="text-sm text-pa-tinta-media">
                    El partido deja de ser instancia final. Conserva la planilla, los goles y los cobros, y su resultado pasa a sumar en la tabla de posiciones.
                </p>
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Mover partido
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
