<div class="mt-3 flex flex-wrap gap-2">
    <button type="button" class="min-h-11 rounded border border-gray-300 px-3 py-2 text-sm text-blue-900" data-cobro="detalle" data-cargo="{{ json_encode($cargo) }}">Detalle / historial</button>
    @if ($cargo['deuda_ars'] > 0 && $configuracion?->activado_at)
        <button type="button" class="min-h-11 rounded bg-blue-900 px-3 py-2 text-sm text-white" data-cobro="pago" data-cargo="{{ json_encode($cargo) }}">Registrar pago</button>
        <button type="button" class="min-h-11 rounded border border-gray-300 px-3 py-2 text-sm" data-cobro="exencion" data-cargo="{{ json_encode($cargo) }}">Eximir residual</button>
    @endif
    @if ($configuracion?->activado_at)
        <button type="button" class="min-h-11 rounded border border-gray-300 px-3 py-2 text-sm" data-cobro="politica" data-cargo="{{ json_encode($cargo) }}">Exención futura</button>
    @endif
</div>
