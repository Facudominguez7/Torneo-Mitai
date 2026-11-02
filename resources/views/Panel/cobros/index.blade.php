@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Cobros de planilla" subtitle="Equipos que deben planillas y configuración de cobros de la edición." />
    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición" description="Elegí una edición en la barra superior para consultar sus cobros." />
    @else
        <section class="mt-4 rounded-lg border border-gray-200 bg-white p-4" aria-label="Configuración global">
            <h2 class="font-semibold">Configuración global · {{ $configuracion?->activado_at ? 'Cobros activados' : 'Cobros sin activar' }}</h2>
            <p class="mt-2 text-sm">Tarifa vigente: $ {{ number_format($configuracion?->tarifa_ars ?? 0, 0, ',', '.') }} ARS. Aplica a altas futuras; no cambia deuda histórica.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="button" data-cobro="tarifa" class="min-h-11 rounded border border-gray-300 px-4 py-2">Cambiar tarifa global</button>
                @unless ($configuracion?->activado_at)<button type="button" data-cobro="activar" class="min-h-11 rounded bg-blue-900 px-4 py-2 text-white">Activar cobros…</button>@endunless
            </div>
        </section>
        <a href="{{ route('cobros.deuda-manual', ['idEdicion' => $EdicionSeleccionada->id]) }}" class="mt-4 inline-flex min-h-11 items-center rounded-md bg-brand-navy px-4 font-semibold text-white">Cargar deuda de planilla</a>
        @include('Panel.cobros.deudores')
        @include('Panel.cobros.modal')
    @endif
@endsection

@section('scripts')
    <script>{!! file_get_contents(resource_path('js/cobros.js')) !!}</script>
@endsection
