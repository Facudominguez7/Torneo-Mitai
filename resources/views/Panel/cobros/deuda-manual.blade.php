@extends('Panel.admin')

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-ui.page-header title="Cargar deuda de planilla" />
        @if (! $EdicionSeleccionada)
            <x-ui.empty-state message="Seleccioná una edición" description="Elegí una edición en la barra superior para cargar una deuda." />
        @else
            @if (session('success'))<p role="status" class="rounded-md border border-brand-teal bg-white p-3 text-brand-navy">{{ session('success') }}</p>@endif
            @if ($errors->any())
                <div role="alert" class="rounded-md border border-red-300 bg-white p-3 text-red-700">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="rounded-lg border border-gray-200 bg-white">
                <form method="GET" action="{{ route('cobros.deuda-manual') }}" class="space-y-2 p-4 sm:px-6">
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <label for="idEquipo" class="block text-sm font-semibold text-gray-700">Equipo</label>
                    <div class="flex flex-wrap gap-2">
                        <select id="idEquipo" name="idEquipo" class="min-h-11 min-w-0 flex-1 rounded-md border-gray-300" required>
                            <option value="">Elegí un equipo</option>
                            @foreach ($equipos as $opcion)
                                <option value="{{ $opcion->id }}" @selected((string) $equipo?->id === (string) $opcion->id)>{{ $opcion->nombre }}</option>
                            @endforeach
                        </select>
                        <button class="min-h-11 rounded-md border border-gray-300 px-4 font-semibold text-gray-700">Ver partidos</button>
                    </div>
                </form>
                @if ($equipo)
                    <form method="POST" action="{{ route('cobros.deudas-manuales') }}">
                        @csrf
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <input type="hidden" name="idEquipo" value="{{ $equipo->id }}">
                        <div class="grid gap-5 px-4 pb-5 sm:px-6">
                            <fieldset>
                                <legend class="mb-2 text-sm font-semibold text-gray-700">Partido adeudado</legend>
                                <div class="grid gap-2">
                                    @forelse ($partidos as $partido)
                                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 {{ $partido['tiene_cargo'] ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }}">
                                            <input type="radio" name="partido" value="{{ $partido['valor'] }}" @checked(old('partido') === $partido['valor']) @disabled($partido['tiene_cargo']) class="text-brand-navy focus:ring-brand-teal">
                                            <span class="min-w-0 flex-1"><span class="font-semibold">{{ $partido['etapa'] }} · {{ $partido['fecha']->locale('es')->isoFormat('dddd DD/MM · HH:mm') }}</span><br><span class="text-sm text-gray-700">vs {{ $partido['rival'] }} · Cancha {{ $partido['cancha'] ?? 'sin asignar' }}</span></span>
                                            <span class="text-sm text-gray-500">{{ $partido['tiene_cargo'] ? 'Ya tiene cargo' : 'Sin cargo' }}</span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-gray-600">No hay partidos jugados para este equipo.</p>
                                    @endforelse
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Se listan los partidos ya jugados del equipo en esta edición. Los que ya tienen cargo no se pueden volver a cargar.</p>
                            </fieldset>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label for="importe_ars" class="block text-sm font-semibold text-gray-700">Importe (ARS)</label><input id="importe_ars" name="importe_ars" inputmode="numeric" value="{{ old('importe_ars', $tarifa) }}" required class="mt-1 min-h-11 w-full rounded-md border-gray-300"><p class="mt-1 text-sm text-gray-500">Por defecto, la tarifa vigente.</p></div>
                                <div><label for="motivo" class="block text-sm font-semibold text-gray-700">Motivo (opcional)</label><input id="motivo" name="motivo" maxlength="255" value="{{ old('motivo') }}" placeholder="Ej. Planilla anterior a activar los cobros" class="mt-1 min-h-11 w-full rounded-md border-gray-300"></div>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 p-4 sm:px-6">
                            <a href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id]) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 font-semibold text-gray-700">Cancelar</a>
                            <button class="min-h-11 rounded-md bg-brand-navy px-4 font-semibold text-white">Cargar deuda</button>
                        </div>
                    </form>
                @else
                    <p class="px-4 pb-5 text-sm text-gray-600 sm:px-6">Elegí un equipo para ver sus partidos jugados.</p>
                @endif
            </div>
            <p class="text-sm text-gray-500">La deuda queda como cualquier cargo: se paga, se exime o se marca «no vino» y queda en el historial.</p>
        @endif
    </div>
@endsection
