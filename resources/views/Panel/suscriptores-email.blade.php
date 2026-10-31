{{--
    Suscriptores (T9a, panel-admin-redesign): tarjetas de envío (resumen de la
    fecha, aviso rápido, recordatorio de verificación), filtros y tabla de
    usuarios sobre los componentes x-ui.*. El <script> del final es el del
    envío por lotes y no cambió: los ids, data-* y nombres de funciones de acá
    son su contrato (ver ENDPOINTS, mostrarProgreso, localStorage
    `mitai_sending_state`).
--}}
@extends('Panel.admin')

@section('contenido')
    @php
        $resumenIdEdicion = $resumenIdEdicion ?? ($EdicionSeleccionada->id ?? null);
        $resumenNombreEdicion = $resumenNombreEdicion ?? 'Edición vigente';
        $resumenIdCategoria = $resumenIdCategoria ?? null;
        $resumenIdFecha = $resumenIdFecha ?? null;
        $categoriasResumen = $categoriasResumen ?? collect();
        $fechasResumen = $fechasResumen ?? collect();
        $totalSinVerificar = $totalSinVerificar ?? 0;
        $idEdicionNotificacion = $EdicionSeleccionada->id ?? $resumenIdEdicion;
        $urlNotificacionFixture = route('fixture', ['idEdicion' => $idEdicionNotificacion]);
        $urlNotificacionGoleadores = route('tabla-goleadores', ['idEdicion' => $idEdicionNotificacion]);
        $iniciales = static fn (string $nombre): string => collect(preg_split('/\s+/', trim($nombre)))
            ->filter()
            ->take(2)
            ->map(static fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('');
    @endphp

    <x-ui.page-header title="Suscriptores" subtitle="Familias que reciben novedades del torneo por email.">
        <x-slot name="actions">
            <x-ui.badge variant="info">{{ $usuarios->total() }} usuarios</x-ui.badge>
        </x-slot>
    </x-ui.page-header>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Resumen de la fecha --}}
        <article class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div>
                <h2 class="text-base font-bold text-gray-800">Resumen de la fecha</h2>
                <p class="mt-1 text-sm text-gray-500">Resultados de una fecha para los suscriptores que eligieron el resumen.</p>
                <p class="mt-1 text-xs text-gray-400">Edición activa: {{ $resumenNombreEdicion }}</p>
            </div>

            <div class="flex flex-col gap-2" id="resumen-fecha-controls">
                <x-ui.select id="resumen-idCategoria" label="Categoría">
                    <option value="">Todas las categorías</option>
                    @foreach($categoriasResumen as $categoriaResumen)
                        <option value="{{ $categoriaResumen->id }}" {{ (string) $resumenIdCategoria === (string) $categoriaResumen->id ? 'selected' : '' }}>
                            {{ $categoriaResumen->nombreCategoria }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.select id="resumen-idFecha" label="Fecha">
                    <option value="">Seleccionar fecha</option>
                    @foreach($fechasResumen as $fechaResumen)
                        <option value="{{ $fechaResumen->id }}" data-categoria="{{ $fechaResumen->idCategoria }}" {{ (string) $resumenIdFecha === (string) $fechaResumen->id ? 'selected' : '' }}>
                            {{ $fechaResumen->nombre }}
                        </option>
                    @endforeach
                </x-ui.select>

                <x-ui.button id="btn-enviar-resumen-fecha" onclick="iniciarEnvioResumenFecha()" class="mt-1 w-full">
                    Enviar resumen
                </x-ui.button>
            </div>
        </article>

        {{-- Aviso rápido --}}
        <article class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div>
                <h2 class="text-base font-bold text-gray-800">Aviso rápido</h2>
                <p class="mt-1 text-sm text-gray-500">Un mensaje prearmado o uno a medida para todos los suscriptos.</p>
            </div>

            <div id="notif-dropdown-container">
                <x-ui.button id="btn-manual" onclick="toggleNotifDropdown()" class="w-full">
                    Elegir aviso ▼
                </x-ui.button>

                <div id="notif-dropdown" class="mt-2 hidden overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <button type="button"
                        data-notif-titulo="Fixture Actualizado"
                        data-notif-mensaje="Se cargo el fixture. Consulta los proximos partidos."
                        data-notif-url="{{ $urlNotificacionFixture }}"
                        data-notif-icono="📅"
                        onclick="prepararNotificacionDesdeBoton(this); cerrarNotifDropdown()"
                        class="flex w-full items-center gap-2 border-b border-gray-100 px-4 py-3 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-navy/50">
                        <span aria-hidden="true">📅</span> Fixture cargado
                    </button>
                    <button type="button"
                        data-notif-titulo="Goleadores Actualizados"
                        data-notif-mensaje="Se actualizo la tabla de goleadores. Mira quienes lideran."
                        data-notif-url="{{ $urlNotificacionGoleadores }}"
                        data-notif-icono="⚽"
                        onclick="prepararNotificacionDesdeBoton(this); cerrarNotifDropdown()"
                        class="flex w-full items-center gap-2 border-b border-gray-100 px-4 py-3 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-navy/50">
                        <span aria-hidden="true">⚽</span> Goleadores actualizados
                    </button>
                    <button type="button" onclick="abrirModalNotificacion(); cerrarNotifDropdown()"
                        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-bold text-brand-navy transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-navy/50">
                        <span aria-hidden="true">✏️</span> Personalizado…
                    </button>
                </div>
            </div>
        </article>

        {{-- Recordatorio de verificación --}}
        <article class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div>
                <h2 class="text-base font-bold text-amber-800">Recordatorio de verificación</h2>
                <p class="mt-1 text-sm text-amber-800">
                    @if ($totalSinVerificar > 0)
                        {{ $totalSinVerificar }} {{ $totalSinVerificar === 1 ? 'cuenta sin verificar el email' : 'cuentas sin verificar el email' }}. Se envía por tandas para no superar el límite del servidor.
                    @else
                        No hay cuentas sin verificar el email.
                    @endif
                </p>
            </div>

            <div id="recordatorios-container" class="w-full">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button id="btn-enviar-recordatorios" onclick="iniciarEnvioRecordatorios()" class="grow">
                        <span id="btn-text">Enviar recordatorios</span>
                    </x-ui.button>

                    <x-ui.button id="btn-detener" variant="danger" onclick="detenerEnvio()" class="hidden">
                        Detener
                    </x-ui.button>

                    <x-ui.button id="btn-continuar" variant="success" onclick="continuarEnvioPausado()" class="hidden">
                        Continuar
                    </x-ui.button>
                </div>

                <div id="progreso-recordatorios" class="mt-3 hidden text-sm text-amber-800">
                    <div class="flex items-center gap-2">
                        <svg id="progreso-spinner" class="h-4 w-4 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span id="progreso-texto" aria-live="polite">Iniciando...</span>
                    </div>
                    <div id="error-debug-msg" class="mt-2 hidden rounded border border-red-200 bg-red-50 p-2 text-center font-mono text-xs text-red-600"></div>
                </div>
            </div>
        </article>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('notificaciones.index') }}" class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-ui.search id="search" name="search" label="Buscar" placeholder="Nombre o email" :value="request('search')" />

            <x-ui.select id="verified_status" name="verified_status" label="Verificación">
                <option value="todos" {{ request('verified_status') == 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="verificado" {{ request('verified_status') == 'verificado' ? 'selected' : '' }}>Verificado</option>
                <option value="no_verificado" {{ request('verified_status') == 'no_verificado' ? 'selected' : '' }}>Pendiente</option>
            </x-ui.select>

            <x-ui.select id="email_notif_status" name="email_notif_status" label="Suscripción">
                <option value="todos" {{ request('email_notif_status') == 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="activo" {{ request('email_notif_status') == 'activo' ? 'selected' : '' }}>Suscripto</option>
                <option value="inactivo" {{ request('email_notif_status') == 'inactivo' ? 'selected' : '' }}>De baja</option>
            </x-ui.select>
        </div>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <x-ui.button href="{{ route('notificaciones.index') }}" variant="secondary" class="text-center">Limpiar</x-ui.button>
            <x-ui.button type="submit">Filtrar</x-ui.button>
        </div>
    </form>

    @if($usuarios->isEmpty())
        <x-ui.empty-state class="mt-6" message="No se encontraron usuarios con esos filtros"
            description="Probá con otra búsqueda o limpiá los filtros para ver a todos los usuarios." />
    @else
        <div class="mt-6 space-y-3 md:hidden">
            @foreach($usuarios as $user)
                <article class="rounded-xl border border-gray-200 bg-white p-3">
                    <div class="flex items-start gap-3">
                        @if($user->google_avatar)
                            <img class="h-10 w-10 shrink-0 rounded-full" src="{{ $user->google_avatar }}" alt="">
                        @else
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-teal text-sm font-extrabold text-white" aria-hidden="true">{{ $iniciales($user->name) }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            <p class="mt-1 text-[11px] text-gray-400">Registro: {{ $user->created_at->format('d/m/Y') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if($user->hasVerifiedEmail())
                            <x-ui.badge variant="success">Verificado</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning">Pendiente</x-ui.badge>
                        @endif
                        @if($user->email_subscribed)
                            <x-ui.badge variant="success">Suscripto</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">De baja</x-ui.badge>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Último envío: {{ $user->last_notification_at ? $user->last_notification_at->format('d/m/Y H:i') : '-' }}</p>

                    @if(!$user->hasVerifiedEmail())
                        <form action="{{ route('notificaciones.reenviar-verificacion', $user->id) }}" method="POST" class="mt-3">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">Reenviar verificación</x-ui.button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <x-ui.data-table class="mt-6 hidden md:block">
            <x-slot name="head">
                <tr>
                    <th scope="col">Usuario</th>
                    <th scope="col">Verificación</th>
                    <th scope="col">Suscripción</th>
                    <th scope="col">Último envío</th>
                    <th scope="col" class="text-right">Acciones</th>
                </tr>
            </x-slot>

            <x-slot name="body">
                @foreach($usuarios as $user)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                @if($user->google_avatar)
                                    <img class="h-8 w-8 shrink-0 rounded-full" src="{{ $user->google_avatar }}" alt="">
                                @else
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-teal text-xs font-extrabold text-white" aria-hidden="true">{{ $iniciales($user->name) }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($user->hasVerifiedEmail())
                                <x-ui.badge variant="success">Verificado</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning">Pendiente</x-ui.badge>
                            @endif
                        </td>
                        <td>
                            @if($user->email_subscribed)
                                <x-ui.badge variant="success">Suscripto</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral">De baja</x-ui.badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-gray-500">
                            {{ $user->last_notification_at ? $user->last_notification_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="text-right">
                            @if(!$user->hasVerifiedEmail())
                                <form action="{{ route('notificaciones.reenviar-verificacion', $user->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" title="Reenviar email de verificación">Reenviar verificación</x-ui.button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot>
        </x-ui.data-table>

        <div class="mt-6">
            {{ $usuarios->links() }}
        </div>
    @endif

    <style>
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
        let currentTask = 'recordatorios'; // 'recordatorios' | 'manual'
        let currentPayload = {};
        let rateLimitRetries = 0;
        let totalSesionEnviados = 0;
        let pausadoPorProveedor = false;

        /**
         * Delay base entre lotes (ms). Se usa 8s para mantener un ritmo
         * seguro en Hostinger (~7-8 envíos SMTP/minuto como máximo).
         */
        const DELAY_BASE_MS = 8000;

        /**
         * Delays escalonados cuando se detecta rate limiting (ms).
         * 1er intento: 60s, 2do: 120s, 3ro: 180s.
         */
        const RATE_LIMIT_DELAYS = [60000, 120000, 180000];

        /** Tras este número de rate limits consecutivos se entra en cooldown largo. */
        const MAX_RATE_LIMIT_RETRIES = 3;

        /** Cooldown largo en ms (15 minutos) tras agotar reintentos cortos. */
        const COOLDOWN_LARGO_MS = 900000;

        const ENDPOINTS = {
            recordatorios: {
                start_msg: 'Enviando recordatorios...',
                count_route: '{{ route("notificaciones.contar-pendientes") }}',
                send_route: '{{ route("notificaciones.enviar-lote") }}',
                log_route: '{{ route("notificaciones.log-completo") }}',
                batch_size: 10,
            },
            manual: {
                start_msg: 'Enviando notificación masiva...',
                count_route: '{{ route("notificaciones.manual.contar") }}',
                send_route: '{{ route("notificaciones.manual.enviar-lote") }}',
                log_route: '{{ route("notificaciones.log-completo") }}',
                batch_size: 20,
            },
            // Sin lotes: el envío queda en cola y sale como un único broadcast de Resend.
            resumen_fecha: {
                count_route: '{{ route("notificaciones.resumen-fecha.contar") }}',
                send_route: '{{ route("notificaciones.resumen-fecha.suscriptores") }}',
            }
        };

        const resumenIdCategoriaInput = document.getElementById('resumen-idCategoria');
        const resumenIdFechaInput = document.getElementById('resumen-idFecha');

        // Recuperar estado al cargar
        document.addEventListener('DOMContentLoaded', () => {
            const estado = JSON.parse(localStorage.getItem('mitai_sending_state'));
            if (estado) {
                currentTask = estado.task || currentTask;
                currentPayload = estado.payload || {};
                totalEnviados = Number(estado.enviados || 0);
                totalErrores = Number(estado.errores || 0);
                currentOffset = estado.offset || 0;
                pausadoPorProveedor = Boolean(estado.paused_by_provider);

                if (estado.active) {
                    mostrarProgreso(true);
                    actualizarTextoProgreso(`Recuperando sesión... Enviados: ${totalEnviados}`);

                    // Retomar envío
                    reanudarEnvio();
                } else if (pausadoPorProveedor) {
                    mostrarProgreso(true);
                    actualizarTextoProgreso('Envío pausado por bloqueo SMTP en Hostinger. Rehabilitá el correo en hPanel y presioná "Continuar".');
                    actualizarEstadoLocal(false);
                }
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
                const elegibles = Number(data.eligible_total || 0);
                if (pendientes === 0) {
                    if (elegibles === 0) {
                        await Swal.fire('Sin pendientes', data.message || 'No hay usuarios para recordar.', 'info');
                        return;
                    }

                    const reenvio = await Swal.fire({
                        title: 'Sin pendientes por cooldown',
                        html: `<div style="text-align:left">` +
                            `<p>${data.message || 'Todos recibieron recordatorio recientemente.'}</p>` +
                            `<p><strong>Usuarios elegibles para reenvío manual:</strong> ${elegibles}</p>` +
                            `<p>Si continuás, se reenviará aunque hayan recibido recordatorio en los últimos 7 días.</p>` +
                            `</div>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Reenviar igual',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#d97706',
                        cancelButtonColor: '#6b7280',
                    });

                    if (reenvio.isConfirmed) {
                        iniciarProceso('recordatorios', { force: true });
                    }

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

                iniciarProceso('recordatorios', { force: false });
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
         * Deja en cola el resumen de la fecha para los suscriptores (un único broadcast de Resend).
         * Cada fecha y categoría sale una sola vez; solo se puede reintentar un envío fallido.
         */
        async function iniciarEnvioResumenFecha() {
            const boton = document.getElementById('btn-enviar-resumen-fecha');
            if (enviando || boton?.disabled) {
                return;
            }

            const idCategoria = resumenIdCategoriaInput?.value || '';
            const idFecha = resumenIdFechaInput?.value || '';

            if (!idFecha) {
                await Swal.fire('Faltan datos', 'Seleccioná una fecha para enviar el resumen.', 'warning');
                return;
            }

            const datos = { idFecha, ...(idCategoria ? { idCategoria } : {}) };

            try {
                const conteo = await fetch(`${ENDPOINTS.resumen_fecha.count_route}?${new URLSearchParams(datos).toString()}`, {
                    headers: { Accept: 'application/json' },
                });
                const resumen = await conteo.json();
                if (!conteo.ok || !resumen.success) {
                    throw new Error(resumen.message || 'No hay resultados para enviar en la fecha seleccionada.');
                }

                if (resumen.envio?.estado === 'enviado') {
                    await Swal.fire('Ya enviado', `Este resumen ya se envió el ${resumen.envio.enviado_at} a ${resumen.envio.destinatarios} destinatario(s).`, 'info');
                    return;
                }

                if (resumen.envio?.estado === 'pendiente') {
                    await Swal.fire('En cola', 'Este resumen ya está en cola: sale en el próximo minuto.', 'info');
                    return;
                }

                if (resumen.destinatarios === 0) {
                    await Swal.fire('Sin destinatarios', 'Todavía nadie eligió recibir el resumen de la fecha.', 'info');
                    return;
                }

                const reintento = resumen.envio?.estado === 'fallido';
                const confirmacion = await Swal.fire({
                    title: reintento ? 'Reintentar el envío' : 'Enviar a suscriptores',
                    html: `<div style="text-align:left">` +
                        (reintento ? `<p>El envío anterior de este resumen falló.</p>` : '') +
                        `<p><strong>Fecha:</strong> ${resumen.fecha_nombre}</p>` +
                        `<p><strong>Partidos:</strong> ${resumen.partidos_count}</p>` +
                        `<p><strong>Destinatarios:</strong> ${resumen.destinatarios}</p>` +
                        `<p>Cada resumen se envía una sola vez.</p>` +
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

                boton.disabled = true;
                boton.classList.add('opacity-50', 'cursor-not-allowed');

                const respuesta = await fetch(ENDPOINTS.resumen_fecha.send_route, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(datos),
                });
                const envio = await respuesta.json();
                if (!respuesta.ok || !envio.success) {
                    throw new Error(envio.message || 'No se pudo enviar el resumen.');
                }

                await Swal.fire('Resumen en cola', envio.message, 'success');
            } catch (error) {
                await Swal.fire('No se pudo enviar', error.message || 'Error al enviar el resumen.', 'error');
            } finally {
                if (boton) {
                    boton.disabled = false;
                    boton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
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
            rateLimitRetries = 0;
            pausadoPorProveedor = false;
            
            enviando = true;
            abortController = new AbortController();
            
            mostrarProgreso(true);
            actualizarIndicadorCarga(true);
            actualizarEstadoLocal(true);
            procesarLotes();
        }

        function reanudarEnvio() {
            enviando = true;
            pausadoPorProveedor = false;
            abortController = new AbortController();
            mostrarProgreso(true);
            actualizarIndicadorCarga(true);
            actualizarEstadoLocal(true);
            procesarLotes();
        }

        /**
         * Pausa el envío cuando Hostinger deshabilita SMTP (error 554).
         *
         * Mantiene estado y offset para que el administrador reactive el
         * servicio en hPanel y reanude manualmente desde el botón Continuar.
         */
        function pausarPorProveedor(mensaje, detalleTecnico = '') {
            enviando = false;
            pausadoPorProveedor = true;

            mostrarProgreso(true);
            actualizarIndicadorCarga(false);
            actualizarTextoProgreso(mensaje || 'Envío pausado por bloqueo SMTP del proveedor.');

            const errDiv = document.getElementById('error-debug-msg');
            if (errDiv) {
                if (detalleTecnico) {
                    errDiv.textContent = 'Último error: ' + detalleTecnico;
                    errDiv.classList.remove('hidden');
                } else {
                    errDiv.classList.add('hidden');
                }
            }

            actualizarEstadoLocal(false);
        }

        /**
         * Reanuda manualmente un envío pausado por bloqueo de proveedor.
         */
        function continuarEnvioPausado() {
            if (enviando || !pausadoPorProveedor) {
                return;
            }

            // Ocultar inmediatamente el botón para evitar doble click visual.
            pausadoPorProveedor = false;
            mostrarProgreso(true);

            const btnContinuar = document.getElementById('btn-continuar');
            if (btnContinuar) {
                btnContinuar.classList.add('hidden');
            }

            const errDiv = document.getElementById('error-debug-msg');
            if (errDiv) {
                errDiv.classList.add('hidden');
            }

            actualizarTextoProgreso('Reanudando envío...');
            reanudarEnvio();
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

                    // --- Bloqueo 554 en Hostinger: pausar y esperar reanudación manual ---
                    if (data.provider_blocked) {
                        pausarPorProveedor(
                            data.message || 'Hostinger bloqueó temporalmente el envío SMTP. Rehabilitá el correo en hPanel y presioná "Continuar".',
                            data.last_error || ''
                        );
                        break;
                    }

                    // --- Rate limit detectado por el backend ---
                    if (data.rate_limited) {
                        rateLimitRetries++;

                        // Mostrar error técnico
                        const errDiv = document.getElementById('error-debug-msg');
                        if (errDiv) {
                            errDiv.textContent = 'Rate limit: ' + (data.last_error || 'Hostinger limitó envíos temporalmente');
                            errDiv.classList.remove('hidden');
                        }

                        // Cooldown largo: esperar 15 min y reintentar automáticamente
                        if (rateLimitRetries > MAX_RATE_LIMIT_RETRIES) {
                            const horaReintento = new Date(Date.now() + COOLDOWN_LARGO_MS);
                            const horaStr = horaReintento.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
                            const cooldownSec = Math.round(COOLDOWN_LARGO_MS / 1000);

                            // Mostrar error técnico
                            const errDivCooldown = document.getElementById('error-debug-msg');
                            if (errDivCooldown) {
                                errDivCooldown.textContent = 'Cooldown: rate limit persistente de Hostinger. Reintento automático programado.';
                                errDivCooldown.classList.remove('hidden');
                            }

                            for (let restante = cooldownSec; restante > 0 && enviando; restante--) {
                                const minutos = Math.floor(restante / 60);
                                const segundos = restante % 60;
                                actualizarTextoProgreso(
                                    `Cooldown de Hostinger. Reintentando automaticamente a las ${horaStr} ` +
                                    `(faltan ${minutos}:${String(segundos).padStart(2, '0')}). ` +
                                    `Enviados: ${totalEnviados} de ${totalEnviados + (data.remaining ?? 0)}. No cierres esta pestaña.`
                                );
                                await new Promise(r => setTimeout(r, 1000));
                            }

                            // Resetear contador para un nuevo ciclo de reintentos cortos
                            rateLimitRetries = 0;
                            continue;
                        }

                        // Countdown visible con delay escalonado
                        const waitMs = RATE_LIMIT_DELAYS[Math.min(rateLimitRetries - 1, RATE_LIMIT_DELAYS.length - 1)];
                        const waitSec = Math.round(waitMs / 1000);

                        for (let restante = waitSec; restante > 0 && enviando; restante--) {
                            actualizarTextoProgreso(
                                `Rate limit detectado (intento ${rateLimitRetries}/${MAX_RATE_LIMIT_RETRIES}). ` +
                                `Esperando ${restante}s antes de reintentar... ` +
                                `Enviados: ${totalEnviados}`
                            );
                            await new Promise(r => setTimeout(r, 1000));
                        }

                        // NO avanzar offset — reintentar el mismo lote
                        continue;
                    }

                    // --- Éxito: resetear contador de rate limit ---
                    rateLimitRetries = 0;

                    if (data.metrics) {
                        totalEnviados = Number(data.metrics.accepted || 0);
                        totalErrores = Number(data.metrics.failed || 0);
                    }

                    // Actualizar métricas
                    totalSesionEnviados += Number(data.sent || 0);
                    
                    // Actualizar Offset para siguiente lote
                    currentOffset += batchSize; 

                    // Actualizar UI
                    let msg = '';
                    if (currentTask === 'manual') {
                        msg = `Enviando notificación... Emails: ${totalEnviados} | Errores: ${totalErrores} | Restantes: ${data.metrics?.remaining ?? data.remaining ?? 0}`;
                    } else {
                        msg = `Enviando... Enviados: ${totalEnviados} | Errores: ${totalErrores} | Restantes aprox: ${data.metrics?.remaining ?? data.remaining}`;
                    }

                    // Advertencia de cuota de sesión
                    if (totalSesionEnviados >= 400) {
                        msg += ` | Sesion: ${totalSesionEnviados} enviados (limite sugerido: 500)`;
                    }
                    
                    actualizarTextoProgreso(msg);
                    
                    // Mostrar error si hubo (parcial, no rate limit)
                    if (data.last_error) {
                        const errDiv = document.getElementById('error-debug-msg');
                        if (errDiv) {
                            errDiv.textContent = "Último error: " + data.last_error;
                            errDiv.classList.remove('hidden');
                        }
                    }

                    actualizarEstadoLocal(true);

                    done = data.done;
                    
                    // Delay conservador entre lotes para respetar límites Hostinger
                    if (!done) await new Promise(r => setTimeout(r, DELAY_BASE_MS));

                } catch (e) {
                    if (e.name === 'AbortError') {
                        console.log('Cancelado por usuario');
                    } else {
                        console.error(e);
                        actualizarTextoProgreso('Error de conexión. Reintentando en 10s...');
                        await new Promise(r => setTimeout(r, 10000));
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
                pausadoPorProveedor = false;
                if (abortController) abortController.abort();
                actualizarEstadoLocal(false);
                finalizarEnvio(true);
            });
        }

        function finalizarEnvio(detenido) {
            enviando = false;
            actualizarIndicadorCarga(false);
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
                 localStorage.removeItem('mitai_sending_state');
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
            const btnContinuar = document.getElementById('btn-continuar');

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

                if (btnContinuar) {
                    if (pausadoPorProveedor) {
                        btnContinuar.classList.remove('hidden');
                    } else {
                        btnContinuar.classList.add('hidden');
                    }
                }
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

                if (btnContinuar) {
                    btnContinuar.classList.add('hidden');
                }
            }

            actualizarIndicadorCarga(mostrar && enviando && !pausadoPorProveedor);
        }

        function actualizarIndicadorCarga(activo) {
            const spinner = document.getElementById('progreso-spinner');
            if (!spinner) {
                return;
            }

            if (activo) {
                spinner.classList.remove('hidden');
                return;
            }

            spinner.classList.add('hidden');
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
                paused_by_provider: pausadoPorProveedor,
                timestamp: Date.now()
            };
            localStorage.setItem('mitai_sending_state', JSON.stringify(estado));
        }
    </script>
@endsection
