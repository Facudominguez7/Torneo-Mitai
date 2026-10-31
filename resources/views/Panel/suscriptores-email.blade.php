{{--
    Suscriptores (T9a, panel-admin-redesign): tarjeta del resumen de la fecha,
    filtros y tabla de usuarios sobre los componentes x-ui.*. El aviso rápido
    por BCC y el recordatorio de verificación por tandas se retiraron (T10,
    resumen-fecha-suscriptores): los avisos salen por el canal de suscriptores.
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
        // El resumen no va por lotes: queda en cola y sale como un único envío a los suscriptores.
        const ENDPOINTS = {
            resumen_fecha: {
                count_route: '{{ route("notificaciones.resumen-fecha.contar") }}',
                send_route: '{{ route("notificaciones.resumen-fecha.suscriptores") }}',
            }
        };

        const resumenIdCategoriaInput = document.getElementById('resumen-idCategoria');
        const resumenIdFechaInput = document.getElementById('resumen-idFecha');

        document.addEventListener('DOMContentLoaded', () => {
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

        /**
         * Deja en cola el resumen de la fecha para los suscriptores (un único broadcast de Resend).
         * Cada fecha y categoría sale una sola vez; solo se puede reintentar un envío fallido.
         */
        async function iniciarEnvioResumenFecha() {
            const boton = document.getElementById('btn-enviar-resumen-fecha');
            if (boton?.disabled) {
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
    </script>
@endsection
