@extends('Panel.admin')

@section('detalle')
    @include('components.loader')

    @php
        $contextoNavegacion = $contextoNavegacion ?? [];
        $origen = $contextoNavegacion['origen'] ?? request('origen');

        $parametrosPartido = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'idCategoria' => $contextoNavegacion['idCategoria'] ?? request('idCategoria'),
            'idGrupo' => $contextoNavegacion['idGrupo'] ?? request('idGrupo'),
            'idFecha' => $contextoNavegacion['idFecha'] ?? request('idFecha'),
            'horario' => $contextoNavegacion['horario_filtro'] ?? request('horario_filtro'),
            'busqueda' => $contextoNavegacion['busqueda'] ?? request('busqueda'),
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $parametrosInstancia = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'idCategoria' => $contextoNavegacion['idCategoria'] ?? request('idCategoria'),
            'idFase' => $contextoNavegacion['idFase'] ?? request('idFase'),
            'idCopa' => $contextoNavegacion['idCopa'] ?? request('idCopa'),
            'estado' => $contextoNavegacion['estado'] ?? request('estado'),
            'busqueda' => $contextoNavegacion['busqueda'] ?? request('busqueda'),
            'fecha' => $contextoNavegacion['fecha'] ?? request('fecha'),
            'horario' => $contextoNavegacion['horario_filtro'] ?? request('horario_filtro'),
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $horarioAdminRegreso = $contextoNavegacion['horario_filtro'] ?? request('horario_filtro');

        if (($horarioAdminRegreso === null || $horarioAdminRegreso === '') && !empty($horarioSeleccionado) && $horarioSeleccionado !== '0') {
            $horarioAdminRegreso = $horarioSeleccionado;
        }

        $parametrosAdmin = array_filter([
            'idEdicion' => $EdicionSeleccionada->id,
            'horario' => $horarioAdminRegreso,
        ], static fn ($valor) => $valor !== null && $valor !== '');

        $volverAInstancias = $tipoPartido === 'instanciaFinal' || $origen === 'instancia-final-index';
        $rutaVolver = $volverAInstancias ? route('instancia_final.index', $parametrosInstancia) : route('admin', $parametrosAdmin);

        $rutaCargarResultado = route($tipoPartido === 'instanciaFinal' ? 'cargar-resultado-instancia' : 'cargar-resultado',
            array_merge([
                'idEdicion' => $EdicionSeleccionada->id,
                'idPartido' => $partido->id,
            ], $volverAInstancias ? $parametrosInstancia : $parametrosPartido)
        );

        $golesConfirmados = $partido->goles_confirmados_at !== null;
        $resultadoCargado = $partido->golesEquipoLocal !== null && $partido->golesEquipoVisitante !== null;

        if ($golesConfirmados) {
            $estadoGolesClase = 'bg-emerald-100 text-emerald-700';
            $estadoGolesLabel = 'Goles confirmados';
            $estadoGolesDetalle = 'Confirmados el '.$partido->goles_confirmados_at->format('d/m/Y H:i');
        } elseif ($resultadoCargado) {
            $estadoGolesClase = 'bg-amber-100 text-amber-700';
            $estadoGolesLabel = 'Goles pendientes de carga';
            $estadoGolesDetalle = 'Resultado oficial cargado. Falta revisar y confirmar los goles individuales.';
        } else {
            $estadoGolesClase = 'bg-slate-100 text-slate-700';
            $estadoGolesLabel = 'Resultado pendiente de carga';
            $estadoGolesDetalle = 'Todavia no hay resultado oficial cargado para revisar goles.';
        }
    @endphp

    <div class="w-full p-6 bg-white rounded-lg shadow-md">
        <!-- Botón de Volver -->
        <a href="{{ $rutaVolver }}"
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

        <div class="mb-6 flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Estado de goles</p>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $estadoGolesClase }}">
                        {{ $estadoGolesLabel }}
                    </span>
                    <span class="text-sm text-gray-600">
                        {{ $estadoGolesDetalle }}
                    </span>
                </div>
            </div>

            <form action="{{ route($golesConfirmados ? 'planilla.quitarConfirmacionGoles' : 'planilla.confirmarGoles') }}" method="POST" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="hidden" name="partido_id" value="{{ $partido->id }}">
                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
                <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
                <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
                <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
                <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
                <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
                <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
                <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
                <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
                <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
                <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
                <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

                <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold text-white {{ $golesConfirmados ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700' }} transition duration-200">
                    {{ $golesConfirmados ? 'Quitar confirmacion' : 'Confirmar goles' }}
                </button>
            </form>
        </div>

        @if (is_null($partido->golesEquipoLocal) && is_null($partido->golesEquipoVisitante))
            <div class="mb-4">
                <a href="{{ $rutaCargarResultado }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md">
                    Cargar resultado
                </a>
            </div>
        @endif

        <!-- Equipo Local -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoLocal->nombre }}</h2>
        @php $localCount = $jugadoresLocalPlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $localCount > 18 ? 'text-red-600' : ($localCount == 18 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $localCount }} / 18
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button data-print-url="{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoLocal, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                onclick="printExternal(this.dataset.printUrl)"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Locales -->
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between" data-player-search-panel="local">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <div class="w-full lg:w-[46rem] xl:w-[52rem]">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <div class="relative mt-1 flex items-center gap-2">
                    <input type="search" data-player-search-input="local" placeholder="Buscar por DNI"
                        class="block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3">
                    <div class="relative shrink-0">
                        <button type="button" data-player-filter-toggle="local"
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                            Filtros
                        </button>
                        <div data-player-filter-menu="local"
                            class="hidden absolute right-0 z-20 mt-2 w-56 rounded-md border border-gray-200 bg-white p-3 shadow-lg">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" data-player-filter-dni="local"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                DNI (predefinido)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span data-player-chip-dni="local"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                        <span>Filtro: DNI</span>
                        <button type="button" data-player-clear-dni="local" class="text-blue-700 hover:text-blue-900">x</button>
                    </span>
                    <span data-player-chip-field="local"
                        class="hidden inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span data-player-chip-field-text="local"></span>
                        <button type="button" data-player-clear-field="local" class="text-emerald-700 hover:text-emerald-900">x</button>
                    </span>
                    <button type="button" data-player-reset-search="local"
                        class="hidden rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                        Restablecer
                    </button>
                </div>
                <div data-player-search-suggestions="local"
                    class="hidden mt-2 rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Elegí por cuál buscar</p>
                    <div class="grid gap-1">
                        <button type="button" data-player-select-field="name" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="lastname" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="dni" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="number" data-player-field-scope="local"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                    </div>
                </div>
            </div>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="local">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
            <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
            <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
            <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
            <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
            <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
            <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
            <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
            <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
            <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
            <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

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
                            data-player-dni="{{ $jugador->dni_jugador }}"
                            data-player-number="{{ $jugador->numero_camiseta }}">
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
                                        value="{{ $jugador->goles }}" min="0"
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
                                    data-player-dni="{{ $jugador->dni_jugador }}"
                                    data-player-number="{{ $jugador->numero_camiseta }}">
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
                                            value="{{ $jugador->goles }}" min="0" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
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

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" data-mark-attendance-all="local"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                    Marcar asistencia a todos
                </button>
                <button type="button" data-toggle-attendance-all="local" data-attendance-value="0"
                    class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition duration-200">
                    Desmarcar asistencia a todos
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200">
                    Actualizar Todos
                </button>
            </div>
        </form>
        <hr class="my-4 border-t-2 border-gray-500">

        <!-- Equipo Visitante -->
        <h2 class="text-xl font-semibold mb-2 text-center">{{ $partido->equipoVisitante->nombre }}</h2>
        @php $visitanteCount = $jugadoresVisitantePlanilla->count(); @endphp
        <p class="text-center mb-2 font-medium {{ $visitanteCount > 18 ? 'text-red-600' : ($visitanteCount == 18 ? 'text-yellow-600' : 'text-green-600') }}">
            Jugadores en planilla: {{ $visitanteCount }} / 18
        </p>
        <!-- Botón para generar el PDF -->
        <!-- Botón para generar el PDF -->
        <div class="flex justify-end space-x-2">
            <button data-print-url="{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $partido->idEquipoVisitante, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                onclick="printExternal(this.dataset.printUrl)"
                class="inline-block mb-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300 cursor-pointer">
                Imprimir Planilla
            </button>
        </div>

        <!-- Formulario para actualizar jugadores Visitantes -->
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between" data-player-search-panel="visitante">
            <h3 class="text-lg font-semibold text-center sm:text-left">Lista de Buena Fe</h3>
            <div class="w-full lg:w-[46rem] xl:w-[52rem]">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Buscar jugador</span>
                <div class="relative mt-1 flex items-center gap-2">
                    <input type="search" data-player-search-input="visitante" placeholder="Buscar por DNI"
                        class="block w-full h-9 border border-gray-300 rounded-md shadow-sm text-sm px-3">
                    <div class="relative shrink-0">
                        <button type="button" data-player-filter-toggle="visitante"
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                            Filtros
                        </button>
                        <div data-player-filter-menu="visitante"
                            class="hidden absolute right-0 z-20 mt-2 w-56 rounded-md border border-gray-200 bg-white p-3 shadow-lg">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" data-player-filter-dni="visitante"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                DNI (predefinido)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span data-player-chip-dni="visitante"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                        <span>Filtro: DNI</span>
                        <button type="button" data-player-clear-dni="visitante" class="text-blue-700 hover:text-blue-900">x</button>
                    </span>
                    <span data-player-chip-field="visitante"
                        class="hidden inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span data-player-chip-field-text="visitante"></span>
                        <button type="button" data-player-clear-field="visitante" class="text-emerald-700 hover:text-emerald-900">x</button>
                    </span>
                    <button type="button" data-player-reset-search="visitante"
                        class="hidden rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                        Restablecer
                    </button>
                </div>
                <div data-player-search-suggestions="visitante"
                    class="hidden mt-2 rounded-md border border-gray-200 bg-white p-2 shadow-sm">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Elegí por cuál buscar</p>
                    <div class="grid gap-1">
                        <button type="button" data-player-select-field="name" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="lastname" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="dni" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                        <button type="button" data-player-select-field="number" data-player-field-scope="visitante"
                            class="rounded-md px-2 py-1 text-left text-sm text-gray-700 hover:bg-gray-100"></button>
                    </div>
                </div>
            </div>
        </div>
    <form action="{{ route('planilla.actualizarJugadores') }}" method="POST" data-player-scope="visitante">
            @csrf
            <input type="hidden" name="partido_id" value="{{ $partido->id }}">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <input type="hidden" name="horario" value="{{ $horarioSeleccionado }}">
            <input type="hidden" name="tipoPartido" value="{{ $tipoPartido }}">
            <input type="hidden" name="origen" value="{{ $contextoNavegacion['origen'] ?? request('origen') }}">
            <input type="hidden" name="idCategoria" value="{{ $contextoNavegacion['idCategoria'] ?? request('idCategoria') }}">
            <input type="hidden" name="idGrupo" value="{{ $contextoNavegacion['idGrupo'] ?? request('idGrupo') }}">
            <input type="hidden" name="idFase" value="{{ $contextoNavegacion['idFase'] ?? request('idFase') }}">
            <input type="hidden" name="idCopa" value="{{ $contextoNavegacion['idCopa'] ?? request('idCopa') }}">
            <input type="hidden" name="estado" value="{{ $contextoNavegacion['estado'] ?? request('estado') }}">
            <input type="hidden" name="busqueda" value="{{ $contextoNavegacion['busqueda'] ?? request('busqueda') }}">
            <input type="hidden" name="idFecha" value="{{ $contextoNavegacion['idFecha'] ?? request('idFecha') }}">
            <input type="hidden" name="fecha" value="{{ $contextoNavegacion['fecha'] ?? request('fecha') }}">
            <input type="hidden" name="horario_filtro" value="{{ $contextoNavegacion['horario_filtro'] ?? request('horario_filtro') }}">

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
                            data-player-dni="{{ $jugador->dni_jugador }}"
                            data-player-number="{{ $jugador->numero_camiseta }}">
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
                                        value="{{ $jugador->goles }}" min="0"
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
                                    data-player-dni="{{ $jugador->dni_jugador }}"
                                    data-player-number="{{ $jugador->numero_camiseta }}">
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
                                            value="{{ $jugador->goles }}" min="0" class="w-full min-w-0 h-9 border-gray-300 rounded-md shadow-sm text-center text-xs md:text-sm">
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

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" data-mark-attendance-all="visitante"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                    Marcar asistencia a todos
                </button>
                <button type="button" data-toggle-attendance-all="visitante" data-attendance-value="0"
                    class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition duration-200">
                    Desmarcar asistencia a todos
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200">
                    Actualizar Todos
                </button>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchPanels = document.querySelectorAll('[data-player-search-panel]');
            const searchStateByScope = new Map();
            const searchFieldLabels = {
                name: 'Nombre',
                lastname: 'Apellido',
                dni: 'DNI',
                number: 'Numero'
            };

            const isValidField = (field) => Object.prototype.hasOwnProperty.call(searchFieldLabels, field);

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
                const numberInput = item.querySelector('input[name$="[numero_camiseta]"]');

                if (nameInput) item.dataset.playerName = nameInput.value ?? '';
                if (lastnameInput) item.dataset.playerLastname = lastnameInput.value ?? '';
                if (dniInput) item.dataset.playerDni = dniInput.value ?? '';
                if (numberInput) item.dataset.playerNumber = numberInput.value ?? '';
            };

            const collectItemValues = (item) => {
                refreshItemDataset(item);

                return [
                    item.dataset.playerName,
                    item.dataset.playerLastname,
                    item.dataset.playerDni,
                    item.dataset.playerNumber
                ].filter(Boolean).join(' ');
            };

            const defaultState = () => ({
                query: '',
                dniPredefined: true,
                selectedField: null
            });

            const getScopeMode = (state) => {
                if (state.dniPredefined) {
                    return 'dni';
                }

                if (state.selectedField && isValidField(state.selectedField)) {
                    return state.selectedField;
                }

                return 'general';
            };

            const matchesTokens = (value, tokens) => tokens.every((token) => value.includes(token));

            const matchesByMode = (item, mode, tokens) => {
                if (!tokens.length) {
                    return true;
                }

                refreshItemDataset(item);

                const fieldValue = {
                    name: normalizeText(item.dataset.playerName ?? ''),
                    lastname: normalizeText(item.dataset.playerLastname ?? ''),
                    dni: normalizeText(item.dataset.playerDni ?? ''),
                    number: normalizeText(item.dataset.playerNumber ?? ''),
                    general: normalizeText(collectItemValues(item))
                };

                if (mode === 'name' || mode === 'lastname' || mode === 'dni' || mode === 'number') {
                    return matchesTokens(fieldValue[mode], tokens);
                }

                return matchesTokens(fieldValue.general, tokens);
            };

            const setItemVisibility = (item, isVisible) => {
                if (isVisible) {
                    item.style.removeProperty('visibility');
                    item.style.removeProperty('position');
                    item.style.removeProperty('height');
                    return;
                }

                item.style.visibility = 'collapse';
                item.style.position = 'absolute';
                item.style.height = '0';
            };

            const filterItems = (scope) => {
                const state = searchStateByScope.get(scope);
                if (!state) {
                    return;
                }

                const mode = getScopeMode(state);
                const tokens = normalizeText(state.query).split(/\s+/).filter(Boolean);
                const items = document.querySelectorAll(`[data-player-item="${scope}"]`);

                items.forEach((item) => {
                    setItemVisibility(item, matchesByMode(item, mode, tokens));
                });
            };

            const getPlaceholder = (state) => {
                if (state.dniPredefined) {
                    return 'Buscar por DNI';
                }

                if (state.selectedField && isValidField(state.selectedField)) {
                    return `Buscar por ${searchFieldLabels[state.selectedField].toLowerCase()}`;
                }

                return 'Buscar por nombre, apellido, DNI o numero';
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

            const renderScope = (scope) => {
                const panel = document.querySelector(`[data-player-search-panel="${scope}"]`);
                const state = searchStateByScope.get(scope);

                if (!panel || !state) {
                    return;
                }

                const input = panel.querySelector(`[data-player-search-input="${scope}"]`);
                const dniCheckbox = panel.querySelector(`[data-player-filter-dni="${scope}"]`);
                const dniChip = panel.querySelector(`[data-player-chip-dni="${scope}"]`);
                const fieldChip = panel.querySelector(`[data-player-chip-field="${scope}"]`);
                const fieldChipText = panel.querySelector(`[data-player-chip-field-text="${scope}"]`);
                const suggestions = panel.querySelector(`[data-player-search-suggestions="${scope}"]`);
                const suggestionButtons = panel.querySelectorAll(`[data-player-field-scope="${scope}"]`);
                const resetButton = panel.querySelector(`[data-player-reset-search="${scope}"]`);
                const showSuggestions = !state.dniPredefined && !state.selectedField && normalizeText(state.query).length > 0;
                const showReset = normalizeText(state.query).length > 0 || !state.dniPredefined || !!state.selectedField;

                if (input) {
                    if (input.value !== state.query) {
                        input.value = state.query;
                    }

                    input.placeholder = getPlaceholder(state);
                }

                if (dniCheckbox) {
                    dniCheckbox.checked = state.dniPredefined;
                }

                if (dniChip) {
                    dniChip.classList.toggle('hidden', !state.dniPredefined);
                }

                if (fieldChip && fieldChipText) {
                    const showFieldChip = !state.dniPredefined && !!state.selectedField && isValidField(state.selectedField);
                    fieldChip.classList.toggle('hidden', !showFieldChip);
                    if (showFieldChip) {
                        fieldChipText.textContent = `Campo: ${searchFieldLabels[state.selectedField]}`;
                    }
                }

                if (suggestions) {
                    suggestions.classList.toggle('hidden', !showSuggestions);
                }

                suggestionButtons.forEach((button) => {
                    const field = button.dataset.playerSelectField;
                    if (!isValidField(field)) {
                        return;
                    }

                    button.textContent = `Buscar ${searchFieldLabels[field]}: "${state.query}"`;
                });

                if (resetButton) {
                    resetButton.classList.toggle('hidden', !showReset);
                }

                filterItems(scope);
            };

            const updateScopeState = (scope, partialState) => {
                const currentState = searchStateByScope.get(scope);
                if (!currentState) {
                    return;
                }

                Object.assign(currentState, partialState);

                if (currentState.dniPredefined) {
                    currentState.selectedField = null;
                }

                if (currentState.selectedField && !isValidField(currentState.selectedField)) {
                    currentState.selectedField = null;
                }

                renderScope(scope);
            };

            const closeAllFilterMenus = () => {
                searchPanels.forEach((panel) => {
                    const menu = panel.querySelector('[data-player-filter-menu]');
                    if (menu) {
                        menu.classList.add('hidden');
                    }
                });
            };

            searchPanels.forEach((panel) => {
                const scope = panel.dataset.playerSearchPanel;
                if (!scope) {
                    return;
                }

                searchStateByScope.set(scope, defaultState());

                const input = panel.querySelector(`[data-player-search-input="${scope}"]`);
                const toggleButton = panel.querySelector(`[data-player-filter-toggle="${scope}"]`);
                const filterMenu = panel.querySelector(`[data-player-filter-menu="${scope}"]`);
                const dniCheckbox = panel.querySelector(`[data-player-filter-dni="${scope}"]`);
                const clearDniButton = panel.querySelector(`[data-player-clear-dni="${scope}"]`);
                const clearFieldButton = panel.querySelector(`[data-player-clear-field="${scope}"]`);
                const resetButton = panel.querySelector(`[data-player-reset-search="${scope}"]`);
                const suggestionButtons = panel.querySelectorAll(`[data-player-field-scope="${scope}"]`);

                renderScope(scope);

                if (toggleButton && filterMenu) {
                    toggleButton.addEventListener('click', (event) => {
                        event.stopPropagation();
                        const isClosed = filterMenu.classList.contains('hidden');
                        closeAllFilterMenus();
                        if (isClosed) {
                            filterMenu.classList.remove('hidden');
                        }
                    });

                    filterMenu.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });
                }

                if (input) {
                    input.addEventListener('input', () => {
                        updateScopeState(scope, { query: input.value });
                    });
                }

                if (dniCheckbox) {
                    dniCheckbox.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });

                    dniCheckbox.addEventListener('change', () => {
                        updateScopeState(scope, {
                            dniPredefined: dniCheckbox.checked,
                            selectedField: null
                        });
                    });
                }

                if (clearDniButton) {
                    clearDniButton.addEventListener('click', () => {
                        updateScopeState(scope, {
                            dniPredefined: false,
                            selectedField: null
                        });
                    });
                }

                if (clearFieldButton) {
                    clearFieldButton.addEventListener('click', () => {
                        updateScopeState(scope, { selectedField: null });
                    });
                }

                if (resetButton) {
                    resetButton.addEventListener('click', () => {
                        updateScopeState(scope, {
                            query: '',
                            dniPredefined: true,
                            selectedField: null
                        });
                    });
                }

                suggestionButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const field = button.dataset.playerSelectField;
                        if (!isValidField(field)) {
                            return;
                        }

                        updateScopeState(scope, {
                            dniPredefined: false,
                            selectedField: field
                        });
                    });
                });
            });

            document.addEventListener('click', () => {
                closeAllFilterMenus();
            });

            const updateForms = document.querySelectorAll('form[data-player-scope]');
            const attendanceToggleButtons = document.querySelectorAll('[data-mark-attendance-all], [data-toggle-attendance-all]');

            attendanceToggleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const scope = button.dataset.markAttendanceAll || button.dataset.toggleAttendanceAll;
                    if (!scope) {
                        return;
                    }

                    const form = document.querySelector(`form[data-player-scope="${scope}"]`);
                    if (!form) {
                        return;
                    }

                    const attendanceCheckboxes = form.querySelectorAll('input[type="checkbox"][name$="[asistencia]"]');
                    const shouldCheck = button.dataset.attendanceValue !== '0';

                    attendanceCheckboxes.forEach((checkbox) => {
                        checkbox.checked = shouldCheck;
                    });

                    syncGroupValues(scope);
                    filterItems(scope);
                });
            });

            updateForms.forEach((form) => {
                form.addEventListener('submit', () => {
                    const scope = form.dataset.playerScope;

                    if (!scope) {
                        return;
                    }

                    const items = form.querySelectorAll(`[data-player-item="${scope}"]`);
                    items.forEach((item) => {
                        item.style.removeProperty('visibility');
                        item.style.removeProperty('position');
                        item.style.removeProperty('height');
                    });

                    syncGroupValues(scope);

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
