@extends('Panel.admin')

@section('detalle')
    <div class="container mx-auto px-3 py-4 sm:px-4 sm:py-6">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-white sm:text-3xl">📋 Lista de Buena Fe</h1>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:space-x-4 sm:gap-0">
                <button
                    onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}')"
                    class="inline-flex w-full items-center justify-center rounded bg-green-500 px-4 py-2 font-bold text-white hover:bg-green-600 sm:w-auto">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                        </path>
                    </svg>
                    <span>Imprimir Planilla</span>
                </button>
                <a href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                    class="flex items-center justify-center font-medium text-white hover:text-blue-500 sm:justify-start">
                    &larr; Volver a Equipos
                </a>
            </div>
        </div>

        {{-- Mensajes de éxito/error --}}
        @if (session('success'))
            <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
            <script>
                setTimeout(function () {
                    const msg = document.getElementById('success-message');
                    if (msg) msg.remove();
                }, 60000);
            </script>
        @endif

        @if (session('error'))
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
            <script>
                setTimeout(function () {
                    const msg = document.getElementById('error-message');
                    if (msg) msg.remove();
                }, 60000);
            </script>
        @endif

        @if ($errors->any())
            <div id="validation-message" class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-red-700">
                {{ $errors->first() }}
            </div>
            <script>
                setTimeout(function() {
                    const msg = document.getElementById('validation-message');
                    if (msg) msg.remove();
                }, 60000);
            </script>
        @endif

        {{-- Información del Equipo --}}
        <div class="mb-6 flex flex-col gap-4 rounded-lg border-l-4 border-blue-500 bg-white p-4 shadow-md sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="mb-1 text-xl font-bold text-gray-800 sm:text-2xl">
                    {{ $equipoSeleccionado->equipo->nombre }}
                </h2>
                <p class="mb-2 text-base font-medium text-blue-600 sm:text-lg">
                    {{ $equipoSeleccionado->categoria->nombreCategoria }}
                </p>
                <p class="text-sm text-gray-500 sm:text-base">
                    Edición: <span class="font-semibold">{{ $EdicionSeleccionada->nombre }}</span> |
                    Jugadores Activos: <span
                        class="font-semibold {{ $jugadoresActivos->count() >= $limiteJugadoresPorPlanilla ? 'text-red-600' : 'text-green-600' }}">{{ $jugadoresActivos->count() }}/{{ $limiteJugadoresPorPlanilla }}</span>
                </p>
            </div>

            {{-- Botón PDF --}}
            <div>
                <a href="{{ route('lista-buena-fe.pdf', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                    class="flex w-full items-center justify-center rounded-md bg-red-600 px-4 py-2 text-white shadow transition hover:bg-red-700 sm:w-auto">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                        </path>
                    </svg>
                    Descargar PDF
                </a>
            </div>
        </div>

        {{-- Botón de Migración --}}
        @if ($jugadoresActivos->isEmpty())
            <div class="bg-yellow-50 border border-yellow-300 p-4 rounded-lg mb-6">
                <h3 class="font-semibold text-yellow-800 mb-2">⚠️ No hay jugadores en la Lista de Buena Fe</h3>
                <p class="text-sm text-yellow-700 mb-3">
                    Puedes migrar automáticamente los jugadores que ya han jugado partidos para este equipo.
                </p>
                <form method="POST" action="{{ route('lista-buena-fe.migrar') }}" class="inline">
                    @csrf
                    <input type="hidden" name="idEquipo" value="{{ $equipoSeleccionado->idEquipo }}">
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md"
                        onclick="return confirm('¿Deseas migrar todos los jugadores de partidos anteriores a la Lista de Buena Fe?')">
                        🔄 Migrar Jugadores Existentes
                    </button>
                </form>
            </div>
        @endif

        {{-- Formulario para Agregar Jugador --}}
        @if ($jugadoresActivos->count() < $limiteJugadoresPorPlanilla)
            <div class="mb-6 rounded-lg bg-white p-4 shadow-md sm:p-6">
                <details class="mb-4">
                    <summary
                        class="cursor-pointer rounded-md bg-gray-200 p-3 text-base font-medium text-gray-700 transition hover:bg-gray-300 sm:text-lg">
                        ➕ Agregar Jugador a la Lista de Buena Fe
                    </summary>

                    <form method="POST" action="{{ route('lista-buena-fe.agregar') }}" id="formAgregarJugador"
                        class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @csrf
                        <input type="hidden" name="idEquipo" value="{{ $equipoSeleccionado->idEquipo }}">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <input type="hidden" name="idCategoria" value="{{ $equipoSeleccionado->idCategoria }}">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">DNI del Jugador:</label>
                            <input type="text" name="dni_jugador" id="dni_jugador" required pattern="\d{7,8}"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Ej: 12345678" autocomplete="off">
                            {{-- Indicador de estado de búsqueda --}}
                            <div id="dni_status" class="mt-1 text-sm hidden">
                                <span id="dni_buscando" class="text-blue-600 hidden">
                                    <svg class="animate-spin inline h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Buscando...
                                </span>
                                <span id="dni_encontrado" class="text-green-600 hidden">
                                    ✅ <span id="dni_mensaje_encontrado"></span>
                                </span>
                                <span id="dni_no_encontrado" class="text-gray-500 hidden">
                                    ℹ️ DNI no registrado - Complete los datos manualmente
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre:</label>
                            <input type="text" name="nombre_jugador" id="nombre_jugador" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Nombre del jugador">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido:</label>
                            <input type="text" name="apellido_jugador" id="apellido_jugador" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Apellido del jugador">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de Camiseta: <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input type="number" name="numero_camiseta" id="numero_camiseta" min="0" max="999"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Ej: 10">
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit"
                                class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md font-medium transition">
                                Agregar a Lista de Buena Fe
                            </button>
                        </div>
                    </form>
                </details>
            </div>
        @else
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-center">
                <p class="text-red-700 font-semibold">
                    ⛔ Este equipo ha alcanzado el límite de {{ $limiteJugadoresPorPlanilla }} jugadores.
                </p>
            </div>
        @endif

        {{-- Tabla de Jugadores Activos --}}
        <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-md">
            <div class="bg-green-600 text-white px-6 py-3">
                <h3 class="text-xl font-semibold">✅ Jugadores Activos</h3>
            </div>

            @if ($jugadoresActivos->isEmpty())
                <div class="p-6 text-center text-gray-500">
                    No hay jugadores activos en la Lista de Buena Fe.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[880px] divide-y divide-gray-200 sm:min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre Completo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Nac.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camiseta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Alta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suspendido</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($jugadoresActivos as $jugador)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        {{ $jugador->dni_jugador }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $jugador->jugador?->nombre ?? 'Sin nombre' }} {{ $jugador->jugador?->apellido ?? '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ !is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $jugador->fecha_alta->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if ($jugador->estaSuspendido())
                                            <span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">
                                                🟥 SÍ
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">
                                                ✅ NO
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center text-sm font-medium">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button"
                                                class="js-editar-jugador inline-flex h-8 shrink-0 items-center justify-center whitespace-nowrap rounded bg-amber-500 px-3 text-xs leading-none text-white hover:bg-amber-600"
                                                data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                                data-estado="activo"
                                                data-dni="{{ $jugador->dni_jugador }}"
                                                data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                                data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                                data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                                data-camiseta="{{ !is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}">
                                                ✏️ Editar
                                            </button>

                                            <form method="POST" action="{{ route('lista-buena-fe.dar-baja', $jugador->id) }}"
                                                class="contents">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="motivo_baja" placeholder="Motivo (opcional)"
                                                    class="h-8 w-36 rounded border-gray-300 px-2 text-xs leading-none">
                                                <button type="submit"
                                                    class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded bg-red-500 px-3 text-xs leading-none text-white hover:bg-red-600"
                                                    onclick="return confirm('¿Dar de baja a este jugador?')">
                                                    🗑️ Baja
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Tabla de Jugadores Inactivos (Historial) --}}
        @if ($jugadoresInactivos->isNotEmpty())
            <div class="overflow-hidden rounded-lg bg-white shadow-md">
                <details>
                    <summary
                        class="flex cursor-pointer flex-col gap-2 bg-gray-500 px-4 py-3 text-white transition hover:bg-gray-600 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <span class="text-base font-semibold sm:text-xl">📜 Historial - Jugadores Dados de Baja</span>
                        <span class="bg-gray-700 px-2 py-1 rounded text-sm">{{ $jugadoresInactivos->count() }}</span>
                    </summary>

                    <div class="overflow-x-auto">
                        <table class="min-w-[900px] divide-y divide-gray-200 sm:min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camiseta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Alta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Baja</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($jugadoresInactivos as $jugador)
                                    <tr class="hover:bg-gray-50 opacity-75">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $jugador->dni_jugador }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $jugador->jugador?->nombre ?? 'Sin nombre' }} {{ $jugador->jugador?->apellido ?? '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ !is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $jugador->fecha_alta->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $jugador->fecha_baja ? $jugador->fecha_baja->format('d/m/Y') : '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $jugador->motivo_baja ?? 'Sin especificar' }}
                                        </td>
                                        <td class="px-6 py-4 align-middle whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button type="button"
                                                    class="js-editar-jugador inline-flex h-8 shrink-0 items-center justify-center whitespace-nowrap rounded bg-amber-500 px-3 text-xs leading-none text-white hover:bg-amber-600"
                                                    data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                                    data-estado="inactivo"
                                                    data-dni="{{ $jugador->dni_jugador }}"
                                                    data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                                    data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                                    data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                                    data-camiseta="{{ !is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}">
                                                    ✏️ Editar
                                                </button>

                                                @if ($jugadoresActivos->count() < $limiteJugadoresPorPlanilla)
                                                    <form method="POST" action="{{ route('lista-buena-fe.reactivar', $jugador->id) }}"
                                                        class="contents">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit"
                                                            class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded bg-green-500 px-3 text-xs leading-none text-white hover:bg-green-600"
                                                            onclick="return confirm('¿Reactivar a este jugador?')">
                                                            ♻️ Reactivar
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-red-500 font-semibold">Límite alcanzado</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        @endif

        <div id="modal-editar-jugador"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 id="modal-editar-titulo" class="text-lg font-semibold text-slate-900">Editar jugador</h3>
                        <p id="modal-editar-subtitulo" class="text-sm text-slate-500">
                            Corrige identidad y datos operativos sin salir de la Lista de Buena Fe.
                        </p>
                    </div>

                    <button type="button" id="cerrar-modal-editar"
                        class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Cerrar modal de edición">
                        ✕
                    </button>
                </div>

                <form method="POST" id="formEditarJugador" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="editar_dni_jugador" class="mb-1 block text-sm font-medium text-gray-700">DNI del Jugador:</label>
                        <input type="text" name="dni_jugador" id="editar_dni_jugador" required pattern="\d{7,8}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Ej: 12345678">
                    </div>

                    <div>
                        <label for="editar_nombre_jugador" class="mb-1 block text-sm font-medium text-gray-700">Nombre:</label>
                        <input type="text" name="nombre_jugador" id="editar_nombre_jugador" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Nombre del jugador">
                    </div>

                    <div>
                        <label for="editar_apellido_jugador" class="mb-1 block text-sm font-medium text-gray-700">Apellido:</label>
                        <input type="text" name="apellido_jugador" id="editar_apellido_jugador" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Apellido del jugador">
                    </div>

                    <div>
                        <label for="editar_fecha_nacimiento" class="mb-1 block text-sm font-medium text-gray-700">Fecha de Nacimiento:</label>
                        <input type="date" name="fecha_nacimiento" id="editar_fecha_nacimiento" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="editar_numero_camiseta" class="mb-1 block text-sm font-medium text-gray-700">
                            Número de Camiseta: <span class="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <input type="number" name="numero_camiseta" id="editar_numero_camiseta" min="0" max="999"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Ej: 10">
                    </div>

                    <div class="md:col-span-2 flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" id="cancelar-edicion"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

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

        /**
         * Gestionar advertencias conflictivas con modal de confirmación.
         *
         * Si el usuario confirma, se reenvía la operación con `force=1`
         * para registrar el caso como conflictivo.
         */
        document.addEventListener('DOMContentLoaded', function() {
            const warningMessage = @json(session('warning'));
            const warningData = @json(session('warning_data', []));

            if (warningMessage) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Posible caso conflictivo',
                    text: warningMessage,
                    showCancelButton: true,
                    confirmButtonText: 'Continuar y marcar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d97706',
                    cancelButtonColor: '#6b7280',
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = @json(route('lista-buena-fe.agregar'));

                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = @json(csrf_token());
                    form.appendChild(csrf);

                    const forceInput = document.createElement('input');
                    forceInput.type = 'hidden';
                    forceInput.name = 'force';
                    forceInput.value = '1';
                    form.appendChild(forceInput);

                    Object.entries(warningData).forEach(function([key, value]) {
                        if (value === null || typeof value === 'undefined') {
                            return;
                        }

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = String(value);
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                });
            }

            const warningReactivarMessage = @json(session('warning_reactivar'));
            const warningReactivarAction = @json(session('warning_reactivar_id') ? route('lista-buena-fe.reactivar', session('warning_reactivar_id')) : null);

            if (warningReactivarMessage && warningReactivarAction) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Posible caso conflictivo',
                    text: warningReactivarMessage,
                    showCancelButton: true,
                    confirmButtonText: 'Reactivar y marcar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d97706',
                    cancelButtonColor: '#6b7280',
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = warningReactivarAction;

                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = @json(csrf_token());
                    form.appendChild(csrf);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PUT';
                    form.appendChild(methodInput);

                    const forceInput = document.createElement('input');
                    forceInput.type = 'hidden';
                    forceInput.name = 'force';
                    forceInput.value = '1';
                    form.appendChild(forceInput);

                    document.body.appendChild(form);
                    form.submit();
                });
            }

            const warningActualizarMessage = @json(session('warning_actualizar'));
            const warningActualizarData = @json(session('warning_actualizar_data', []));
            const warningActualizarAction = @json(session('warning_actualizar_action'));

            if (warningActualizarMessage && warningActualizarAction) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Posible caso conflictivo',
                    text: warningActualizarMessage,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar y marcar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d97706',
                    cancelButtonColor: '#6b7280',
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = warningActualizarAction;

                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = @json(csrf_token());
                    form.appendChild(csrf);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PUT';
                    form.appendChild(methodInput);

                    const forceInput = document.createElement('input');
                    forceInput.type = 'hidden';
                    forceInput.name = 'force';
                    forceInput.value = '1';
                    form.appendChild(forceInput);

                    Object.entries(warningActualizarData).forEach(function([key, value]) {
                        if (value === null || typeof value === 'undefined') {
                            return;
                        }

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = String(value);
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('modal-editar-jugador');
            const form = document.getElementById('formEditarJugador');
            const titulo = document.getElementById('modal-editar-titulo');
            const subtitulo = document.getElementById('modal-editar-subtitulo');
            const cerrar = document.getElementById('cerrar-modal-editar');
            const cancelar = document.getElementById('cancelar-edicion');
            const botonesEditar = document.querySelectorAll('.js-editar-jugador');

            if (!modal || !form || botonesEditar.length === 0) {
                return;
            }

            const dniInput = document.getElementById('editar_dni_jugador');
            const nombreInput = document.getElementById('editar_nombre_jugador');
            const apellidoInput = document.getElementById('editar_apellido_jugador');
            const fechaInput = document.getElementById('editar_fecha_nacimiento');
            const camisetaInput = document.getElementById('editar_numero_camiseta');

            function abrirModal(button) {
                form.action = button.dataset.action || '';
                dniInput.value = button.dataset.dni || '';
                nombreInput.value = button.dataset.nombre || '';
                apellidoInput.value = button.dataset.apellido || '';
                fechaInput.value = button.dataset.fecha || '';
                camisetaInput.value = button.dataset.camiseta || '';

                const estado = button.dataset.estado === 'inactivo' ? 'inactivo' : 'activo';
                titulo.textContent = `Editar jugador ${estado}`;
                subtitulo.textContent = estado === 'inactivo'
                    ? 'Actualiza datos históricos antes de una posible reactivación.'
                    : 'Corrige identidad y datos operativos del jugador activo.';

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }

            function cerrarModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            botonesEditar.forEach(function(button) {
                button.addEventListener('click', function() {
                    abrirModal(button);
                });
            });

            [cerrar, cancelar].filter(Boolean).forEach(function(button) {
                button.addEventListener('click', cerrarModal);
            });

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    cerrarModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    cerrarModal();
                }
            });
        });

        /**
         * Autocompletado de datos de jugador por DNI
         * 
         * Cuando el usuario termina de escribir un DNI (7-8 dígitos),
         * se busca automáticamente en la base de datos y se completan
         * los campos de nombre, apellido y fecha de nacimiento.
         */
        document.addEventListener('DOMContentLoaded', function() {
            const dniInput = document.getElementById('dni_jugador');
            const nombreInput = document.getElementById('nombre_jugador');
            const apellidoInput = document.getElementById('apellido_jugador');
            const fechaInput = document.getElementById('fecha_nacimiento');
            const camisetaInput = document.getElementById('numero_camiseta');
            
            // Elementos de estado
            const statusContainer = document.getElementById('dni_status');
            const buscandoSpan = document.getElementById('dni_buscando');
            const encontradoSpan = document.getElementById('dni_encontrado');
            const mensajeEncontrado = document.getElementById('dni_mensaje_encontrado');
            const noEncontradoSpan = document.getElementById('dni_no_encontrado');
            
            let timeoutId = null;
            let abortController = null;
            
            // Si no existen los elementos, no hacer nada (ej: formulario no visible)
            if (!dniInput || !nombreInput || !apellidoInput || !fechaInput) {
                return;
            }
            
            /**
             * Función para ocultar todos los estados
             */
            function ocultarEstados() {
                statusContainer.classList.add('hidden');
                buscandoSpan.classList.add('hidden');
                encontradoSpan.classList.add('hidden');
                noEncontradoSpan.classList.add('hidden');
            }
            
            /**
             * Función para mostrar estado "buscando"
             */
            function mostrarBuscando() {
                statusContainer.classList.remove('hidden');
                buscandoSpan.classList.remove('hidden');
                encontradoSpan.classList.add('hidden');
                noEncontradoSpan.classList.add('hidden');
            }
            
            /**
             * Función para mostrar estado "encontrado"
             */
            function mostrarEncontrado(mensaje) {
                statusContainer.classList.remove('hidden');
                buscandoSpan.classList.add('hidden');
                encontradoSpan.classList.remove('hidden');
                mensajeEncontrado.textContent = mensaje;
                noEncontradoSpan.classList.add('hidden');
            }
            
            /**
             * Función para mostrar estado "no encontrado"
             */
            function mostrarNoEncontrado() {
                statusContainer.classList.remove('hidden');
                buscandoSpan.classList.add('hidden');
                encontradoSpan.classList.add('hidden');
                noEncontradoSpan.classList.remove('hidden');
            }
            
            /**
             * Función para aplicar estilo de campo autocompletado
             */
            function aplicarEstiloAutocompletado(input, valor) {
                if (valor !== null && typeof valor !== 'undefined' && valor !== '') {
                    input.value = valor;
                    input.classList.add('bg-green-50', 'border-green-300');
                    input.classList.remove('bg-white');
                }
            }
            
            /**
             * Función para remover estilo de campo autocompletado
             */
            function removerEstiloAutocompletado(input) {
                input.classList.remove('bg-green-50', 'border-green-300');
                input.classList.add('bg-white');
            }
            
            /**
             * Función para limpiar campos
             */
            function limpiarCampos() {
                nombreInput.value = '';
                apellidoInput.value = '';
                fechaInput.value = '';
                if (camisetaInput) camisetaInput.value = '';
                removerEstiloAutocompletado(nombreInput);
                removerEstiloAutocompletado(apellidoInput);
                removerEstiloAutocompletado(fechaInput);
                if (camisetaInput) removerEstiloAutocompletado(camisetaInput);
            }
            
            /**
             * Función principal de búsqueda por DNI
             */
            async function buscarPorDni(dni) {
                // Cancelar petición anterior si existe
                if (abortController) {
                    abortController.abort();
                }
                
                abortController = new AbortController();
                
                mostrarBuscando();
                
                try {
                    const response = await fetch(`{{ route('lista-buena-fe.buscar-dni') }}?dni=${dni}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: abortController.signal
                    });
                    
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    
                    const data = await response.json();
                    
                    if (data.encontrado) {
                        // Autocompletar campos
                        aplicarEstiloAutocompletado(nombreInput, data.nombre);
                        aplicarEstiloAutocompletado(apellidoInput, data.apellido);
                        if (data.fecha_nacimiento) {
                            aplicarEstiloAutocompletado(fechaInput, data.fecha_nacimiento);
                        }
                        if (data.numero_camiseta !== null && typeof data.numero_camiseta !== 'undefined' && camisetaInput) {
                            aplicarEstiloAutocompletado(camisetaInput, data.numero_camiseta);
                        }
                        mostrarEncontrado(data.mensaje || 'Jugador encontrado');
                    } else {
                        limpiarCampos();
                        mostrarNoEncontrado();
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        // Petición cancelada, ignorar
                        return;
                    }
                    console.error('Error al buscar jugador:', error);
                    ocultarEstados();
                }
            }
            
            /**
             * Event listener con debounce para el campo DNI
             */
            dniInput.addEventListener('input', function(e) {
                const dni = e.target.value.trim();
                
                // Limpiar timeout anterior
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                
                // Si el DNI no tiene el formato correcto, ocultar estados y limpiar
                if (!/^\d{7,8}$/.test(dni)) {
                    ocultarEstados();
                    // Si está vacío o incompleto, limpiar los estilos pero no los valores
                    if (dni.length === 0) {
                        limpiarCampos();
                    }
                    return;
                }
                
                // Debounce de 500ms
                timeoutId = setTimeout(() => {
                    buscarPorDni(dni);
                }, 500);
            });
            
            /**
             * Remover estilos de autocompletado cuando el usuario edita manualmente
             */
            [nombreInput, apellidoInput, fechaInput, camisetaInput].filter(Boolean).forEach(input => {
                input.addEventListener('input', function() {
                    removerEstiloAutocompletado(this);
                });
            });
        });
    </script>
@endsection
