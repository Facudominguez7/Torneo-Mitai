<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Resumen de cobros">
    @foreach ([
        'deuda_ars' => 'Deuda de los cargos filtrados',
        'eximido_ars' => 'Exenciones de los cargos filtrados',
        'recaudacion_asociada_ars' => 'Aplicaciones a los cargos filtrados',
        'ingresos_ars' => 'Ingresos reales de la edición (recepción)',
        'credito_ars' => 'Crédito disponible de la edición',
    ] as $clave => $etiqueta)
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-sm text-gray-600">{{ $etiqueta }}</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">$ {{ number_format($resumen[$clave], 0, ',', '.') }}</p>
        </div>
    @endforeach
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-600">Equipos deudores en los filtros</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ $resumen['deudores'] }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-600">Cantidad de cargos con exención en los filtros</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ $resumen['exenciones'] }}</p>
    </div>
</div>
<p class="mt-3 text-sm text-gray-600">ARS enteros. Los ingresos reales y el crédito abarcan toda la edición, no solo el equipo o partido filtrado. Las aplicaciones no son nuevos ingresos. El crédito no depende del rango de recepción.</p>
<ul class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-700" aria-label="Ingresos por medio">
    @foreach (['efectivo' => 'Efectivo', 'mercado_pago' => 'Mercado Pago', 'transferencia' => 'Transferencia (histórico)'] as $medio => $nombre)
        @continue($medio === 'transferencia' && empty($resumen['ingresos_por_medio'][$medio]))
        <li>{{ $nombre }}: $ {{ number_format($resumen['ingresos_por_medio'][$medio] ?? 0, 0, ',', '.') }}</li>
    @endforeach
</ul>
