<li data-cobros-planilla class="flex items-baseline justify-between gap-3">
    <span class="min-w-0 break-words text-gray-700">
        {{ $planilla['detalle'] }}
        @if ($planilla['dia'])
            <span class="text-xs text-gray-500">· {{ $planilla['dia'] }}</span>
        @endif
    </span>
    <span class="shrink-0 font-semibold tabular-nums text-gray-900">$ {{ number_format($planilla['deuda_ars'], 0, ',', '.') }}</span>
</li>
