@extends('Panel.admin')

@section('contenido')
    @php
        $requiereGrupo = $tipo === 'equipogrupo' || $tipo === 'partido';
        $sinCategorias = $categorias->isEmpty();
        $sinGrupos = $requiereGrupo && (isset($grupos) ? $grupos->isEmpty() : true);
        $bloquearContinuar = $sinCategorias || $sinGrupos;
        $contexto = $parametrosContexto ?? [];
        $categoriaSeleccionada = (int) request('idCategoria', $idCategoria);
        $resumenAsignacion = $resumenAsignacion ?? null;
        $equiposPendientesPreview = $equiposPendientesPreview ?? collect();

        $rutasIndice = [
            'partido' => 'partido.index',
            'instancia_final' => 'instancia_final.index',
            'equipogrupo' => 'grupos.index',
            'campeon' => 'campeon.index',
            'subcampeon' => 'subcampeon.index',
            'goleador' => 'goleador.index',
            'valla' => 'valla.index',
        ];

        $rutaIndice = $rutasIndice[$tipo] ?? 'admin';
        $parametrosVolver = array_filter(array_merge([
            'idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion'),
        ], $contexto), static fn ($valor) => $valor !== null && $valor !== '');
    @endphp

    <div class="mx-auto w-full max-w-2xl px-4 py-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-gray-800">Seleccionar contexto</h1>
            <a href="{{ route($rutaIndice, $parametrosVolver) }}"
                class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
                Volver al listado
            </a>
        </div>

        <div class="mb-5 rounded-lg bg-white p-4 shadow-md">
            <label for="idCategoria" class="mb-3 block text-base font-medium text-gray-800">
                Selecciona la categoría
            </label>

            <form action="{{ route($tipo . '.create') }}" method="GET" id="seleccionar-categoria-form" class="space-y-3">
                <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id ?? request('idEdicion') }}">

                @foreach ($contexto as $clave => $valor)
                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                @endforeach

                @if ($sinCategorias)
                    <div class="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-yellow-900">
                        No hay categorías cargadas para esta edición. Creá al menos una categoría para continuar.
                    </div>

                    @if ($EdicionSeleccionada)
                        <div>
                            <a href="{{ route('categoria.create', ['idEdicion' => $EdicionSeleccionada->id]) }}"
                                class="inline-block rounded-md bg-yellow-700 px-4 py-2 text-white hover:bg-yellow-800">
                                Crear categoría
                            </a>
                        </div>
                    @endif
                @else
                    <select name="idCategoria" id="idCategoria"
                        class="w-full rounded-md border border-gray-200 bg-white py-3 px-4 text-base font-medium text-gray-700 outline-none focus:border-brand-navy focus:shadow-md">
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}"
                                {{ $categoriaSeleccionada === (int) $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nombreCategoria }}
                            </option>
                        @endforeach
                    </select>

                    @if ($requiereGrupo)
                        @if ($sinGrupos)
                            <div class="rounded-md border border-orange-300 bg-orange-50 p-4 text-orange-900">
                                No hay grupos para esta categoría. Primero creá un grupo para continuar.
                            </div>

                            @if ($EdicionSeleccionada && $categoriaSeleccionada)
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('grupos.create', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoriaSeleccionada]) }}"
                                        class="inline-block rounded-md bg-orange-700 px-4 py-2 text-white hover:bg-orange-800">
                                        Crear grupo
                                    </a>

                                    @if ($tipo === 'partido')
                                        <a href="{{ route('partido.create-interzonal', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $categoriaSeleccionada]) }}"
                                            class="inline-block rounded-md bg-brand-navy px-4 py-2 text-white hover:bg-brand-navy/90">
                                            Cargar partido interzonal
                                        </a>
                                    @endif
                                </div>
                            @endif
                        @else
                            <select name="idGrupo" id="idGrupo"
                                class="w-full rounded-md border border-gray-200 bg-white py-3 px-4 text-base font-medium text-gray-700 outline-none focus:border-brand-navy focus:shadow-md">
                                @foreach ($grupos as $grupo)
                                    <option value="{{ $grupo->id }}" {{ (int) request('idGrupo') === (int) $grupo->id ? 'selected' : '' }}>
                                        {{ $grupo->nombre }}
                                    </option>
                                @endforeach
                            </select>

                            @if ($tipo === 'equipogrupo' && $resumenAsignacion)
                                <div id="card-asignacion-masiva" class="mt-4 rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 via-cyan-50 to-white p-4 shadow-sm">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Asignacion automatica</p>
                                            <h2 class="text-lg font-bold text-gray-900">Distribuir equipos pendientes en 1 click</h2>
                                        </div>
                                        <button type="button" id="btn-asignar-pendientes"
                                            data-endpoint="{{ route('equipogrupo.asignar-pendientes') }}"
                                            {{ $resumenAsignacion['pendientes'] === 0 ? 'disabled' : '' }}
                                            class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-blue-300">
                                            {{ $resumenAsignacion['pendientes'] === 0 ? 'Sin pendientes por asignar' : 'Asignar pendientes automaticamente' }}
                                        </button>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div class="rounded-lg bg-white p-3 shadow-sm">
                                            <p class="text-xs text-gray-500">Total equipos</p>
                                            <p class="text-2xl font-bold text-gray-900" data-resumen="total_equipos">{{ $resumenAsignacion['total_equipos'] }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white p-3 shadow-sm">
                                            <p class="text-xs text-gray-500">Ya asignados</p>
                                            <p class="text-2xl font-bold text-emerald-700" data-resumen="ya_asignados">{{ $resumenAsignacion['ya_asignados'] }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white p-3 shadow-sm">
                                            <p class="text-xs text-gray-500">Pendientes</p>
                                            <p class="text-2xl font-bold text-amber-700" data-resumen="pendientes">{{ $resumenAsignacion['pendientes'] }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded-lg border border-blue-100 bg-white p-3">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-700">Distribucion actual por grupo</p>
                                        <div class="space-y-2">
                                            @foreach ($resumenAsignacion['grupos'] as $grupoResumen)
                                                <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2" data-grupo-id="{{ $grupoResumen['id'] }}">
                                                    <span class="text-sm font-medium text-gray-800">{{ $grupoResumen['nombre'] }}</span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-800" data-grupo-total>{{ $grupoResumen['equipos_actuales'] }}</span>
                                                        <span class="hidden rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800" data-grupo-nuevos></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-3" id="resumen-pendientes-preview">
                                        @if ($equiposPendientesPreview->isNotEmpty())
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">Pendientes (preview)</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($equiposPendientesPreview as $equipoPendiente)
                                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">{{ $equipoPendiente->nombre }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($tipo === 'partido' && $categoriaSeleccionada)
                        <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                            Atajo: también podés crear un interzonal sin seleccionar grupo.
                            <a href="{{ route('partido.create-interzonal', ['idEdicion' => $EdicionSeleccionada->id ?? request('idEdicion'), 'idCategoria' => $categoriaSeleccionada]) }}"
                                class="ml-1 font-semibold underline">
                                Ir a interzonal
                            </a>
                        </div>
                    @endif
                @endif

                <div class="flex justify-center">
                    <button type="submit"
                        {{ $bloquearContinuar ? 'disabled' : '' }}
                        class="rounded-lg bg-brand-navy px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-navy/90 disabled:cursor-not-allowed disabled:opacity-60">
                        Continuar con carga manual
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script type="application/json" id="contexto-seleccion-data">@json($contexto)</script>

    <script>
        const selectCategoria = document.getElementById('idCategoria');
        if (selectCategoria) {
            selectCategoria.addEventListener('change', function() {
                const idCategoria = this.value;
                const idEdicion = document.querySelector('input[name="idEdicion"]').value;
                const url = "{{ route('seleccionar-categoria') }}";
                const params = new URLSearchParams({
                    idCategoria,
                    idEdicion,
                    tipo: "{{ $tipo }}",
                    forzarSeleccion: 1,
                });

                const contextoData = document.getElementById('contexto-seleccion-data');
                const contexto = contextoData ? JSON.parse(contextoData.textContent || '{}') : {};
                Object.entries(contexto).forEach(([clave, valor]) => {
                    params.set(clave, valor);
                });

                window.location.href = `${url}?${params.toString()}`;
            });
        }

        const botonAsignarPendientes = document.getElementById('btn-asignar-pendientes');
        const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const actualizarCardAsignacion = (data) => {
            if (!data) {
                return;
            }

            const resumenPendientes = document.querySelector('[data-resumen="pendientes"]');
            const resumenAsignados = document.querySelector('[data-resumen="ya_asignados"]');

            if (resumenPendientes) {
                resumenPendientes.textContent = data.pendientes ?? 0;
            }

            if (resumenAsignados) {
                resumenAsignados.textContent = data.ya_asignados ?? 0;
            }

            if (Array.isArray(data.grupos)) {
                data.grupos.forEach((grupo) => {
                    const filaGrupo = document.querySelector(`[data-grupo-id="${grupo.id}"]`);
                    if (!filaGrupo) {
                        return;
                    }

                    const totalGrupo = filaGrupo.querySelector('[data-grupo-total]');
                    const nuevosGrupo = filaGrupo.querySelector('[data-grupo-nuevos]');

                    if (totalGrupo) {
                        totalGrupo.textContent = grupo.equipos_totales ?? 0;
                    }

                    if (nuevosGrupo) {
                        const nuevos = Number.parseInt(grupo.nuevos_asignados ?? 0, 10);
                        if (nuevos > 0) {
                            nuevosGrupo.textContent = `+${nuevos} nuevos`;
                            nuevosGrupo.classList.remove('hidden');
                        } else {
                            nuevosGrupo.textContent = '';
                            nuevosGrupo.classList.add('hidden');
                        }
                    }
                });
            }

            const previewPendientes = document.getElementById('resumen-pendientes-preview');
            if (previewPendientes && Number.parseInt(data.pendientes ?? 0, 10) === 0) {
                previewPendientes.innerHTML = '<p class="rounded-md border border-emerald-200 bg-emerald-50 p-2 text-xs font-semibold text-emerald-700">No quedan equipos pendientes en esta categoría.</p>';
            }

            if (botonAsignarPendientes && Number.parseInt(data.pendientes ?? 0, 10) === 0) {
                botonAsignarPendientes.disabled = true;
                botonAsignarPendientes.textContent = 'Sin pendientes por asignar';
            }
        };

        if (botonAsignarPendientes) {
            botonAsignarPendientes.addEventListener('click', async () => {
                const inputCategoria = document.getElementById('idCategoria');
                const inputEdicion = document.querySelector('input[name="idEdicion"]');

                if (!inputCategoria || !inputEdicion) {
                    return;
                }

                const pendientesActuales = Number.parseInt(document.querySelector('[data-resumen="pendientes"]')?.textContent ?? '0', 10);
                if (pendientesActuales <= 0) {
                    await Swal.fire('Sin pendientes', 'Todos los equipos ya estan asignados en grupos.', 'info');
                    botonAsignarPendientes.disabled = true;
                    botonAsignarPendientes.textContent = 'Sin pendientes por asignar';
                    return;
                }

                const confirmacion = await Swal.fire({
                    title: 'Asignar equipos pendientes',
                    text: `Se asignaran ${pendientesActuales} equipos sin grupo en forma balanceada.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Si, asignar ahora',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#1d4ed8',
                    buttonsStyling: false,
                    customClass: {
                        actions: 'gap-3',
                        confirmButton: 'rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                        cancelButton: 'rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2',
                    },
                });

                if (!confirmacion.isConfirmed) {
                    return;
                }

                const textoOriginal = botonAsignarPendientes.textContent;
                botonAsignarPendientes.disabled = true;
                botonAsignarPendientes.textContent = 'Asignando...';

                try {
                    const response = await fetch(botonAsignarPendientes.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': tokenCsrf ?? '',
                        },
                        body: JSON.stringify({
                            idEdicion: inputEdicion.value,
                            idCategoria: inputCategoria.value,
                        }),
                    });

                    const resultado = await response.json();
                    if (!response.ok || !resultado.success) {
                        throw new Error(resultado.message ?? 'No se pudo completar la asignacion automatica.');
                    }

                    actualizarCardAsignacion(resultado.data);

                    await Swal.fire({
                        icon: 'success',
                        title: 'Asignacion completada',
                        text: resultado.message,
                        confirmButtonColor: '#1d4ed8',
                    });

                    const pendientesRestantes = Number.parseInt(resultado.data?.pendientes ?? '0', 10);
                    if (pendientesRestantes > 0) {
                        botonAsignarPendientes.disabled = false;
                        botonAsignarPendientes.textContent = textoOriginal;
                    }
                } catch (error) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message ?? 'No se pudo completar la asignacion automatica.',
                        confirmButtonColor: '#dc2626',
                    });

                    botonAsignarPendientes.disabled = false;
                    botonAsignarPendientes.textContent = textoOriginal;
                    return;
                }
            });
        }
    </script>
@endsection
