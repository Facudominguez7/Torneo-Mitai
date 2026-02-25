@extends('Panel.admin')

@section('detalle')
    <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">📋 Lista de Buena Fe</h1>
            <div class="flex space-x-4">
                <button
                    onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}')"
                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded inline-flex items-center cursor-pointer">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                        </path>
                    </svg>
                    <span>Imprimir Planilla</span>
                </button>
                <a href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                    class="hover:text-blue-500 font-medium text-white flex items-center">
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

        {{-- Información del Equipo --}}
        <div class="bg-white shadow-md rounded-lg p-6 mb-6 border-l-4 border-blue-500 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-1">
                    {{ $equipoSeleccionado->equipo->nombre }}
                </h2>
                <p class="text-lg text-blue-600 font-medium mb-2">
                    {{ $equipoSeleccionado->categoria->nombreCategoria }}
                </p>
                <p class="text-sm text-gray-500">
                    Edición: <span class="font-semibold">{{ $EdicionSeleccionada->nombre }}</span> |
                    Jugadores Activos: <span
                        class="font-semibold {{ $jugadoresActivos->count() >= 18 ? 'text-red-600' : 'text-green-600' }}">{{ $jugadoresActivos->count() }}/18</span>
                </p>
            </div>

            {{-- Botón PDF --}}
            <div>
                <a href="{{ route('lista-buena-fe.pdf', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                    class="flex items-center bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md shadow transition">
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
        @if ($jugadoresActivos->count() < 18)
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <details class="mb-4">
                    <summary
                        class="cursor-pointer text-lg font-medium text-gray-700 bg-gray-200 p-3 rounded-md hover:bg-gray-300 transition">
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
                    ⛔ Este equipo ha alcanzado el límite de 18 jugadores.
                </p>
            </div>
        @endif

        {{-- Tabla de Jugadores Activos --}}
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="bg-green-600 text-white px-6 py-3">
                <h3 class="text-xl font-semibold">✅ Jugadores Activos</h3>
            </div>

            @if ($jugadoresActivos->isEmpty())
                <div class="p-6 text-center text-gray-500">
                    No hay jugadores activos en la Lista de Buena Fe.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre Completo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Nac.</th>
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
                                        {{ $jugador->jugador->nombre }} {{ $jugador->jugador->apellido }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}
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
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <form method="POST" action="{{ route('lista-buena-fe.dar-baja', $jugador->id) }}"
                                            class="inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="motivo_baja" placeholder="Motivo (opcional)"
                                                class="text-xs border-gray-300 rounded px-2 py-1 mb-1 w-32">
                                            <button type="submit"
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs"
                                                onclick="return confirm('¿Dar de baja a este jugador?')">
                                                🗑️ Baja
                                            </button>
                                        </form>
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
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <details>
                    <summary
                        class="bg-gray-500 text-white px-6 py-3 cursor-pointer hover:bg-gray-600 transition flex justify-between items-center">
                        <span class="text-xl font-semibold">📜 Historial - Jugadores Dados de Baja</span>
                        <span class="bg-gray-700 px-2 py-1 rounded text-sm">{{ $jugadoresInactivos->count() }}</span>
                    </summary>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
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
                                            {{ $jugador->jugador->nombre }} {{ $jugador->jugador->apellido }}
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
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            @if ($jugadoresActivos->count() < 18)
                                                <form method="POST" action="{{ route('lista-buena-fe.reactivar', $jugador->id) }}"
                                                    class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit"
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs"
                                                        onclick="return confirm('¿Reactivar a este jugador?')">
                                                        ♻️ Reactivar
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-red-500 font-semibold">Límite alcanzado</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        @endif
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
                if (valor) {
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
                        if (data.numero_camiseta && camisetaInput) {
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
