@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
        <x-ui.page-header title="Lista de buena fe" :subtitle="$equipoSeleccionado->equipo->nombre.' · '.$equipoSeleccionado->categoria->nombreCategoria">
            <x-slot name="actions">
                <x-ui.button variant="success"
                    onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}')">
                    Imprimir Planilla
                </x-ui.button>
                <x-ui.button href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id]) }}" variant="secondary">
                    Volver a equipos
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        {{-- Mensajes de éxito/error --}}
        @if (session('success'))
            <div id="success-message" class="mt-6 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-green-800">
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
            <div id="error-message" class="mt-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
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
            <div id="validation-message" role="alert" class="mt-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
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
        <div class="mt-6 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="mb-1 text-xl font-bold text-gray-800 sm:text-2xl">
                    {{ $equipoSeleccionado->equipo->nombre }}
                </h2>
                <p class="mb-2 text-base font-medium text-brand-navy sm:text-lg">
                    {{ $equipoSeleccionado->categoria->nombreCategoria }}
                </p>
                <p class="text-sm text-gray-500 sm:text-base">
                    Edición: <span class="font-semibold">{{ $EdicionSeleccionada->nombre }}</span> |
                    Jugadores Activos: <span
                        class="font-semibold {{ $jugadoresActivos->count() >= $limiteJugadoresPorPlanilla ? 'text-red-600' : 'text-green-600' }}">{{ $jugadoresActivos->count() }}/{{ $limiteJugadoresPorPlanilla }}</span>
                </p>
            </div>

            <x-ui.button href="{{ route('lista-buena-fe.pdf', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                variant="danger">
                Descargar PDF
            </x-ui.button>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
            <form method="GET" action="{{ route('lista-buena-fe.show', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">
                <x-ui.search label="Buscar por DNI" name="buscar_dni" id="buscar_dni" :value="$buscarDni ?? ''"
                    inputmode="numeric" pattern="\d*" maxlength="8" placeholder="Ej: 12345678" />

                <x-ui.button type="submit" variant="primary">
                    Buscar
                </x-ui.button>

                <x-ui.button href="{{ route('lista-buena-fe.show', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                    variant="secondary">
                    Limpiar
                </x-ui.button>
            </form>
        </div>

        {{-- Botón de Migración --}}
        @if ($jugadoresActivos->isEmpty())
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <h3 class="mb-2 font-semibold text-amber-800">No hay jugadores en la Lista de Buena Fe</h3>
                <p class="mb-3 text-sm text-amber-700">
                    Puedes migrar automáticamente los jugadores que ya han jugado partidos para este equipo.
                </p>
                <form method="POST" action="{{ route('lista-buena-fe.migrar') }}" class="inline">
                    @csrf
                    <input type="hidden" name="idEquipo" value="{{ $equipoSeleccionado->idEquipo }}">
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <x-ui.button type="submit" variant="secondary"
                        onclick="return confirm('¿Deseas migrar todos los jugadores de partidos anteriores a la Lista de Buena Fe?')">
                        Migrar Jugadores Existentes
                    </x-ui.button>
                </form>
            </div>
        @endif

        {{-- Formulario para Agregar Jugador --}}
        @if ($jugadoresActivos->count() < $limiteJugadoresPorPlanilla)
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                <details class="mb-4">
                    <summary
                        class="cursor-pointer rounded-md bg-gray-100 p-3 text-base font-medium text-gray-700 transition hover:bg-gray-200 sm:text-lg">
                        Agregar jugador a la Lista de Buena Fe
                    </summary>

                    <form method="POST" action="{{ route('lista-buena-fe.agregar') }}" id="formAgregarJugador"
                        class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="idEquipo" value="{{ $equipoSeleccionado->idEquipo }}">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <input type="hidden" name="idCategoria" value="{{ $equipoSeleccionado->idCategoria }}">

                        <div>
                            <label for="dni_jugador" class="mb-1 block text-sm font-medium text-gray-700">DNI del Jugador:</label>
                            <input type="text" name="dni_jugador" id="dni_jugador" required pattern="\d{7,8}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                                placeholder="Ej: 12345678" autocomplete="off">
                            {{-- Indicador de estado de búsqueda --}}
                            <div id="dni_status" class="mt-1 hidden text-sm">
                                <span id="dni_buscando" class="hidden text-brand-navy">
                                    Buscando...
                                </span>
                                <span id="dni_encontrado" class="hidden text-green-600">
                                    <span id="dni_mensaje_encontrado"></span>
                                </span>
                                <span id="dni_no_encontrado" class="hidden text-gray-500">
                                    DNI no registrado - Complete los datos manualmente
                                </span>
                            </div>
                        </div>

                        <div>
                            <label for="nombre_jugador" class="mb-1 block text-sm font-medium text-gray-700">Nombre:</label>
                            <input type="text" name="nombre_jugador" id="nombre_jugador" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                                placeholder="Nombre del jugador">
                        </div>

                        <div>
                            <label for="apellido_jugador" class="mb-1 block text-sm font-medium text-gray-700">Apellido:</label>
                            <input type="text" name="apellido_jugador" id="apellido_jugador" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                                placeholder="Apellido del jugador">
                        </div>

                        <div>
                            <label for="fecha_nacimiento" class="mb-1 block text-sm font-medium text-gray-700">Fecha de Nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30">
                        </div>

                        <div>
                            <label for="numero_camiseta" class="mb-1 block text-sm font-medium text-gray-700">
                                Número de Camiseta: <span class="font-normal text-gray-400">(opcional)</span>
                            </label>
                            <input type="number" name="numero_camiseta" id="numero_camiseta" min="0" max="999"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                                placeholder="Ej: 10">
                        </div>

                        <div class="md:col-span-2">
                            <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                                Agregar a Lista de Buena Fe
                            </x-ui.button>
                        </div>
                    </form>
                </details>
            </div>
        @else
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-center">
                <p class="font-semibold text-red-700">
                    Este equipo ha alcanzado el límite de {{ $limiteJugadoresPorPlanilla }} jugadores.
                </p>
            </div>
        @endif

        {{-- Tabla de Jugadores Activos --}}
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-3">
                <h3 class="text-lg font-semibold text-gray-800">Jugadores Activos</h3>
            </div>

            @if ($jugadoresActivos->isEmpty())
                <div class="p-6 text-center text-gray-500">
                    {{ filled($buscarDni ?? '') ? 'No hay jugadores activos que coincidan con el DNI buscado.' : 'No hay jugadores activos en la Lista de Buena Fe.' }}
                </div>
            @else
                <x-ui.data-table :empty="false">
                    <x-slot name="head">
                        <tr>
                            <th scope="col">DNI</th>
                            <th scope="col">Nombre Completo</th>
                            <th scope="col">Fecha Nac.</th>
                            <th scope="col">Camiseta</th>
                            <th scope="col">Fecha Alta</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-center">Acciones</th>
                        </tr>
                    </x-slot>
                    <x-slot name="body">
                        @foreach ($jugadoresActivos as $jugador)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $jugador->dni_jugador }}</td>
                                <td>{{ $jugador->jugador?->nombre ?? 'Sin nombre' }} {{ $jugador->jugador?->apellido ?? '' }}</td>
                                <td>{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}</td>
                                <td>{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}</td>
                                <td>{{ $jugador->fecha_alta->format('d/m/Y') }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @if ($jugador->estaSuspendido())
                                            <x-ui.badge variant="danger">Suspendido</x-ui.badge>
                                        @endif
                                        @if ($jugador->conflictivo && ! $jugador->resuelto)
                                            <x-ui.badge variant="warning">En revisión</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <x-ui.button size="sm" variant="success" type="button"
                                            class="js-editar-jugador"
                                            data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                            data-estado="activo"
                                            data-dni="{{ $jugador->dni_jugador }}"
                                            data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                            data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                            data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                            data-camiseta="{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}">
                                            Editar
                                        </x-ui.button>

                                        <form method="POST" action="{{ route('lista-buena-fe.dar-baja', $jugador->id) }}" class="contents">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="motivo_baja" placeholder="Motivo (opcional)"
                                                class="h-8 w-32 rounded border-gray-300 px-2 text-xs leading-none">
                                            <x-ui.button size="sm" variant="danger" type="submit"
                                                onclick="return confirm('¿Dar de baja a este jugador?')">
                                                Baja
                                            </x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot>
                </x-ui.data-table>
            @endif
        </div>

        {{-- Tabla de Jugadores Inactivos (Historial) --}}
        @if ($jugadoresInactivos->isNotEmpty())
            <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
                <details>
                    <summary
                        class="flex cursor-pointer flex-col gap-2 bg-gray-100 px-4 py-3 text-gray-700 transition hover:bg-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <span class="text-base font-semibold sm:text-lg">Historial - Jugadores Dados de Baja</span>
                        <span class="rounded bg-gray-200 px-2 py-1 text-sm text-gray-700">{{ $jugadoresInactivos->count() }}</span>
                    </summary>

                    <x-ui.data-table :empty="false">
                        <x-slot name="head">
                            <tr>
                                <th scope="col">DNI</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Camiseta</th>
                                <th scope="col">Fecha Alta</th>
                                <th scope="col">Fecha Baja</th>
                                <th scope="col">Motivo</th>
                                <th scope="col" class="text-center">Acción</th>
                            </tr>
                        </x-slot>
                        <x-slot name="body">
                            @foreach ($jugadoresInactivos as $jugador)
                                <tr class="opacity-75">
                                    <td>{{ $jugador->dni_jugador }}</td>
                                    <td>{{ $jugador->jugador?->nombre ?? 'Sin nombre' }} {{ $jugador->jugador?->apellido ?? '' }}</td>
                                    <td>{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}</td>
                                    <td>{{ $jugador->fecha_alta->format('d/m/Y') }}</td>
                                    <td>{{ $jugador->fecha_baja ? $jugador->fecha_baja->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $jugador->motivo_baja ?? 'Sin especificar' }}</td>
                                    <td class="text-center">
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            <x-ui.button size="sm" variant="success" type="button"
                                                class="js-editar-jugador"
                                                data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                                data-estado="inactivo"
                                                data-dni="{{ $jugador->dni_jugador }}"
                                                data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                                data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                                data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                                data-camiseta="{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}">
                                                Editar
                                            </x-ui.button>

                                            @if ($jugadoresActivos->count() < $limiteJugadoresPorPlanilla)
                                                <form method="POST" action="{{ route('lista-buena-fe.reactivar', $jugador->id) }}" class="contents">
                                                    @csrf
                                                    @method('PUT')
                                                    <x-ui.button size="sm" variant="primary" type="submit"
                                                        onclick="return confirm('¿Reactivar a este jugador?')">
                                                        Reactivar
                                                    </x-ui.button>
                                                </form>
                                            @else
                                                <span class="text-xs font-semibold text-red-500">Límite alcanzado</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot>
                    </x-ui.data-table>
                </details>
            </div>
        @endif

        @if (filled($buscarDni ?? '') && $jugadoresActivos->isEmpty() && $jugadoresInactivos->isEmpty())
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
                No se encontraron jugadores activos ni inactivos para el DNI <span class="font-semibold">{{ $buscarDni }}</span>.
            </div>
        @endif

        <div id="modal-editar-jugador"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 id="modal-editar-titulo" class="text-lg font-semibold text-gray-900">Editar jugador</h3>
                        <p id="modal-editar-subtitulo" class="text-sm text-gray-500">
                            Corrige identidad y datos operativos sin salir de la Lista de Buena Fe.
                        </p>
                    </div>

                    <button type="button" id="cerrar-modal-editar"
                        class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
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
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Ej: 12345678">
                    </div>

                    <div>
                        <label for="editar_nombre_jugador" class="mb-1 block text-sm font-medium text-gray-700">Nombre:</label>
                        <input type="text" name="nombre_jugador" id="editar_nombre_jugador" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Nombre del jugador">
                    </div>

                    <div>
                        <label for="editar_apellido_jugador" class="mb-1 block text-sm font-medium text-gray-700">Apellido:</label>
                        <input type="text" name="apellido_jugador" id="editar_apellido_jugador" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Apellido del jugador">
                    </div>

                    <div>
                        <label for="editar_fecha_nacimiento" class="mb-1 block text-sm font-medium text-gray-700">Fecha de Nacimiento:</label>
                        <input type="date" name="fecha_nacimiento" id="editar_fecha_nacimiento" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30">
                    </div>

                    <div class="md:col-span-2">
                        <label for="editar_numero_camiseta" class="mb-1 block text-sm font-medium text-gray-700">
                            Número de Camiseta: <span class="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <input type="number" name="numero_camiseta" id="editar_numero_camiseta" min="0" max="999"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Ej: 10">
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end md:col-span-2">
                        <button type="button" id="cancelar-edicion"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            Cancelar
                        </button>
                        <x-ui.button type="submit" variant="primary">
                            Guardar cambios
                        </x-ui.button>
                    </div>
                </form>
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
