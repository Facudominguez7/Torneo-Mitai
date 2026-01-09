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
                    <button type="button" id="btn-enviar-recordatorios" onclick="iniciarEnvioRecordatorios()" 
                        class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span id="btn-text">Enviar Recordatorios</span>
                    </button>
                    <div id="progreso-recordatorios" class="hidden mt-2 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span id="progreso-texto">Iniciando...</span>
                        </div>
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

        async function iniciarEnvioRecordatorios() {
            if (enviando) return;
            
            if (!confirm('¿Enviar recordatorios de verificación a todos los usuarios pendientes?')) {
                return;
            }

            enviando = true;
            totalEnviados = 0;
            totalErrores = 0;
            
            const btn = document.getElementById('btn-enviar-recordatorios');
            const btnText = document.getElementById('btn-text');
            const progreso = document.getElementById('progreso-recordatorios');
            const progresoTexto = document.getElementById('progreso-texto');
            
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btnText.textContent = 'Enviando...';
            progreso.classList.remove('hidden');
            progresoTexto.textContent = 'Iniciando...';

            await enviarLotes(progresoTexto);

            // Finalizar
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            btnText.textContent = 'Enviar Recordatorios';
            progresoTexto.textContent = `✓ Completado. Enviados: ${totalEnviados}, Errores: ${totalErrores}`;
            enviando = false;

            // Ocultar progreso después de 5 segundos
            setTimeout(() => {
                progreso.classList.add('hidden');
            }, 5000);
        }

        async function enviarLotes(progresoTexto) {
            let done = false;
            
            while (!done) {
                try {
                    const response = await fetch('{{ route("push.enviar-lote") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    const data = await response.json();
                    
                    totalEnviados += data.sent;
                    totalErrores += data.errors;
                    
                    progresoTexto.textContent = `Enviados: ${totalEnviados} | Restantes: ${data.remaining}`;
                    
                    done = data.done;
                    
                    // Pequeña pausa entre lotes para no saturar
                    if (!done) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    progresoTexto.textContent = 'Error: ' + error.message;
                    done = true;
                }
            }
        }
    </script>
@endsection