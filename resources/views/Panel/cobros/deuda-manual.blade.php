@extends('Panel.admin')

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-ui.page-header title="Cargar deuda de planilla" />
        @if (! $EdicionSeleccionada)
            <x-ui.empty-state message="Seleccioná una edición" description="Elegí una edición en la barra superior para cargar una deuda." />
        @else
            @if (session('success'))<p role="status" class="rounded-md border border-brand-teal bg-white p-3 text-brand-navy">{{ session('success') }}</p>@endif
            @if ($errors->any())
                <div role="alert" class="rounded-md border border-red-300 bg-white p-3 text-red-700">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="rounded-lg border border-gray-200 bg-white">
                @if ($equipo)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-4 sm:px-6">
                        <p class="text-sm text-gray-700">Equipo: <span class="font-semibold text-gray-900">{{ $equipo->nombre }} · {{ $categoriaEquipo }}</span></p>
                        <a href="{{ route('cobros.deuda-manual', ['idEdicion' => $EdicionSeleccionada->id]) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 font-semibold text-gray-700">Cambiar equipo</a>
                    </div>
                @else
                    <form method="GET" action="{{ route('cobros.deuda-manual') }}" class="space-y-2 p-4 sm:px-6"
                        x-data="{
                            q: @js($buscar),
                            normalizar(texto) { return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); },
                            coincide(opcion) { return this.normalizar(this.q) !== '' && opcion.dataset.nombre.includes(this.normalizar(this.q)); },
                            visibles() { return [...this.$refs.lista.children].filter((opcion) => this.coincide(opcion)); },
                            elegirUnico(evento) { const visibles = this.visibles(); if (visibles.length === 1) { evento.preventDefault(); visibles[0].querySelector('a').click(); } },
                        }">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <label for="buscar-equipo" class="block text-sm font-semibold text-gray-700">Buscar equipo</label>
                        <div class="flex flex-wrap gap-2">
                            <input type="search" id="buscar-equipo" name="buscar" value="{{ $buscar }}" x-model="q" @keydown.enter="elegirUnico($event)"
                                placeholder="Nombre del equipo" autocomplete="off" autofocus
                                class="min-h-11 min-w-0 flex-1 rounded-md border-gray-300 focus:border-brand-teal focus:ring-brand-teal">
                            <button class="min-h-11 rounded-md border border-gray-300 px-4 font-semibold text-gray-700">Buscar</button>
                        </div>
                        <ul x-ref="lista" class="max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-md empty:hidden">
                            @foreach ($opcionesEquipo as $opcion)
                                <li data-equipo-opcion="{{ $opcion['id'] }}" data-nombre="{{ $opcion['busqueda'] }}" x-show="coincide($el)"@unless ($opcion['coincide']) style="display: none"@endunless>
                                    <a href="{{ route('cobros.deuda-manual', ['idEdicion' => $EdicionSeleccionada->id, 'idEquipo' => $opcion['id']]) }}"
                                        class="flex min-h-11 items-center justify-between gap-3 px-3 py-2 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                        <span class="font-semibold text-gray-900">{{ $opcion['nombre'] }}</span>
                                        <span class="shrink-0 text-sm text-gray-600">{{ $opcion['categoria'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-sm text-gray-600" x-show="normalizar(q) !== '' && visibles().length === 0"@unless ($buscar !== '' && $opcionesEquipo->where('coincide', true)->isEmpty()) style="display: none"@endunless>No hay equipos con ese nombre en esta edición.</p>
                    </form>
                @endif
                @if ($equipo)
                    <form method="POST" action="{{ route('cobros.deudas-manuales') }}" x-data="{ enviando: false }" @submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                        @csrf
                        <input type="hidden" name="clave_idempotencia" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                        <input type="hidden" name="idEquipo" value="{{ $equipo->id }}">
                        <div class="grid gap-3 px-4 pb-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:px-6">
                            <div><label for="importe_ars" class="block text-sm font-semibold text-gray-700">Importe (ARS)</label><input id="importe_ars" name="importe_ars" inputmode="numeric" value="{{ old('importe_ars', $tarifa) }}" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 tabular-nums"><p class="mt-1 text-sm text-gray-500">Por defecto, la tarifa vigente.</p></div>
                            <div><label for="fecha" class="block text-sm font-semibold text-gray-700">Fecha de la deuda</label><input id="fecha" type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 tabular-nums"><p class="mt-1 text-sm text-gray-500">Por defecto, hoy. Aparece en las planillas desde esa fecha.</p></div>
                            <div class="sm:col-span-2"><label for="motivo" class="block text-sm font-semibold text-gray-700">Motivo (opcional)</label><input id="motivo" name="motivo" maxlength="255" value="{{ old('motivo') }}" placeholder="Ej. Planilla anterior a activar los cobros" class="mt-1 min-h-11 w-full rounded-md border-gray-300"></div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 p-4 sm:px-6">
                            <a href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id]) }}" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 font-semibold text-gray-700">Cancelar</a>
                            <button data-deuda-manual-enviar :disabled="enviando" :aria-busy="enviando" class="min-h-11 rounded-md bg-brand-navy px-4 font-semibold text-white disabled:cursor-wait disabled:opacity-60"><span x-show="! enviando">Cargar deuda</span><span x-show="enviando" x-cloak>Cargando…</span></button>
                        </div>
                    </form>
                @else
                    <p class="px-4 pb-5 text-sm text-gray-600 sm:px-6">Buscá el equipo para cargarle una deuda.</p>
                @endif
            </div>
            <p class="text-sm text-gray-500">La deuda queda como cualquier planilla adeudada: se paga o se exime y queda en el historial.</p>
        @endif
    </div>
@endsection
