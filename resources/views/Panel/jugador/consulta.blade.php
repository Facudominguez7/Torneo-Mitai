@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Consulta de Jugador"
        :subtitle="'Busca información detallada de un jugador por su DNI — Edición: '.($edicionActual?->nombre ?? 'Sin edición seleccionada')" />

    {{-- Formulario de Búsqueda --}}
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
        <form action="{{ route('jugador.buscar') }}" method="POST" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada?->id }}">

            <div>
                <label for="dni" class="mb-2 block text-sm font-medium text-gray-700">
                    DNI del Jugador
                </label>
                <input
                    type="number"
                    id="dni"
                    name="dni"
                    value="{{ $dni ?? old('dni', request('dni')) }}"
                    class="w-full rounded-lg border-gray-300 px-4 py-2 focus:border-brand-navy focus:ring-2 focus:ring-brand-navy/30"
                    placeholder="Ej: 12345678"
                    required
                >
            </div>

            <div class="flex items-end">
                <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                    Buscar
                </x-ui.button>
            </div>
        </form>
    </div>

    {{-- Mensajes de Error/Éxito --}}
    @if (session('error'))
        <div role="alert" class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
            <p class="font-bold">Error</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- Resultados de la Búsqueda --}}
    @if (isset($jugador))
        @include('Panel.jugador._resultado')
    @endif
@endsection
