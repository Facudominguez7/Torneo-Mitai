@extends('Panel.admin')

@section('detalle')
    @include('components.loader')

    <div class="w-full p-6 bg-white rounded-lg shadow-md">
        <!-- Botón de Volver -->
        <a href="{{ $tipoPartido === 'instanciaFinal' ? route('instancia_final', ['idEdicion' => $EdicionSeleccionada->id, 'horario' => $horarioSeleccionado, 'fecha' => request('fecha')]) : route('admin', ['idEdicion' => $EdicionSeleccionada->id, 'horario' => $horarioSeleccionado]) }}"
            class="inline-block mb-4 text-blue-500 hover:text-blue-700">
            <svg class="w-6 h-6 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Volver
        </a>
        <h1 class="text-2xl font-bold mb-4">Planilla de Jugadores - Partido {{ $partido->id }}</h1>

        <div class="mb-4">
            <p class="text-gray-700"> {{ $partido->categoria->nombreCategoria }}</p>
            <p class="text-gray-700"><strong>Horario:</strong>
                {{ $partido->horario ? $partido->horario : \Carbon\Carbon::parse($partido->horario_datetime)->format('d-m-Y H:i') }}
            </p>
            <p class="text-gray-700"><strong>Cancha:</strong> {{ $partido->cancha }}</p>
        </div>

        @if (is_null($partido->golesEquipoLocal) && is_null($partido->golesEquipoVisitante))
            <div class="mb-4">
                <a href="{{ route($tipoPartido === 'instanciaFinal' ? 'cargar-resultado-instancia' : 'cargar-resultado', [
                    'idEdicion' => $EdicionSeleccionada->id,
                    'idPartido' => $partido->id,
                ]) }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md">
                    Cargar resultado
                </a>
            </div>
        @endif

        <!-- Equipo Local -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoLocal->nombre }}</h2>
        @php $localCount = $jugadoresLocalPlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $localCount > 16 ? 'text-red-600' : ($localCount == 16 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $localCount }} / 16
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoLocal, 'idEdicion' => $EdicionSeleccionada->id]) }}')"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Locales -->
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <label class="w-full sm:w-72">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <input type="search" data-player-search="local"
                    placeholder="Nombre, apellido o DNI"
                    class="mt-1 block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3"
                >
            </label>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="local">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">

            @if ($jugadoresLocalPlanilla->isEmpty())
                <p class="text-center">No hay jugadores en el equipo local.</p>
            @else
                <div class="sm:hidden space-y-4 mb-6">
                    @foreach ($jugadoresLocalPlanilla as $jugador)
                        <div class="p-3 border border-gray-200 rounded-lg shadow-sm bg-white"
                            data-player-item="local"
                            data-player-key="{{ $jugador->dni_jugador }}"
                            data-player-name="{{ $jugador->nombre }}"
                            data-player-lastname="{{ $jugador->apellido }}"
                            data-player-dni="{{ $jugador->dni_jugador }}">
                            <div class="grid grid-cols-1 gap-3">
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]" value="{{ $jugador->nombre }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Apellido</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]" value="{{ $jugador->apellido }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de Nacimiento</label>
                                    <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                        value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">DNI</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][dni]" value="{{ $jugador->dni_jugador }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                    <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                        value="{{ $jugador->dni_jugador }}">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Número</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                        value="{{ $jugador->numero_camiseta }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goles</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                        value="{{ $jugador->goles }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Asistencia</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                        {{ $jugador->asistio ? 'checked' : '' }}
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between {{ $jugador->tarjeta_roja ? 'bg-red-50 p-2 rounded-md' : '' }}">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tarjeta Roja</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                        {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                        class="h-5 w-5 text-red-600 border-gray-300 rounded">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full min-w-[900px] bg-white border border-gray-200 mb-6">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="px-2 py-2 border-b text-left text-xs font-semibold">Nombre y Apellido</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">F. Nac.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">DNI</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">N°</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Goles</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Asist.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Roja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jugadoresLocalPlanilla as $jugador)
                                <tr data-player-item="local"
                                    data-player-key="{{ $jugador->dni_jugador }}"
                                    data-player-name="{{ $jugador->nombre }}"
                                    data-player-lastname="{{ $jugador->apellido }}"
                                    data-player-dni="{{ $jugador->dni_jugador }}">
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-left align-top md:align-middle text-[11px] md:text-sm break-words">
                                        <div class="flex w-full gap-1 md:gap-2 flex-col md:flex-row">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]"
                                                value="{{ $jugador->nombre }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]"
                                                value="{{ $jugador->apellido }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}" readonly
                                            class="border-gray-300 bg-gray-100 rounded-md shadow-sm w-full min-w-0 h-9 text-xs md:text-sm cursor-not-allowed">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input name="jugadores[{{ $jugador->dni_jugador }}][dni]"
                                            value="{{ $jugador->dni_jugador }}" readonly
                                            class="w-full min-w-0 h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-xs md:text-sm cursor-not-allowed">
                                        <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                            value="{{ $jugador->dni_jugador }}">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                            value="{{ $jugador->numero_camiseta }}"
                                            class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                            value="{{ $jugador->goles }}" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                            {{ $jugador->asistio ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle {{ $jugador->tarjeta_roja ? 'bg-red-50' : '' }}">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                            {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                            class="h-4 w-4 text-red-600 border-gray-300 rounded">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md mt-4">Actualizar Todos</button>
        </form>
        <hr class="my-4 border-t-2 border-gray-500">

        <!-- Equipo Visitante -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoVisitante->nombre }}</h2>
        @php $visitanteCount = $jugadoresVisitantePlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $visitanteCount > 16 ? 'text-red-600' : ($visitanteCount == 16 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $visitanteCount }} / 16
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoVisitante, 'idEdicion' => $EdicionSeleccionada->id]) }}')"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Visitantes -->
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <label class="w-full sm:w-72">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <input type="search" data-player-search="visitante"
                    placeholder="Nombre, apellido o DNI"
                    class="mt-1 block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3"
                >
            </label>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="visitante">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">

            @if ($jugadoresVisitantePlanilla->isEmpty())
                <p class="text-center">No hay jugadores en el equipo visitante.</p>
            @else
                <div class="sm:hidden space-y-4 mb-6">
                    @foreach ($jugadoresVisitantePlanilla as $jugador)
                        <div class="p-3 border border-gray-200 rounded-lg shadow-sm bg-white"
                            data-player-item="visitante"
                            data-player-key="{{ $jugador->dni_jugador }}"
                            data-player-name="{{ $jugador->nombre }}"
                            data-player-lastname="{{ $jugador->apellido }}"
                            data-player-dni="{{ $jugador->dni_jugador }}">
                            <div class="grid grid-cols-1 gap-3">
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]" value="{{ $jugador->nombre }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Apellido</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]" value="{{ $jugador->apellido }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de Nacimiento</label>
                                    <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                        value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">DNI</label>
                                    <input name="jugadores[{{ $jugador->dni_jugador }}][dni]" value="{{ $jugador->dni_jugador }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm">
                                    <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                        value="{{ $jugador->dni_jugador }}">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Número</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                        value="{{ $jugador->numero_camiseta }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goles</label>
                                    <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                        value="{{ $jugador->goles }}"
                                        class="h-9 border-gray-300 rounded-md shadow-sm text-sm text-center">
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Asistencia</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                        {{ $jugador->asistio ? 'checked' : '' }}
                                        class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between {{ $jugador->tarjeta_roja ? 'bg-red-50 p-2 rounded-md' : '' }}">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tarjeta Roja</label>
                                    <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                        {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                        class="h-5 w-5 text-red-600 border-gray-300 rounded">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full min-w-[900px] bg-white border border-gray-200 mb-6">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="px-2 py-2 border-b text-left text-xs font-semibold">Nombre y Apellido</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">F. Nac.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">DNI</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">N°</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Goles</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Asist.</th>
                                <th class="px-2 py-2 border-b text-xs font-semibold">Roja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jugadoresVisitantePlanilla as $jugador)
                                <tr data-player-item="visitante"
                                    data-player-key="{{ $jugador->dni_jugador }}"
                                    data-player-name="{{ $jugador->nombre }}"
                                    data-player-lastname="{{ $jugador->apellido }}"
                                    data-player-dni="{{ $jugador->dni_jugador }}">
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-left align-top md:align-middle text-[11px] md:text-sm break-words">
                                        <div class="flex w-full gap-1 md:gap-2 flex-col md:flex-row">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][nombre]"
                                                value="{{ $jugador->nombre }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                            <input name="jugadores[{{ $jugador->dni_jugador }}][apellido]"
                                                value="{{ $jugador->apellido }}" readonly
                                                class="flex-1 min-w-0 w-full h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed">
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="date" name="jugadores[{{ $jugador->dni_jugador }}][fecha_nacimiento]"
                                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}" readonly
                                            class="border-gray-300 bg-gray-100 rounded-md shadow-sm w-full min-w-0 h-9 text-xs md:text-sm cursor-not-allowed">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input name="jugadores[{{ $jugador->dni_jugador }}][dni]"
                                            value="{{ $jugador->dni_jugador }}" readonly
                                            class="w-full min-w-0 h-9 border-gray-300 bg-gray-100 rounded-md shadow-sm text-xs md:text-sm cursor-not-allowed">
                                        <input type="hidden" name="jugadores[{{ $jugador->dni_jugador }}][dni_actual]"
                                            value="{{ $jugador->dni_jugador }}">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][numero_camiseta]"
                                            value="{{ $jugador->numero_camiseta }}"
                                            class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle text-[11px] md:text-sm">
                                        <input type="number" name="jugadores[{{ $jugador->dni_jugador }}][goles]"
                                            value="{{ $jugador->goles }}" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][asistencia]"
                                            {{ $jugador->asistio ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </td>
                                    <td class="px-2 py-1 md:px-3 md:py-2 border-b text-center align-top md:align-middle {{ $jugador->tarjeta_roja ? 'bg-red-50' : '' }}">
                                        <input type="checkbox" name="jugadores[{{ $jugador->dni_jugador }}][tarjeta_roja]"
                                            {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                            class="h-4 w-4 text-red-600 border-gray-300 rounded">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md mt-4">Actualizar Todos</button>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInputs = document.querySelectorAll('[data-player-search]');

            const normalizeText = (value) => {
                if (!value) {
                    return '';
                }

                return value
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            };

            const refreshItemDataset = (item) => {
                const nameInput = item.querySelector('input[name$="[nombre]"]');
                const lastnameInput = item.querySelector('input[name$="[apellido]"]');
                const dniInput = item.querySelector('input[name$="[dni]"]');

                if (nameInput) item.dataset.playerName = nameInput.value ?? '';
                if (lastnameInput) item.dataset.playerLastname = lastnameInput.value ?? '';
                if (dniInput) item.dataset.playerDni = dniInput.value ?? '';
            };

            const collectItemValues = (item) => {
                refreshItemDataset(item);

                return [
                    item.dataset.playerName,
                    item.dataset.playerLastname,
                    item.dataset.playerDni
                ].filter(Boolean).join(' ');
            };

            const filterItems = (scope, query) => {
                const items = document.querySelectorAll(`[data-player-item="${scope}"]`);
                const normalizedQuery = normalizeText(query);

                const queryTokens = normalizedQuery.split(/\s+/).filter(Boolean);

                items.forEach((item) => {
                    const searchable = normalizeText(collectItemValues(item));

                    const matches = queryTokens.length === 0
                        ? true
                        : queryTokens.every((token) => searchable.includes(token));

                    // Usar una clase personalizada que solo oculte visualmente pero mantenga en el DOM
                    if (matches) {
                        item.style.removeProperty('visibility');
                        item.style.removeProperty('position');
                        item.style.removeProperty('height');
                    } else {
                        // Ocultar visualmente pero mantener en el flujo del formulario
                        item.style.visibility = 'collapse';
                        item.style.position = 'absolute';
                        item.style.height = '0';
                    }
                });
            };

            const syncGroupValues = (scope) => {
                const items = document.querySelectorAll(`[data-player-item="${scope}"]`);
                const groups = new Map();

                items.forEach((item) => {
                    const key = item.dataset.playerKey;
                    if (!key) {
                        return;
                    }

                    if (!groups.has(key)) {
                        groups.set(key, []);
                    }

                    groups.get(key).push(item);
                });

                groups.forEach((groupItems) => {
                    if (groupItems.length < 2) {
                        refreshItemDataset(groupItems[0]);
                        return;
                    }

                    const visible = groupItems.find((item) => item.offsetParent !== null) ?? groupItems[0];
                    refreshItemDataset(visible);

                    const sourceInputs = Array.from(visible.querySelectorAll('input, select, textarea'))
                        .filter((input) => input.name && !input.disabled);

                    groupItems.forEach((item) => {
                        if (item === visible) {
                            return;
                        }

                        sourceInputs.forEach((sourceInput) => {
                            const target = item.querySelector(`[name="${sourceInput.name}"]`);

                            if (!target) {
                                return;
                            }

                            if (['checkbox', 'radio'].includes(sourceInput.type)) {
                                target.checked = sourceInput.checked;
                            } else {
                                target.value = sourceInput.value;
                            }
                        });

                        refreshItemDataset(item);
                    });
                });
            };

            searchInputs.forEach((input) => {
                const scope = input.dataset.playerSearch;

                if (!scope) {
                    return;
                }

                const handleInput = () => filterItems(scope, input.value);

                handleInput();
                input.addEventListener('input', handleInput);
            });

            const updateForms = document.querySelectorAll('form[data-player-scope]');

            updateForms.forEach((form) => {
                form.addEventListener('submit', (e) => {
                    const scope = form.dataset.playerScope;

                    if (!scope) {
                        return;
                    }

                    // Restaurar visibilidad de TODOS los elementos antes del submit
                    const items = form.querySelectorAll(`[data-player-item="${scope}"]`);
                    items.forEach((item) => {
                        item.style.removeProperty('visibility');
                        item.style.removeProperty('position');
                        item.style.removeProperty('height');
                    });

                    // Sincronizar todos los valores entre cards y tabla
                    syncGroupValues(scope);

                    // Actualizar dataset de todos los items
                    items.forEach((item) => {
                        refreshItemDataset(item);
                    });
                });
            });
        });
    </script>
@endsection

@section('scripts')
<script>
    function printExternal(url) {
        var printFrame = document.getElementById('printFrame');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'printFrame';
            printFrame.style.display = 'none';
            document.body.appendChild(printFrame);
        }
        printFrame.src = url;
    }
</script>
@endsection
