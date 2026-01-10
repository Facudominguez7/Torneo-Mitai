@extends('Panel.admin')

@section('contenido')
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                </svg>
                Gestión de Usuarios y Notificaciones
            </h1>
            <div class="flex items-center gap-3">
                <div id="recordatorios-container">
                    <div class="flex gap-2">
                        <button type="button" id="btn-enviar-recordatorios" onclick="iniciarEnvioRecordatorios()" 
                            class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span id="btn-text">Enviar Recordatorios</span>
                        </button>
                        
                        <!-- Botón Notificación Manual -->
                        <div class="relative" id="notif-dropdown-container">
                            <button type="button" onclick="toggleNotifDropdown()" id="btn-manual"
                                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-sm transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                                </svg>
                                Notificar ▼
                            </button>
                            <!-- Dropdown Menu -->
                            <div id="notif-dropdown" class="absolute hidden bg-white shadow-xl rounded-lg mt-1 z-50 min-w-56 right-0 border border-gray-100">
                                <button onclick="prepararNotificacion('📅 Fixture Actualizado', '¡Se ha cargado el fixture! Consultá los próximos partidos.', '/fixture?idEdicion={{ $EdicionSeleccionada->id ?? '' }}'); cerrarNotifDropdown()"
                                    class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors border-b first:rounded-t-lg">
                                    <span class="mr-2">📅</span> Fixture Cargado
                                </button>
                                <button onclick="prepararNotificacion('⚽ Goleadores Actualizados', '¡Se actualizó la tabla de goleadores! Mirá quiénes lideran.', '/tabla-de-goleadores?idEdicion={{ $EdicionSeleccionada->id ?? '' }}'); cerrarNotifDropdown()"
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
                            class="hidden inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Detener
                        </button>
                    </div>
                    <div id="progreso-recordatorios" class="hidden mt-2 text-sm text-gray-600">
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
                <span class="bg-blue-600 text-white px-4 py-2 rounded-full font-bold text-lg">
                    {{ $usuarios->total() }} Usuarios
                </span>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('push.suscriptores') }}" class="mb-8 bg-gray-50 p-4 rounded-lg border border-gray-200">
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
            <div class="mt-4 flex justify-end gap-2">
                <a href="{{ route('push.suscriptores') }}" class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">Limpiar</a>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 transition shadow-sm">Filtrar Resultados</button>
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
            <div class="overflow-x-auto rounded-lg border border-gray-200">
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

    <script>
        let enviando = false;
        let totalEnviados = 0;
        let totalErrores = 0;
        let currentOffset = 0;
        let abortController = null;
        let currentTask = 'recordatorios'; // 'recordatorios' | 'manual'
        let currentPayload = {};

        const ENDPOINTS = {
            recordatorios: {
                start_msg: 'Enviando recordatorios...',
                send_route: '{{ route("push.enviar-lote") }}',
                log_route: '{{ route("push.log-completo") }}'
            },
            manual: {
                start_msg: 'Enviando notificación masiva...',
                send_route: '{{ route("push.manual.enviar-lote") }}',
                log_route: '{{ route("push.log-completo") }}'
            }
        };

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
        });

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
            if (enviando) return;
            if (!confirm('¿Enviar recordatorios de verificación a todos los usuarios pendientes?')) return;
            
            iniciarProceso('recordatorios');
        }

        // 2. Manual (Predefinida)
        async function prepararNotificacion(titulo, mensaje, url) {
            const result = await Swal.fire({
                title: '¿Enviar notificación masiva?',
                html: `<strong>${titulo}</strong><br><small>${mensaje}</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar a todos',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                iniciarProceso('manual', { titulo, mensaje, url });
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
                    iniciarProceso('manual', res.value);
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
            
            while (!done && enviando) {
                try {
                    const body = {
                        ...currentPayload,
                        offset: currentOffset,
                        limit: 10 // Batch size
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

                    // Actualizar métricas
                    totalEnviados += data.sent;
                    totalErrores += data.errors;
                    
                    // Actualizar Offset para siguiente lote
                    currentOffset += 10; 

                    // Actualizar UI
                    let msg = '';
                    if (currentTask === 'manual') {
                        msg = `Enviando... Progreso: ${totalEnviados} usuarios notificados | Errores: ${totalErrores}`;
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
            if (confirm('¿Detener el proceso actual?')) {
                enviando = false;
                if (abortController) abortController.abort();
                actualizarEstadoLocal(false);
                finalizarEnvio(true);
            }
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
                    Swal.fire('¡Proceso Completado!', `Se enviaron ${totalEnviados} notificaciones con ${totalErrores} errores.`, 'success');
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
                
                btnStop.classList.remove('hidden');
            } else {
                if (!detenido) container.classList.add('hidden');
                
                btnRec.disabled = false;
                btnRec.classList.remove('opacity-50', 'cursor-not-allowed');
                
                if(btnMan) {
                    btnMan.disabled = false;
                    btnMan.classList.remove('opacity-50', 'cursor-not-allowed');
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