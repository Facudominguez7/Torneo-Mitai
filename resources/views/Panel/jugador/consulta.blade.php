@extends('Panel.admin')

@section('contenido')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <!-- Título -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">
                <i class="fas fa-search mr-2"></i>Consulta de Jugador
            </h1>
            <p class="text-gray-300 mt-2">Busca información detallada de un jugador por su DNI - Edición: <span class="font-semibold">{{ $edicionActual?->nombre ?? 'Sin edición seleccionada' }}</span></p>
        </div>

        <!-- Formulario de Búsqueda -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
            <form action="{{ route('jugador.buscar') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada?->id }}">
                
                <div>
                    <label for="dni" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        DNI del Jugador
                    </label>
                    <input 
                        type="number" 
                        id="dni" 
                        name="dni" 
                        value="{{ $dni ?? old('dni', request('dni')) }}"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Ej: 12345678"
                        required
                    >
                </div>

                <div class="flex items-end">
                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-200 flex items-center justify-center"
                    >
                        <i class="fas fa-search mr-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Mensajes de Error/Éxito -->
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p class="font-bold">Error</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <!-- Resultados de la Búsqueda -->
        @if(isset($jugador))
            <!-- Información del Jugador -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-3 mr-4">
                        <i class="fas fa-user text-blue-600 dark:text-blue-300 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                            {{ $jugador->apellido }}, {{ $jugador->nombre }}
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">DNI: {{ $jugador->dni }}</p>
                    </div>
                </div>

                @if(isset($sinRegistrosDePartidos) && $sinRegistrosDePartidos)
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded" role="alert">
                        <p class="font-bold">Sin registros de partidos</p>
                        <p>Este jugador no tiene partidos registrados en esta edición, pero está inscripto en la Lista de Buena Fe.</p>
                        @if(isset($listaBuenaFe) && $listaBuenaFe->edicion && $listaBuenaFe->edicion->id != $edicionActual->id)
                            <p class="mt-1 text-sm">Última edición registrada: <strong>{{ $listaBuenaFe->edicion->nombre }}</strong></p>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Equipo</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $equipoActual?->nombre ?? 'Sin equipo asignado' }}</p>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Categoría</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $categoriaActual?->nombreCategoria ?? 'Sin categoría' }}</p>
                    </div>
                    
                    <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Goles</p>
                        <p class="text-lg font-semibold text-green-600 dark:text-green-300">{{ $totalGoles }}</p>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Partidos Jugados</p>
                        <p class="text-lg font-semibold text-blue-600 dark:text-blue-300">{{ $totalPartidos }}</p>
                    </div>
                </div>

                @if($totalTarjetasRojas > 0)
                    <div class="mt-4 bg-red-50 dark:bg-red-900 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Tarjetas Rojas</p>
                        <p class="text-lg font-semibold text-red-600 dark:text-red-300">{{ $totalTarjetasRojas }}</p>
                    </div>
                @endif
            </div>

            <!-- Partidos donde Hizo Goles -->
            @if($partidosConGoles->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-futbol text-green-600 mr-2"></i>Partidos donde Hizo Goles
                    </h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha/Fase</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Partido</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resultado</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Goles</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($partidosConGoles as $partido)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $partido['tipo'] == 'Instancia Final' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' }}">
                                                {{ $partido['tipo'] }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ ($partido['origen'] ?? 'partido') === 'instancia_final' ? 'IF-' : 'P-' }}{{ $partido['idReferencia'] ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $partido['fecha'] }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            {{ $partido['equipoLocal'] }} vs {{ $partido['equipoVisitante'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $partido['golesLocal'] ?? '-' }} - {{ $partido['golesVisitante'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-3 py-1 inline-flex text-sm font-bold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                {{ $partido['goles_jugador'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Todos los Partidos Jugados -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">
                    <i class="fas fa-list text-blue-600 mr-2"></i>Historial Completo de Partidos
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha/Fase</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Partido</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resultado</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Goles</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Asistió</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Camiseta</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($partidosJugados as $partido)
                                <tr class="{{ $partido['goles_jugador'] > 0 ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $partido['tipo'] == 'Instancia Final' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' }}">
                                            {{ $partido['tipo'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ ($partido['origen'] ?? 'partido') === 'instancia_final' ? 'IF-' : 'P-' }}{{ $partido['idReferencia'] ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $partido['fecha'] }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $partido['equipoLocal'] }} vs {{ $partido['equipoVisitante'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $partido['golesLocal'] ?? '-' }} - {{ $partido['golesVisitante'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($partido['goles_jugador'] > 0)
                                            <span class="px-3 py-1 inline-flex text-sm font-bold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                {{ $partido['goles_jugador'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($partido['asistio'])
                                            <i class="fas fa-check-circle text-green-600"></i>
                                        @else
                                            <i class="fas fa-times-circle text-red-600"></i>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">
                                        {{ $partido['numero_camiseta'] ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
