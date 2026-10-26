{{-- Fragmento del modal del buscador del header: misma información que Consulta de jugador. --}}
@isset($edicionActual)
    <p class="text-sm text-gray-600">Edición: <span class="font-semibold text-brand-navy">{{ $edicionActual->nombre }}</span></p>
@endisset

@if ($error ?? null)
    <div data-buscador-jugador-sin-resultado role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
        <p class="font-bold">Sin resultados</p>
        <p>{{ $error }}</p>
    </div>
@else
    <div data-buscador-jugador-encontrado>
        @include('Panel.jugador._resultado')

        <form action="{{ route('jugador.buscar') }}" method="POST" class="mt-6 flex justify-end">
            @csrf
            <input type="hidden" name="dni" value="{{ $dni }}">
            <input type="hidden" name="idEdicion" value="{{ $edicionActual->id }}">
            <x-ui.button type="submit" variant="secondary">Ver en Consulta de jugador</x-ui.button>
        </form>
    </div>
@endif
