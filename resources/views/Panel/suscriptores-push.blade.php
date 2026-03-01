@extends('Panel.admin')

@section('contenido')
    @php
        $resumenIdEdicion = $resumenIdEdicion ?? ($EdicionSeleccionada->id ?? null);
        $resumenNombreEdicion = $resumenNombreEdicion ?? 'Edición vigente';
        $resumenIdCategoria = $resumenIdCategoria ?? null;
        $resumenIdFecha = $resumenIdFecha ?? null;
        $categoriasResumen = $categoriasResumen ?? collect();
        $fechasResumen = $fechasResumen ?? collect();
        $idEdicionNotificacion = $EdicionSeleccionada->id ?? $resumenIdEdicion;
        $urlNotificacionFixture = route('fixture', ['idEdicion' => $idEdicionNotificacion]);
        $urlNotificacionGoleadores = route('tabla-goleadores', ['idEdicion' => $idEdicionNotificacion]);
    @endphp

    <div class="rounded-lg bg-white p-3 shadow-lg sm:p-4 lg:p-6">
        <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
            <h1 class="flex items-center gap-3 text-xl font-bold text-gray-800 sm:text-2xl">
                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                </svg>
                Gestión de Usuarios y Notificaciones
            </h1>
            <div class="w-full max-w-[860px] xl:justify-self-end">
                <div id="recordatorios-container" class="w-full">
                    <div class="flex flex-wrap items-stretch gap-2 sm:gap-3 xl:justify-end">
                        <button type="button" id="btn-enviar-recordatorios" onclick="iniciarEnvioRecordatorios()" 
                            class="panel-action-button panel-action-button--warning">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span id="btn-text">Enviar Recordatorios</span>
                        </button>
                        
                        <!-- Botón Notificación Manual -->
                        <div class="relative min-h-[44px] w-full sm:w-auto" id="notif-dropdown-container">
                            <button type="button" onclick="toggleNotifDropdown()" id="btn-manual"
                                class="panel-action-button panel-action-button--primary">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                                </svg>
                                Notificar ▼
                            </button>
                            <!-- Dropdown Menu -->
                            <div id="notif-dropdown" class="absolute right-0 z-50 mt-2 hidden w-full min-w-[250px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl sm:w-auto">
                                <button type="button"
                                    data-notif-titulo="Fixture Actualizado"
                                    data-notif-mensaje="Se cargo el fixture. Consulta los proximos partidos."
                                    data-notif-url="{{ $urlNotificacionFixture }}"
                                    data-notif-icono="📅"
                                    onclick="prepararNotificacionDesdeBoton(this); cerrarNotifDropdown()"
                                    class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors border-b first:rounded-t-lg">
                                    <span class="mr-2">📅</span> Fixture Cargado
                                </button>
                                <button type="button"
                                    data-notif-titulo="Goleadores Actualizados"
                                    data-notif-mensaje="Se actualizo la tabla de goleadores. Mira quienes lideran."
                                    data-notif-url="{{ $urlNotificacionGoleadores }}"
                                    data-notif-icono="⚽"
                                    onclick="prepararNotificacionDesdeBoton(this); cerrarNotifDropdown()"
                                    class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-green-50 hover:text-green-700 transition-colors border-b">
                                    <span class="mr-2">⚽</span> Goleadores Actualizados
                                </button>
                                <button onclick="abrirModalNotificacion(); cerrarNotifDropdown()"
                                    class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors last:rounded-b-lg">
                                    <span class="mr-2">✏️</span> Personalizada...
                                </button>
                            </div>
                        </div>

                        <button type="button" id="btn-detener" onclick="detenerEnvio()" 
                            class="panel-action-button panel-action-button--danger hidden">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Detener
                        </button>
                    </div>
                    <div id="progreso-recordatorios" class="mt-2 hidden text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span id="progreso-texto">Iniciando...</span>
                        </div>
                        <div id="error-debug-msg" class="text-xs text-red-600 mt-2 hidden text-center font-mono bg-red-50 p-2 rounded border border-red-200"></div>
                    </div>
                </div>
                <span class="mt-3 inline-flex justify-center rounded-full bg-blue-600 px-4 py-2 text-base font-bold text-white">
                    {{ $usuarios->total() }} Usuarios
                </span>
            </div>
        </div>

        <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 p-3 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="w-full">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-blue-800">Resumen de resultados por fecha</h2>
                    <p class="mt-1 text-xs text-blue-700">Centraliza el envío desde aquí para no disparar un correo por cada carga de resultado.</p>
                    <p class="mt-2 inline-flex items-center rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700">
                        Edición activa: {{ $resumenNombreEdicion }}
                    </p>
                </div>

                <div class="flex w-full flex-wrap items-stretch gap-2 lg:justify-end" id="resumen-fecha-controls">
                    <select id="resumen-idCategoria" class="panel-control-input">
                        <option value="">Todas las categorías</option>
                        @foreach($categoriasResumen as $categoriaResumen)
                            <option value="{{ $categoriaResumen->id }}" {{ (string) $resumenIdCategoria === (string) $categoriaResumen->id ? 'selected' : '' }}>
                                {{ $categoriaResumen->nombreCategoria }}
                            </option>
                        @endforeach
                    </select>

                    <select id="resumen-idFecha" class="panel-control-input panel-control-input--fecha">
                        <option value="">Seleccionar fecha</option>
                        @foreach($fechasResumen as $fechaResumen)
                            <option value="{{ $fechaResumen->id }}" data-categoria="{{ $fechaResumen->idCategoria }}" {{ (string) $resumenIdFecha === (string) $fechaResumen->id ? 'selected' : '' }}>
                                {{ $fechaResumen->nombre }}
                            </option>
                        @endforeach
                    </select>

                    <button type="button" id="btn-enviar-resumen-fecha" onclick="iniciarEnvioResumenFecha()"
                        class="panel-action-button panel-action-button--summary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Enviar resumen de fecha
                    </button>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('push.suscriptores') }}" class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre o Email..." 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verificación Email</label>
                    <select name="verified_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="todos" {{ request('verified_status') == 'todos' ? 'selected' : '' }}>Todos</option>
                        <option value="verificado" {{ request('verified_status') == 'verificado' ? 'selected' : '' }}>Verificado</option>
                        <option value="no_verificado" {{ request('verified_status') == 'no_verificado' ? 'selected' : '' }}>No Verificado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado Push</label>
                    <select name="push_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="todos" {{ request('push_status') == 'todos' ? 'selected' : '' }}>Todos</option>
                        <option value="activo" {{ request('push_status') == 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ request('push_status') == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Suscripción Email</label>
                    <select name="email_notif_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="todos" {{ request('email_notif_status') == 'todos' ? 'selected' : '' }}>Todos</option>
                        <option value="activo" {{ request('email_notif_status') == 'activo' ? 'selected' : '' }}>Sí, quiere recibir</option>
                        <option value="inactivo" {{ request('email_notif_status') == 'inactivo' ? 'selected' : '' }}>No quiere recibir</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('push.suscriptores') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-center text-sm text-gray-600 transition hover:bg-gray-50">Limpiar</a>
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white shadow-sm transition hover:bg-blue-700">Filtrar Resultados</button>
            </div>
        </form>

        @if($usuarios->isEmpty())
            <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-lg">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <p class="text-gray-500 text-lg">No se encontraron usuarios con esos filtros</p>
            </div>
        @else
            <div class="space-y-3 md:hidden">
                @foreach($usuarios as $user)
                    <article class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0">
                                @if($user->google_avatar)
                                    <img class="h-10 w-10 rounded-full" src="{{ $user->google_avatar }}" alt="">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-lg font-bold text-white shadow-sm">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                                <p class="mt-1 text-[11px] text-gray-400">Reg: {{ $user->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                            <div class="rounded-md bg-gray-50 p-2">
                                <p class="font-semibold text-gray-700">Verificación</p>
                                @if($user->hasVerifiedEmail())
                                    <p class="mt-1 text-green-700">Verificado</p>
                                @else
                                    <p class="mt-1 text-yellow-700">Pendiente</p>
                                @endif
                            </div>
                            <div class="rounded-md bg-gray-50 p-2">
                                <p class="font-semibold text-gray-700">Push</p>
                                <p class="mt-1 text-gray-700">
                                    {{ $user->push_subscriptions_count > 0 ? $user->push_subscriptions_count . ' dispositivo(s)' : 'Inactivo' }}
                                </p>
                            </div>
                            <div class="rounded-md bg-gray-50 p-2">
                                <p class="font-semibold text-gray-700">Email</p>
                                <p class="mt-1 text-gray-700">{{ $user->email_subscribed ? 'Suscrito' : 'Baja' }}</p>
                            </div>
                            <div class="rounded-md bg-gray-50 p-2">
                                <p class="font-semibold text-gray-700">Ultimo envio</p>
                                <p class="mt-1 text-gray-700">{{ $user->last_notification_at ? $user->last_notification_at->format('d/m/Y H:i') : '-' }}</p>
                            </div>
                        </div>

                        @if(!$user->hasVerifiedEmail())
                            <form action="{{ route('push.reenviar-verificacion', $user->id) }}" method="POST" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 rounded-md bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Reenviar verificación
                                </button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto rounded-lg border border-gray-200 md:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Usuario</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Verificación Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Push</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Notif. Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Último Envío</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($usuarios as $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            @if($user->google_avatar)
                                                <img class="h-10 w-10 rounded-full" src="{{ $user->google_avatar }}" alt="">
                                            @else
                                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-gray-900">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                            <div class="text-xs text-gray-400 mt-0.5">Reg: {{ $user->created_at->format('d/m/Y') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->hasVerifiedEmail())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                            Verificado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                            Pendiente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->push_subscriptions_count > 0)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                            📱 {{ $user->push_subscriptions_count }} Dispositivo(s)
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400 italic">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->email_subscribed)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                            📧 Suscrito
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                            🚫 Baja
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->last_notification_at ? $user->last_notification_at->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if(!$user->hasVerifiedEmail())
                                        <form action="{{ route('push.reenviar-verificacion', $user->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-md transition text-xs font-semibold flex items-center gap-1 ml-auto" title="Reenviar email de verificación">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                Reenviar Email
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>

    <style>
        .panel-action-button {
            min-height: 44px;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            padding: 0.625rem 1rem;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.65);
            transition: transform 0.18s ease, filter 0.18s ease, box-shadow 0.18s ease;
        }

        .panel-action-button:not(.hidden) {
            display: inline-flex;
        }

        .panel-action-button:hover {
            transform: translateY(-1px);
            filter: brightness(1.02);
            box-shadow: 0 14px 28px -16px rgba(15, 23, 42, 0.78);
        }

        .panel-action-button:disabled {
            cursor: not-allowed;
            opacity: 0.58;
            transform: none;
            filter: none;
            box-shadow: none;
        }

        .panel-action-button--warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .panel-action-button--primary {
            min-width: 190px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .panel-action-button--summary {
            min-width: 228px;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        }

        .panel-action-button--danger {
            min-width: 140px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .panel-control-input {
            min-height: 44px;
            width: 100%;
            min-width: 185px;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            background: #ffffff;
            padding: 0 0.875rem;
            font-size: 0.9rem;
            color: #0f172a;
            box-shadow: 0 8px 18px -20px rgba(15, 23, 42, 0.8);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .panel-control-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.22);
        }

        .panel-control-input--fecha {
            min-width: 210px;
        }

        @media (min-width: 640px) {
            .panel-action-button {
                width: auto;
                min-width: 198px;
            }

            .panel-action-button--primary {
                min-width: 190px;
            }

            .panel-action-button--summary {
                min-width: 228px;
            }

            .panel-action-button--danger {
                min-width: 140px;
            }

            .panel-control-input {
                width: auto;
            }
        }

        .swal2-popup .swal2-confirm {
            background-color: #2563eb !important;
            color: #ffffff !important;
        }

        .swal2-popup .swal2-confirm:hover {
            background-color: #1d4ed8 !important;
        }

        .swal2-popup .swal2-cancel {
            background-color: #6b7280 !important;
            color: #ffffff !important;
        }

        .swal2-popup .swal2-cancel:hover {
            background-color: #4b5563 !important;
        }
    </style>

    <script>
        let enviando = false;
        let totalEnviados = 0;
        let totalErrores = 0;
        let currentOffset = 0;
        let abortController = null;
        let currentTask = 'recordatorios'; // 'recordatorios' | 'manual' | 'resumen_fecha'
        let currentPayload = {};

        const ENDPOINTS = {
            recordatorios: {
                start_msg: 'Enviando recordatorios...',
                count_route: '{{ route("push.contar-pendientes") }}',
                send_route: '{{ route("push.enviar-lote") }}',
                log_route: '{{ route("push.log-completo") }}',
                batch_size: 10,
            },
            manual: {
                start_msg: 'Enviando notificación masiva...',
                count_route: '{{ route("push.manual.contar") }}',
                send_route: '{{ route("push.manual.enviar-lote") }}',
                log_route: '{{ route("push.log-completo") }}',
                batch_size: 100,
            },
            resumen_fecha: {
                start_msg: 'Enviando resumen de fecha...',
                count_route: '{{ route("push.resumen-fecha.contar") }}',
                send_route: '{{ route("push.resumen-fecha.enviar-lote") }}',
                log_route: '{{ route("push.log-completo") }}',
                batch_size: 100,
            }
        };

        const resumenIdCategoriaInput = document.getElementById('resumen-idCategoria');
        const resumenIdFechaInput = document.getElementById('resumen-idFecha');

        // Recuperar estado al cargar
        document.addEventListener('DOMContentLoaded', () => {
            const estado = JSON.parse(localStorage.getItem('mitai_sending_state'));
            if (estado && estado.active) {
                currentTask = estado.task;
                currentPayload = estado.payload || {};
                totalEnviados = estado.enviados;
                totalErrores = estado.errores;
                currentOffset = estado.offset || 0;
                
                mostrarProgreso(true);
                actualizarTextoProgreso(`Recuperando sesión... Enviados: ${totalEnviados}`);
                
                // Retomar envío
                reanudarEnvio();
            }

            filtrarFechasResumen();

            if (resumenIdCategoriaInput) {
                resumenIdCategoriaInput.addEventListener('change', () => {
                    filtrarFechasResumen();
                    actualizarContextoResumen({
                        resumen_idCategoria: resumenIdCategoriaInput.value,
                        resumen_idFecha: resumenIdFechaInput?.value || '',
                    });
                });
            }

            if (resumenIdFechaInput) {
                resumenIdFechaInput.addEventListener('change', () => {
                    actualizarContextoResumen({
                        resumen_idFecha: resumenIdFechaInput.value,
                    });
                });
            }
        });

        /**
         * Sincroniza los filtros de resumen en query string y recarga la vista.
         */
        function actualizarContextoResumen(cambios = {}) {
            const currentUrl = new URL(window.location.href);

            Object.entries(cambios).forEach(([clave, valor]) => {
                if (valor === null || valor === undefined || valueIsEmpty(valor)) {
                    currentUrl.searchParams.delete(clave);
                    return;
                }

                currentUrl.searchParams.set(clave, String(valor));
            });

            window.location.href = currentUrl.toString();
        }

        function valueIsEmpty(value) {
            return String(value).trim() === '';
        }

        /**
         * Muestra solo fechas asociadas a la categoría seleccionada.
         */
        function filtrarFechasResumen() {
            if (!resumenIdFechaInput || !resumenIdCategoriaInput) {
                return;
            }

            const categoriaSeleccionada = resumenIdCategoriaInput.value;

            Array.from(resumenIdFechaInput.options).forEach((option) => {
                const categoriaOption = option.dataset.categoria;

                if (!categoriaOption || !categoriaSeleccionada || categoriaOption === categoriaSeleccionada) {
                    option.hidden = false;
                    return;
                }

                if (option.selected) {
                    option.selected = false;
                }

                option.hidden = true;
            });

            if (!resumenIdFechaInput.value) {
                resumenIdFechaInput.selectedIndex = 0;
            }
        }

        // --- FUNCIONES UI DROPDOWN ---
        function toggleNotifDropdown() {
            document.getElementById('notif-dropdown').classList.toggle('hidden');
        }
        function cerrarNotifDropdown() {
            document.getElementById('notif-dropdown').classList.add('hidden');
        }
        document.addEventListener('click', function(e) {
            const container = document.getElementById('notif-dropdown-container');
            if (container && !container.contains(e.target)) cerrarNotifDropdown();
        });

        // --- INICIO DE PROCESOS ---

        // 1. Recordatorios (Legacy wrapper)
        async function iniciarEnvioRecordatorios() {
            if (enviando) {
                return;
            }

            try {
                const response = await fetch(ENDPOINTS.recordatorios.count_route, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'No se pudo consultar los pendientes.');
                }

                const pendientes = Number(data.count || 0);
                if (pendientes === 0) {
                    await Swal.fire('Sin pendientes', data.message || 'No hay usuarios para recordar.', 'info');
                    return;
                }

                const confirmacion = await Swal.fire({
                    title: 'Enviar recordatorios',
                    html: `<div style="text-align:left">` +
                        `<p><strong>Usuarios pendientes:</strong> ${pendientes}</p>` +
                        `<p><strong>Tipo de correo:</strong> verificación de cuenta</p>` +
                        `</div>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                });

                if (!confirmacion.isConfirmed) {
                    return;
                }

                iniciarProceso('recordatorios');
            } catch (error) {
                await Swal.fire('No se pudo iniciar', error.message || 'Error al preparar el envío.', 'error');
            }
        }

        // 2. Manual (Predefinida)
        async function prepararNotificacion(titulo, mensaje, url, icono = '📢') {
            await iniciarEnvioNotificacionManual({ titulo, mensaje, url, icono });
        }

        /**
         * Unifica el flujo de envío manual con conteo y confirmación previa.
         */
        async function iniciarEnvioNotificacionManual(payload) {
            if (enviando) {
                return;
            }

            const titulo = String(payload?.titulo || '').trim();
            const mensaje = String(payload?.mensaje || '').trim();
            const url = String(payload?.url || '/').trim() || '/';
            const icono = String(payload?.icono || '📢').trim() || '📢';
            const tituloConIcono = combinarIconoYTitulo(icono, titulo);

            if (!titulo || !mensaje) {
                await Swal.fire('Faltan datos', 'Completá título y mensaje antes de enviar.', 'warning');
                return;
            }

            let data;

            try {
                const response = await fetch(ENDPOINTS.manual.count_route, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                data = await response.json();
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'No se pudo consultar la cantidad de destinatarios.');
                }
            } catch (error) {
                await Swal.fire('No se pudo iniciar', error.message || 'Error al preparar el envío.', 'error');
                return;
            }

            const totalUsuarios = Number(data.count || 0);
            if (totalUsuarios === 0) {
                await Swal.fire('Sin destinatarios', data.message || 'No hay usuarios activos para notificar.', 'info');
                return;
            }

            const result = await Swal.fire({
                title: '¿Enviar notificación masiva?',
                html: `<div style="text-align:left">` +
                    `<p style="margin-bottom:8px"><strong>${tituloConIcono}</strong></p>` +
                    `<p style="margin:0 0 10px;color:#475569">${mensaje}</p>` +
                    `<p><strong>Usuarios:</strong> ${totalUsuarios}</p>` +
                    `<p><strong>Correos estimados:</strong> ${data.correos_estimados || 0} (BCC de ${data.lote_bcc || 100})</p>` +
                    `</div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar a todos',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
            });

            if (result.isConfirmed) {
                iniciarProceso('manual', { titulo, mensaje, url, icono });
            }
        }

        /**
         * Carga datos de notificación desde un botón del dropdown.
         */
        function prepararNotificacionDesdeBoton(button) {
            if (!button) {
                return;
            }

            prepararNotificacion(
                button.dataset.notifTitulo || '',
                button.dataset.notifMensaje || '',
                button.dataset.notifUrl || '/',
                button.dataset.notifIcono || '📢'
            );
        }

        /**
         * Une icono+título evitando duplicar emoji al inicio.
         */
        function combinarIconoYTitulo(icono, titulo) {
            const iconoNormalizado = String(icono || '').trim();
            const tituloNormalizado = String(titulo || '').trim();

            if (!iconoNormalizado) {
                return tituloNormalizado;
            }

            if (!tituloNormalizado) {
                return iconoNormalizado;
            }

            if (tituloNormalizado.startsWith(iconoNormalizado)) {
                return tituloNormalizado;
            }

            return `${iconoNormalizado} ${tituloNormalizado}`;
        }

        /**
         * Dispara envío centralizado del resumen de resultados por fecha.
         */
        async function iniciarEnvioResumenFecha() {
            if (enviando) {
                return;
            }

            const idCategoria = resumenIdCategoriaInput?.value || '';
            const idFecha = resumenIdFechaInput?.value || '';

            if (!idFecha) {
                await Swal.fire('Faltan datos', 'Seleccioná una fecha para enviar el resumen.', 'warning');
                return;
            }

            const params = new URLSearchParams({
                idFecha,
            });

            if (idCategoria) {
                params.set('idCategoria', idCategoria);
            }

            try {
                const response = await fetch(`${ENDPOINTS.resumen_fecha.count_route}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'No hay resultados para enviar en la fecha seleccionada.');
                }

                const confirmacion = await Swal.fire({
                    title: 'Enviar resumen de fecha',
                    html: `<div style="text-align:left">` +
                        `<p><strong>Fecha:</strong> ${data.fecha_nombre}</p>` +
                        `<p><strong>Partidos:</strong> ${data.partidos_count}</p>` +
                        `<p><strong>Usuarios:</strong> ${data.count}</p>` +
                        `<p><strong>Correos estimados:</strong> ${data.correos_estimados} (BCC de ${data.lote_bcc})</p>` +
                        `</div>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar resumen',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                });

                if (!confirmacion.isConfirmed) {
                    return;
                }

                iniciarProceso('resumen_fecha', {
                    idFecha,
                    ...(idCategoria ? { idCategoria } : {}),
                });
            } catch (error) {
                await Swal.fire('No se pudo iniciar', error.message || 'Error al preparar el envío.', 'error');
            }
        }

        // 3. Manual (Personalizada - Modal)
        function abrirModalNotificacion() {
            Swal.fire({
                title: 'Notificación Masiva Personalizada',
                html: `
                    <div class="text-left">
                        <label class="block text-sm font-medium mb-1">Título</label>
                        <input id="sw-titulo" class="swal2-input w-full m-0 mb-3" placeholder="Título">
                        
                        <label class="block text-sm font-medium mb-1">Mensaje</label>
                        <textarea id="sw-mensaje" class="swal2-textarea w-full m-0 mb-3" placeholder="Mensaje"></textarea>
                        
                        <label class="block text-sm font-medium mb-1">URL (Opcional)</label>
                        <input id="sw-url" class="swal2-input w-full m-0 mb-3" value="/">
                        
                        <label class="block text-sm font-medium mb-1">Icono</label>
                        <input id="sw-icono" class="swal2-input w-full m-0" value="📢">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                preConfirm: () => {
                    return {
                        titulo: document.getElementById('sw-titulo').value,
                        mensaje: document.getElementById('sw-mensaje').value,
                        url: document.getElementById('sw-url').value,
                        icono: document.getElementById('sw-icono').value,
                    }
                }
            }).then((res) => {
                if (res.isConfirmed && res.value) {
                    iniciarEnvioNotificacionManual(res.value);
                }
            });
        }

        // --- LÓGICA CORE ---

        function iniciarProceso(taskType, payload = {}) {
            currentTask = taskType;
            currentPayload = payload;
            totalEnviados = 0;
            totalErrores = 0;
            currentOffset = 0;
            
            enviando = true;
            abortController = new AbortController();
            
            mostrarProgreso(true);
            actualizarEstadoLocal(true);
            procesarLotes();
        }

        function reanudarEnvio() {
            enviando = true;
            abortController = new AbortController();
            procesarLotes();
        }

        async function procesarLotes() {
            let done = false;
            const config = ENDPOINTS[currentTask];
            const batchSize = config.batch_size || 10;
            
            while (!done && enviando) {
                try {
                    const body = {
                        ...currentPayload,
                        offset: currentOffset,
                        limit: batchSize
                    };

                    const response = await fetch(config.send_route, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(body),
                        signal: abortController.signal
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'No se pudo procesar el lote.');
                    }

                    // Actualizar métricas
                    totalEnviados += Number(data.sent || 0);
                    totalErrores += Number(data.errors || 0);
                    
                    // Actualizar Offset para siguiente lote
                    currentOffset += batchSize; 

                    // Actualizar UI
                    let msg = '';
                    if (currentTask === 'manual') {
                        msg = `Enviando notificación... Emails enviados: ${totalEnviados} | Errores: ${totalErrores} | Restantes: ${data.remaining ?? 0}`;
                    } else if (currentTask === 'resumen_fecha') {
                        msg = `Enviando resumen... Emails enviados: ${totalEnviados} | Errores: ${totalErrores} | Restantes: ${data.remaining ?? 0}`;
                    } else {
                        msg = `Enviando... Enviados: ${totalEnviados} | Errores: ${totalErrores} | Restantes aprox: ${data.remaining}`;
                    }
                    
                    actualizarTextoProgreso(msg);
                    
                    // Mostrar error si hubo
                    if (data.last_error) {
                        const errDiv = document.getElementById('error-debug-msg');
                        if (errDiv) {
                            errDiv.textContent = "Último error: " + data.last_error;
                            errDiv.classList.remove('hidden');
                        }
                    }

                    actualizarEstadoLocal(true);

                    done = data.done;
                    
                    if (!done) await new Promise(r => setTimeout(r, 500)); // Delay para no saturar

                } catch (e) {
                    if (e.name === 'AbortError') {
                        console.log('Cancelado por usuario');
                    } else {
                        console.error(e);
                        actualizarTextoProgreso('Error de conexión. Reintentando en 5s...');
                        await new Promise(r => setTimeout(r, 5000));
                    }
                }
            }

            if (done) finalizarEnvio(false);
        }

        function detenerEnvio() {
            Swal.fire({
                title: 'Detener proceso',
                text: '¿Deseas detener el envío actual?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, detener',
                cancelButtonText: 'Continuar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }

                enviando = false;
                if (abortController) abortController.abort();
                actualizarEstadoLocal(false);
                finalizarEnvio(true);
            });
        }

        function finalizarEnvio(detenido) {
            enviando = false;
            actualizarEstadoLocal(false);
            
            // Log final al servidor
            if (!detenido) {
                fetch(ENDPOINTS[currentTask].log_route, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ enviados: totalEnviados, errores: totalErrores, tipo: currentTask })
                }).catch(console.error);

                 // Mostrar alerta de éxito
                if (typeof Swal !== 'undefined') {
                    const unidadEnvio = currentTask === 'recordatorios' ? 'recordatorios' : 'correos';
                    Swal.fire('¡Proceso Completado!', `Se enviaron ${totalEnviados} ${unidadEnvio} con ${totalErrores} errores.`, 'success');
                } else {
                    alert('Proceso completado.');
                }
                
                localStorage.removeItem('mitai_sending_state');
                actualizarTextoProgreso('Completado.');
            } else {
                 actualizarTextoProgreso('Detenido por el usuario.');
            }

            mostrarProgreso(false, detenido);
        }

        // --- UTILIDADES ---

        function mostrarProgreso(mostrar, detenido = false) {
            const container = document.getElementById('progreso-recordatorios');
            const btnRec = document.getElementById('btn-enviar-recordatorios');
            const btnMan = document.getElementById('btn-manual');
            const btnResumen = document.getElementById('btn-enviar-resumen-fecha');
            const btnStop = document.getElementById('btn-detener');

            if (mostrar) {
                container.classList.remove('hidden');
                
                // Deshabilitar botón recordatorios
                btnRec.disabled = true;
                btnRec.classList.add('opacity-50', 'cursor-not-allowed');
                
                // Deshabilitar botón manual si existe (es SPA, puede perder referencia si no se busca de nuevo)
                if(btnMan) {
                    btnMan.disabled = true;
                    btnMan.classList.add('opacity-50', 'cursor-not-allowed');
                }

                if(btnResumen) {
                    btnResumen.disabled = true;
                    btnResumen.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                btnStop.classList.remove('hidden');
            } else {
                if (!detenido) container.classList.add('hidden');
                
                btnRec.disabled = false;
                btnRec.classList.remove('opacity-50', 'cursor-not-allowed');
                
                if(btnMan) {
                    btnMan.disabled = false;
                    btnMan.classList.remove('opacity-50', 'cursor-not-allowed');
                }

                if(btnResumen) {
                    btnResumen.disabled = false;
                    btnResumen.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                
                btnStop.classList.add('hidden');
            }
        }

        function actualizarTextoProgreso(txt) {
            const el = document.getElementById('progreso-texto');
            if (el) el.textContent = txt;
        }

        function actualizarEstadoLocal(activo) {
            const estado = {
                active: activo,
                task: currentTask,
                payload: currentPayload,
                enviados: totalEnviados,
                errores: totalErrores,
                offset: currentOffset,
                timestamp: Date.now()
            };
            localStorage.setItem('mitai_sending_state', JSON.stringify(estado));
        }
    </script>
@endsection
