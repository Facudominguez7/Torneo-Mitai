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
        {{-- Información del Jugador --}}
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-4 flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-navy/10">
                    <span class="text-lg font-bold text-brand-navy">{{ Str::substr($jugador->nombre, 0, 1) }}</span>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        {{ $jugador->apellido }}, {{ $jugador->nombre }}
                    </h2>
                    <p class="text-gray-600">DNI: {{ $jugador->dni }}</p>
                </div>
            </div>

            @if (isset($sinRegistrosDePartidos) && $sinRegistrosDePartidos)
                <div role="alert" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-800">
                    <p class="font-bold">Sin registros activos de partidos</p>
                    <p>Este jugador no tiene partidos activos computables en esta edición. Su historial anterior fue archivado tras la baja de Lista de Buena Fe.</p>
                    @if (isset($listaBuenaFe) && $listaBuenaFe->edicion && $listaBuenaFe->edicion->id != $edicionActual->id)
                        <p class="mt-1 text-sm">Última edición registrada: <strong>{{ $listaBuenaFe->edicion->nombre }}</strong></p>
                    @endif
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Equipo</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $equipoActual?->nombre ?? 'Sin equipo asignado' }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Categoría</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $categoriaActual?->nombreCategoria ?? 'Sin categoría' }}</p>
                </div>

                <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <p class="text-sm text-gray-500">Goles</p>
                    <p class="text-lg font-semibold text-green-600">{{ $totalGoles }}</p>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-gray-500">Partidos Jugados</p>
                    <p class="text-lg font-semibold text-blue-600">{{ $totalPartidos }}</p>
                </div>
            </div>

            @if ($totalTarjetasRojas > 0)
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-sm text-gray-500">Tarjetas Rojas</p>
                    <p class="text-lg font-semibold text-red-600">{{ $totalTarjetasRojas }}</p>
                </div>
            @endif
        </div>

        {{-- Partidos donde Hizo Goles --}}
        @if ($partidosConGoles->isNotEmpty())
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-4 text-xl font-bold text-gray-800">
                    Partidos donde Hizo Goles
                </h3>

                <x-ui.data-table :empty="false">
                    <x-slot name="head">
                        <tr>
                            <th scope="col">Tipo</th>
                            <th scope="col">ID</th>
                            <th scope="col">Fecha/Fase</th>
                            <th scope="col">Partido</th>
                            <th scope="col" class="text-center">Resultado</th>
                            <th scope="col" class="text-center">Goles</th>
                        </tr>
                    </x-slot>
                    <x-slot name="body">
                        @foreach ($partidosConGoles as $partido)
                            <tr>
                                <td>
                                    <x-ui.badge :variant="$partido['tipo'] == 'Instancia Final' ? 'info' : 'neutral'">
                                        {{ $partido['tipo'] }}
                                    </x-ui.badge>
                                </td>
                                <td>{{ ($partido['origen'] ?? 'partido') === 'instancia_final' ? 'IF-' : 'P-' }}{{ $partido['idReferencia'] ?? '-' }}</td>
                                <td>{{ $partido['fecha'] }}</td>
                                <td>{{ $partido['equipoLocal'] }} vs {{ $partido['equipoVisitante'] }}</td>
                                <td class="text-center font-semibold text-gray-900">
                                    {{ $partido['golesLocal'] ?? '-' }} - {{ $partido['golesVisitante'] ?? '-' }}
                                </td>
                                <td class="text-center">
                                    <x-ui.badge variant="success">{{ $partido['goles_jugador'] }}</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot>
                </x-ui.data-table>
            </div>
        @endif

        @if (isset($bajasArchivadas) && $bajasArchivadas->isNotEmpty())
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-4 text-xl font-bold text-gray-800">
                    Historial Archivado por Bajas
                </h3>

                <div class="space-y-6">
                    @foreach ($bajasArchivadas as $bajaArchivada)
                        <div class="overflow-hidden rounded-lg border border-amber-200">
                            <div class="bg-amber-50 px-4 py-3">
                                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-amber-800">
                                            {{ $bajaArchivada->equipo_nombre ?? 'Equipo no disponible' }} - {{ $bajaArchivada->categoria_nombre ?? 'Categoría no disponible' }}
                                        </p>
                                        <p class="text-sm text-amber-700">
                                            Baja: {{ optional($bajaArchivada->fecha_baja)->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                            @if ($bajaArchivada->motivo_baja)
                                                - Motivo: {{ $bajaArchivada->motivo_baja }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-ui.badge variant="danger">No computa para ese equipo</x-ui.badge>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 bg-white p-4 md:grid-cols-4">
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-sm text-gray-500">Goles archivados</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ $bajaArchivada->total_goles }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-sm text-gray-500">Partidos archivados</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ $bajaArchivada->total_partidos }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-sm text-gray-500">Tarjetas rojas</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ $bajaArchivada->total_tarjetas_rojas }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-sm text-gray-500">Planillas archivadas</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ $bajaArchivada->cantidad_planillas_archivadas }}</p>
                                </div>
                            </div>

                            @if (! empty($bajaArchivada->historial_planillas))
                                <div class="border-t border-amber-100">
                                    <x-ui.data-table :empty="false">
                                        <x-slot name="head">
                                            <tr>
                                                <th scope="col">Origen</th>
                                                <th scope="col">Referencia</th>
                                                <th scope="col" class="text-center">Goles</th>
                                                <th scope="col" class="text-center">Asistió</th>
                                                <th scope="col" class="text-center">Roja</th>
                                                <th scope="col" class="text-center">Camiseta</th>
                                            </tr>
                                        </x-slot>
                                        <x-slot name="body">
                                            @foreach ($bajaArchivada->historial_planillas as $planillaArchivada)
                                                <tr>
                                                    <td>{{ data_get($planillaArchivada, 'partido_type') === 'App\\Models\\InstanciaFinal' ? 'Instancia Final' : 'Partido Regular' }}</td>
                                                    <td>{{ data_get($planillaArchivada, 'partido_type') === 'App\\Models\\InstanciaFinal' ? 'IF-' : 'P-' }}{{ data_get($planillaArchivada, 'partido_id', '-') }}</td>
                                                    <td class="text-center">{{ data_get($planillaArchivada, 'goles', 0) }}</td>
                                                    <td class="text-center">{{ data_get($planillaArchivada, 'asistio') ? 'Sí' : 'No' }}</td>
                                                    <td class="text-center">{{ data_get($planillaArchivada, 'tarjeta_roja') ? 'Sí' : 'No' }}</td>
                                                    <td class="text-center">{{ data_get($planillaArchivada, 'numero_camiseta', '-') }}</td>
                                                </tr>
                                            @endforeach
                                        </x-slot>
                                    </x-ui.data-table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Todos los Partidos Jugados --}}
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="mb-4 text-xl font-bold text-gray-800">
                Historial Completo de Partidos
            </h3>

            <x-ui.data-table :empty="$partidosJugados->isEmpty()">
                <x-slot name="head">
                    <tr>
                        <th scope="col">Tipo</th>
                        <th scope="col">ID</th>
                        <th scope="col">Fecha/Fase</th>
                        <th scope="col">Partido</th>
                        <th scope="col" class="text-center">Resultado</th>
                        <th scope="col" class="text-center">Goles</th>
                        <th scope="col" class="text-center">Asistió</th>
                        <th scope="col" class="text-center">Camiseta</th>
                    </tr>
                </x-slot>
                <x-slot name="body">
                    @foreach ($partidosJugados as $partido)
                        <tr class="{{ $partido['goles_jugador'] > 0 ? 'bg-green-50' : '' }}">
                            <td>
                                <x-ui.badge :variant="$partido['tipo'] == 'Instancia Final' ? 'info' : 'neutral'">
                                    {{ $partido['tipo'] }}
                                </x-ui.badge>
                            </td>
                            <td>{{ ($partido['origen'] ?? 'partido') === 'instancia_final' ? 'IF-' : 'P-' }}{{ $partido['idReferencia'] ?? '-' }}</td>
                            <td>{{ $partido['fecha'] }}</td>
                            <td>{{ $partido['equipoLocal'] }} vs {{ $partido['equipoVisitante'] }}</td>
                            <td class="text-center font-semibold text-gray-900">
                                {{ $partido['golesLocal'] ?? '-' }} - {{ $partido['golesVisitante'] ?? '-' }}
                            </td>
                            <td class="text-center">
                                @if ($partido['goles_jugador'] > 0)
                                    <x-ui.badge variant="success">{{ $partido['goles_jugador'] }}</x-ui.badge>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($partido['asistio'])
                                    <x-ui.badge variant="success">Sí</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger">No</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-center">{{ $partido['numero_camiseta'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </x-slot>
            </x-ui.data-table>
        </div>
    @endif
@endsection
