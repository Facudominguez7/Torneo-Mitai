@extends('Panel.admin')

@section('detalle')
    @php
        $rutaDetalle = route('lista-buena-fe.show', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]);
        $cupoCompleto = $cantidadActivos >= $limiteJugadoresPorPlanilla;
        $excedeCupo = $cantidadActivos > $limiteJugadoresPorPlanilla;
        $lugaresLibres = max($limiteJugadoresPorPlanilla - $cantidadActivos, 0);
        $busquedaServidor = filled($buscarDni ?? '');

        // Shared mobile-card label: each cell shows its column name above the value below md.
        $etiquetaMovil = 'max-md:before:mb-0.5 max-md:before:block max-md:before:text-[11px] max-md:before:font-semibold max-md:before:uppercase max-md:before:tracking-wide max-md:before:text-gray-500 max-md:before:content-[attr(data-label)]';
    @endphp

    <div class="mx-auto w-full max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
        <a href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id]) }}"
            class="mb-3 inline-flex items-center gap-1 rounded text-sm font-semibold text-brand-teal transition hover:text-brand-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal/50">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
            </svg>
            Volver a equipos
        </a>

        <x-ui.page-header :title="$equipoSeleccionado->equipo->nombre"
            :subtitle="'Lista de buena fe · '.$equipoSeleccionado->categoria->nombreCategoria.' · '.$EdicionSeleccionada->nombre">
            <x-slot name="actions">
                <x-ui.button variant="primary"
                    onclick="printExternal('{{ route('lista-buena-fe.imprimir-planilla', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}')">
                    Imprimir Planilla
                </x-ui.button>
                <x-ui.button href="{{ route('lista-buena-fe.pdf', ['idEquipo' => $equipoSeleccionado->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}"
                    variant="secondary">
                    Descargar PDF
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1" data-contador-jugadores>
            <x-ui.badge :variant="$excedeCupo ? 'danger' : ($cupoCompleto ? 'success' : 'neutral')">
                {{ $cantidadActivos }}/{{ $limiteJugadoresPorPlanilla }} jugadores activos{{ $cupoCompleto && ! $excedeCupo ? ' · Lista completa' : '' }}
            </x-ui.badge>
            @if ($excedeCupo)
                <span class="text-sm text-gray-500">Excede el tope por {{ $cantidadActivos - $limiteJugadoresPorPlanilla }}.</span>
            @elseif (! $cupoCompleto)
                <span class="text-sm text-gray-500">{{ $lugaresLibres === 1 ? 'Queda 1 lugar' : "Quedan {$lugaresLibres} lugares" }}</span>
            @endif
        </div>

        {{-- Mensajes de éxito/error --}}
        @if (session('success'))
            <div id="success-message" role="status" class="mt-6 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-green-800">
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
            <div id="error-message" role="alert" class="mt-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
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

        {{-- Botón de Migración --}}
        @if ($cantidadActivos === 0)
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <h2 class="mb-2 font-semibold text-amber-800">No hay jugadores en la Lista de Buena Fe</h2>
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
        @if (! $cupoCompleto)
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                <details>
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
            <p class="mt-6 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <span class="font-semibold text-gray-800">La lista está completa.</span>
                Para sumar a alguien, primero da de baja a un jugador.
            </p>
        @endif

        {{-- Jugadores activos --}}
        <section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white" aria-labelledby="titulo-jugadores-activos">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="titulo-jugadores-activos" class="text-lg font-semibold text-gray-800">Jugadores activos</h2>
                    <p class="text-sm text-gray-500" data-filtro-resumen aria-live="polite">
                        @if ($busquedaServidor)
                            Resultados para el DNI {{ $buscarDni }} ·
                            <a href="{{ $rutaDetalle }}" class="font-semibold text-brand-teal underline-offset-2 hover:underline">Ver todos</a>
                        @endif
                    </p>
                </div>

                @if ($cantidadActivos > 0 || $jugadoresInactivos->isNotEmpty())
                    <form method="GET" action="{{ $rutaDetalle }}" role="search" class="w-full sm:w-72"
                        data-filtro-jugadores data-busqueda-servidor="{{ $busquedaServidor ? '1' : '0' }}">
                        <x-ui.search label="Buscar jugador por DNI o nombre" :label-hidden="true" name="buscar_dni" id="buscar_dni"
                            :value="$buscarDni ?? ''" placeholder="Buscar por DNI o nombre" autocomplete="off" />
                    </form>
                @endif
            </div>

            @if ($jugadoresActivos->isEmpty())
                <div class="p-6 text-center text-gray-500">
                    {{ $busquedaServidor ? 'No hay jugadores activos que coincidan con el DNI buscado.' : 'No hay jugadores activos en la Lista de Buena Fe.' }}
                </div>
            @else
                <table class="block w-full text-left text-sm text-gray-700 md:table">
                    <thead class="hidden bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-600 md:table-header-group [&>tr>th]:px-5 [&>tr>th]:py-3">
                        <tr>
                            <th scope="col">Jugador</th>
                            <th scope="col">Camiseta</th>
                            <th scope="col">Nacimiento</th>
                            <th scope="col">Alta</th>
                            <th scope="col" class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="block divide-y divide-gray-100 md:table-row-group">
                        @foreach ($jugadoresActivos as $jugador)
                            @php
                                $nombreCompleto = trim(($jugador->jugador?->nombre ?? 'Sin nombre').' '.($jugador->jugador?->apellido ?? ''));
                            @endphp
                            <tr data-filtro-fila="activo"
                                data-buscar="{{ Str::lower(Str::ascii($jugador->dni_jugador.' '.$nombreCompleto)) }}"
                                class="grid grid-cols-3 gap-x-3 gap-y-3 px-4 py-4 md:table-row md:p-0 md:[&>td]:px-5 md:[&>td]:py-3 md:[&>td]:align-middle">
                                <td data-celda="jugador" class="col-span-3">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-semibold text-gray-900">{{ $nombreCompleto }}</span>
                                        @if ($jugador->estaSuspendido())
                                            <x-ui.badge variant="danger">Suspendido</x-ui.badge>
                                        @endif
                                        @if ($jugador->conflictivo && ! $jugador->resuelto)
                                            <x-ui.badge variant="warning">En revisión</x-ui.badge>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs tabular-nums text-gray-500">DNI {{ $jugador->dni_jugador }}</div>
                                </td>
                                <td data-label="Camiseta" class="tabular-nums {{ $etiquetaMovil }}">
                                    {{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}
                                </td>
                                <td data-label="Nacimiento" class="tabular-nums {{ $etiquetaMovil }}">
                                    {{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}
                                </td>
                                <td data-label="Alta" class="tabular-nums {{ $etiquetaMovil }}">
                                    {{ $jugador->fecha_alta->format('d/m/Y') }}
                                </td>
                                <td class="col-span-3 md:text-right">
                                    <div class="flex gap-2 md:justify-end">
                                        <x-ui.button size="sm" variant="secondary" type="button"
                                            class="js-editar-jugador max-md:flex-1 max-md:py-2.5"
                                            data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                            data-estado="activo"
                                            data-dni="{{ $jugador->dni_jugador }}"
                                            data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                            data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                            data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                            data-camiseta="{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}"
                                            aria-label="Editar a {{ $nombreCompleto }}">
                                            Editar
                                        </x-ui.button>

                                        {{-- Outlined on purpose: the solid red is kept for the confirm button inside the dialog. --}}
                                        <button type="button"
                                            class="js-dar-baja inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 max-md:flex-1 max-md:py-2.5"
                                            data-action="{{ route('lista-buena-fe.dar-baja', $jugador->id) }}"
                                            data-nombre-completo="{{ $nombreCompleto }}"
                                            aria-label="Dar de baja a {{ $nombreCompleto }}">
                                            Dar de baja
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p id="filtro-sin-resultados" class="hidden px-6 py-6 text-center text-sm text-gray-500">
                    Ningún jugador activo coincide con la búsqueda.
                </p>
            @endif
        </section>

        {{-- Historial de bajas --}}
        @if ($jugadoresInactivos->isNotEmpty())
            <details id="historial-bajas" class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
                <summary
                    class="flex cursor-pointer items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-gray-700 transition hover:bg-gray-100 sm:px-6">
                    <span class="text-base font-semibold">Historial · Jugadores dados de baja</span>
                    <x-ui.badge data-contador-historial>{{ $jugadoresInactivos->count() }}</x-ui.badge>
                </summary>

                <table class="block w-full text-left text-sm text-gray-700 md:table">
                    <thead class="hidden bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-600 md:table-header-group [&>tr>th]:px-5 [&>tr>th]:py-3">
                        <tr>
                            <th scope="col">Jugador</th>
                            <th scope="col">Camiseta</th>
                            <th scope="col">Alta</th>
                            <th scope="col">Baja</th>
                            <th scope="col">Motivo</th>
                            <th scope="col" class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="block divide-y divide-gray-100 border-t border-gray-100 md:table-row-group">
                        @foreach ($jugadoresInactivos as $jugador)
                            @php
                                $nombreCompleto = trim(($jugador->jugador?->nombre ?? 'Sin nombre').' '.($jugador->jugador?->apellido ?? ''));
                            @endphp
                            <tr data-filtro-fila="inactivo"
                                data-buscar="{{ Str::lower(Str::ascii($jugador->dni_jugador.' '.$nombreCompleto)) }}"
                                class="grid grid-cols-3 gap-x-3 gap-y-3 px-4 py-4 text-gray-600 md:table-row md:p-0 md:[&>td]:px-5 md:[&>td]:py-3 md:[&>td]:align-middle">
                                <td class="col-span-3">
                                    <div class="font-semibold text-gray-800">{{ $nombreCompleto }}</div>
                                    <div class="mt-0.5 text-xs tabular-nums text-gray-500">DNI {{ $jugador->dni_jugador }}</div>
                                </td>
                                <td data-label="Camiseta" class="tabular-nums {{ $etiquetaMovil }}">
                                    {{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '-' }}
                                </td>
                                <td data-label="Alta" class="tabular-nums {{ $etiquetaMovil }}">{{ $jugador->fecha_alta->format('d/m/Y') }}</td>
                                <td data-label="Baja" class="tabular-nums {{ $etiquetaMovil }}">
                                    {{ $jugador->fecha_baja ? $jugador->fecha_baja->format('d/m/Y') : '-' }}
                                </td>
                                <td data-label="Motivo" class="col-span-3 {{ $etiquetaMovil }}">{{ $jugador->motivo_baja ?? 'Sin especificar' }}</td>
                                <td class="col-span-3 md:text-right">
                                    <div class="flex items-center gap-2 md:justify-end">
                                        <x-ui.button size="sm" variant="secondary" type="button"
                                            class="js-editar-jugador max-md:flex-1 max-md:py-2.5"
                                            data-action="{{ route('lista-buena-fe.actualizar', $jugador->id) }}"
                                            data-estado="inactivo"
                                            data-dni="{{ $jugador->dni_jugador }}"
                                            data-nombre="{{ $jugador->jugador?->nombre ?? '' }}"
                                            data-apellido="{{ $jugador->jugador?->apellido ?? '' }}"
                                            data-fecha="{{ $jugador->fecha_nacimiento ? $jugador->fecha_nacimiento->format('Y-m-d') : '' }}"
                                            data-camiseta="{{ ! is_null($jugador->numero_camiseta) ? $jugador->numero_camiseta : '' }}"
                                            aria-label="Editar a {{ $nombreCompleto }}">
                                            Editar
                                        </x-ui.button>

                                        @if (! $cupoCompleto)
                                            <form method="POST" action="{{ route('lista-buena-fe.reactivar', $jugador->id) }}" class="contents">
                                                @csrf
                                                @method('PUT')
                                                <x-ui.button size="sm" variant="primary" type="submit" class="max-md:flex-1 max-md:py-2.5"
                                                    onclick="return confirm('¿Reactivar a este jugador?')"
                                                    aria-label="Reactivar a {{ $nombreCompleto }}">
                                                    Reactivar
                                                </x-ui.button>
                                            </form>
                                        @else
                                            <span class="text-xs font-semibold text-gray-500 max-md:flex-1 max-md:text-center">Lista completa</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p id="historial-sin-resultados" class="hidden border-t border-gray-100 px-6 py-6 text-center text-sm text-gray-500">
                    Ninguna baja coincide con la búsqueda.
                </p>
            </details>
        @endif

        @if ($busquedaServidor && $jugadoresActivos->isEmpty() && $jugadoresInactivos->isEmpty())
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
                No se encontraron jugadores activos ni inactivos para el DNI <span class="font-semibold">{{ $buscarDni }}</span>.
            </div>
        @endif

        <div id="modal-editar-jugador" role="dialog" aria-modal="true" aria-labelledby="modal-editar-titulo"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h2 id="modal-editar-titulo" class="text-lg font-semibold text-gray-900">Editar jugador</h2>
                        <p id="modal-editar-subtitulo" class="text-sm text-gray-500">
                            Corrige identidad y datos operativos sin salir de la Lista de Buena Fe.
                        </p>
                    </div>

                    <button type="button" id="cerrar-modal-editar" data-cerrar-modal
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
                        <button type="button" id="cancelar-edicion" data-cerrar-modal
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

        <div id="modal-baja-jugador" role="dialog" aria-modal="true" aria-labelledby="modal-baja-titulo"
            aria-describedby="modal-baja-descripcion"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <form method="POST" id="formBajaJugador" class="flex flex-col gap-4 p-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <h2 id="modal-baja-titulo" class="text-lg font-semibold text-gray-900">
                            Dar de baja a <span data-baja-nombre></span>
                        </h2>
                        <p id="modal-baja-descripcion" class="mt-1 text-sm text-gray-500">
                            Pasa al historial y libera su lugar en la lista. Podés reactivarlo mientras haya cupo.
                        </p>
                    </div>

                    <div>
                        <label for="motivo_baja" class="mb-1 block text-sm font-medium text-gray-700">
                            Motivo <span class="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <input type="text" name="motivo_baja" id="motivo_baja" maxlength="255" autocomplete="off"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-navy focus:ring-brand-navy/30"
                            placeholder="Ej: pase a otro club">
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" data-cerrar-modal
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            Cancelar
                        </button>
                        <x-ui.button type="submit" variant="danger">
                            Dar de baja
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

        /**
         * Shared dialog behavior: opens over the page, closes with Escape, the
         * backdrop or any [data-cerrar-modal] button, and gives focus back to
         * the button that opened it.
         */
        function crearModal(modal) {
            let disparador = null;

            function cerrar() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                disparador?.focus();
                disparador = null;
            }

            function abrir(boton, campoInicial) {
                disparador = boton;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                campoInicial?.focus();
            }

            modal.querySelectorAll('[data-cerrar-modal]').forEach(function(boton) {
                boton.addEventListener('click', cerrar);
            });

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    cerrar();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    cerrar();
                }
            });

            return { abrir, cerrar };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('modal-editar-jugador');
            const form = document.getElementById('formEditarJugador');
            const titulo = document.getElementById('modal-editar-titulo');
            const subtitulo = document.getElementById('modal-editar-subtitulo');
            const botonesEditar = document.querySelectorAll('.js-editar-jugador');

            if (!modal || !form || botonesEditar.length === 0) {
                return;
            }

            const dialogo = crearModal(modal);
            const dniInput = document.getElementById('editar_dni_jugador');
            const nombreInput = document.getElementById('editar_nombre_jugador');
            const apellidoInput = document.getElementById('editar_apellido_jugador');
            const fechaInput = document.getElementById('editar_fecha_nacimiento');
            const camisetaInput = document.getElementById('editar_numero_camiseta');

            botonesEditar.forEach(function(button) {
                button.addEventListener('click', function() {
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

                    dialogo.abrir(button, nombreInput);
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('modal-baja-jugador');
            const form = document.getElementById('formBajaJugador');
            const botonesBaja = document.querySelectorAll('.js-dar-baja');

            if (!modal || !form || botonesBaja.length === 0) {
                return;
            }

            const dialogo = crearModal(modal);
            const nombre = modal.querySelector('[data-baja-nombre]');
            const motivo = document.getElementById('motivo_baja');
            const enviar = form.querySelector('[type="submit"]');

            botonesBaja.forEach(function(button) {
                button.addEventListener('click', function() {
                    form.action = button.dataset.action || '';
                    nombre.textContent = button.dataset.nombreCompleto || 'este jugador';
                    motivo.value = '';
                    dialogo.abrir(button, motivo);
                });
            });

            // One click is one baja: block a second submit while the page reloads.
            form.addEventListener('submit', function() {
                enviar.disabled = true;
            });
        });

        /**
         * Instant filter by DNI or name over the active and inactive rows. The
         * rows are already on the page, so typing never reloads it; a page that
         * arrived filtered by the server (?buscar_dni=) keeps the normal submit.
         */
        document.addEventListener('DOMContentLoaded', function() {
            const formulario = document.querySelector('[data-filtro-jugadores]');
            const campo = document.getElementById('buscar_dni');

            if (!formulario || !campo) {
                return;
            }

            const filasActivas = Array.from(document.querySelectorAll('[data-filtro-fila="activo"]'));
            const filasInactivas = Array.from(document.querySelectorAll('[data-filtro-fila="inactivo"]'));
            const sinActivos = document.getElementById('filtro-sin-resultados');
            const sinInactivos = document.getElementById('historial-sin-resultados');
            const resumen = document.querySelector('[data-filtro-resumen]');
            const historial = document.getElementById('historial-bajas');
            const contadorHistorial = document.querySelector('[data-contador-historial]');
            const busquedaServidor = formulario.dataset.busquedaServidor === '1';
            const resumenInicial = resumen?.innerHTML ?? '';

            const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

            // Inline display, not the hidden class: md:table-row would override it on desktop.
            function filtrar(filas, terminos) {
                let visibles = 0;

                filas.forEach(function(fila) {
                    const coincide = terminos.every((termino) => fila.dataset.buscar.includes(termino));
                    fila.style.display = coincide ? '' : 'none';
                    visibles += coincide ? 1 : 0;
                });

                return visibles;
            }

            function aplicar() {
                const terminos = normalizar(campo.value).split(/\s+/).filter(Boolean);
                const activos = filtrar(filasActivas, terminos);
                const inactivos = filtrar(filasInactivas, terminos);
                const filtrando = terminos.length > 0;

                sinActivos?.classList.toggle('hidden', !filtrando || activos > 0 || filasActivas.length === 0);
                sinInactivos?.classList.toggle('hidden', !filtrando || inactivos > 0);

                if (contadorHistorial) {
                    contadorHistorial.textContent = filtrando ? `${inactivos} de ${filasInactivas.length}` : String(filasInactivas.length);
                }

                if (historial && filtrando && inactivos > 0 && activos === 0) {
                    historial.open = true;
                }

                if (resumen && !busquedaServidor) {
                    resumen.textContent = filtrando ? `Mostrando ${activos} de ${filasActivas.length}` : '';
                } else if (resumen && !filtrando) {
                    resumen.innerHTML = resumenInicial;
                }
            }

            campo.addEventListener('input', aplicar);

            // Capture phase: cancel before the global loader (components/loader) sees the submit.
            document.addEventListener('submit', function(event) {
                if (event.target === formulario && !busquedaServidor) {
                    event.preventDefault();
                    aplicar();
                }
            }, true);

            if (campo.value !== '') {
                aplicar();
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
