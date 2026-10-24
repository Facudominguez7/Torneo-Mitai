@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Cobros de planilla" subtitle="Consulta de cargos, deuda e ingresos de la edición." />
    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición" description="Elegí una edición en la barra superior para consultar sus cobros." />
    @else
        @include('Panel.cobros.deudores')
        <form method="GET" action="{{ route('cobros.index') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            @foreach (['tipo', 'partido'] as $clave)
                @if (isset($filtros[$clave]))<input type="hidden" name="{{ $clave }}" value="{{ $filtros[$clave] }}">@endif
            @endforeach
            <h2 class="font-semibold text-gray-900">Filtros de cargos</h2>
            @if (isset($filtros['partido']))<p class="mt-2 text-sm">Partido #{{ $filtros['partido'] }} · {{ $filtros['tipo'] }}. Restablecé los filtros para salir de este partido.</p>@endif
            <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="min-w-0"><label for="buscar" class="block text-sm font-medium text-gray-700">Buscar por nombre de equipo</label>
                    <input id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" maxlength="150" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    @error('buscar')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @foreach ([
                    'idCategoria' => ['Categoría', $categorias->pluck('nombreCategoria', 'id')->all(), 'Todas'],
                    'idEquipo' => ['Equipo', $equipos->pluck('nombre', 'id')->all(), 'Todos'],
                    'idFecha' => ['Fecha del torneo', $fechas->pluck('nombre', 'id')->all(), 'Todas'],
                    'estado' => ['Estado', ['deudores' => 'Deudores (pendiente y parcial)', 'todos' => 'Todos', 'pendiente' => 'Pendiente', 'parcial' => 'Parcial', 'pagado' => 'Pagado', 'exento' => 'Exento'], null],
                    'periodo' => ['Período del partido', ['hasta_hoy' => 'Hoy y anteriores', 'futuros' => 'Futuros', 'todos' => 'Todos (incluye sin fecha)'], null],
                ] as $clave => [$etiqueta, $opciones, $vacio])
                    <div class="min-w-0">
                        <label for="{{ $clave }}" class="block text-sm font-medium text-gray-700">{{ $etiqueta }}</label>
                        <select id="{{ $clave }}" name="{{ $clave }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            @if ($vacio)<option value="">{{ $vacio }}</option>@endif
                            @foreach ($opciones as $valor => $nombre)
                                <option value="{{ $valor }}" @selected((string) ($filtros[$clave] ?? ($clave === 'estado' ? 'todos' : ($clave === 'periodo' ? 'hasta_hoy' : ''))) === (string) $valor)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @error($clave)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                @foreach (['fecha' => 'Día del partido', 'recibido_desde' => 'Ingresos recibidos desde', 'recibido_hasta' => 'Ingresos recibidos hasta'] as $clave => $nombre)
                    <div class="min-w-0"><label for="{{ $clave }}" class="block text-sm font-medium text-gray-700">{{ $nombre }}</label>
                        <input id="{{ $clave }}" type="date" name="{{ $clave }}" value="{{ $filtros[$clave] ?? '' }}" class="mt-1 min-h-11 w-full min-w-0 rounded-md border-gray-300 text-sm">
                        @error($clave)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-sm text-gray-600">Al entrar: todos los cargos de hoy y días anteriores, incluidos pagados y exentos. Para buscar futuros o cargos sin fecha, cambiá el período. El rango de recepción afecta solo ingresos reales por medio.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button class="min-h-11 rounded-md bg-blue-900 px-4 py-2 font-medium text-white focus:ring-2 focus:ring-blue-500">Aplicar filtros</button>
                <a class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 py-2 text-gray-700" href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id]) }}">Restablecer</a>
                <a class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-4 py-2 text-gray-700" href="{{ route('cobros.imprimir', $filtros) }}" data-cobros-imprimir onclick="event.preventDefault(); imprimirEnIframe(this.href)">Imprimir / guardar PDF</a>
            </div>
        </form>
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-4" aria-label="Configuración global">
            <h2 class="font-semibold">Configuración global · {{ $configuracion?->activado_at ? 'Cobros activados' : 'Cobros sin activar' }}</h2>
            <p class="mt-2 text-sm">Tarifa vigente: $ {{ number_format($configuracion?->tarifa_ars ?? 0, 0, ',', '.') }} ARS. Aplica a altas futuras; no cambia deuda histórica.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="button" data-cobro="tarifa" class="min-h-11 rounded border border-gray-300 px-4 py-2">Cambiar tarifa global</button>
                @unless ($configuracion?->activado_at)<button type="button" data-cobro="activar" class="min-h-11 rounded bg-blue-900 px-4 py-2 text-white">Activar cobros…</button>@endunless
            </div>
        </section>
        @include('Panel.cobros.modal')
        <section class="mt-6" aria-label="Totales">@include('Panel.cobros.resumen')</section>
        <section class="mt-6" aria-label="Cargos">@include('Panel.cobros.listado')</section>
        <div class="mt-4">{{ $cargos->links() }}</div>
    @endif
@endsection

@section('scripts')
    <script>{!! file_get_contents(resource_path('js/cobros.js')) !!}</script>
@endsection
