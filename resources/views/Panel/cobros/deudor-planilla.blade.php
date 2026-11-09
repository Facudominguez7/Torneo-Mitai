<li data-cobros-planilla class="flex items-baseline justify-between gap-3">
    <span class="min-w-0 break-words text-pa-tinta-media">
        {{ $planilla['detalle'] }}
        @if ($planilla['dia'])
            <span class="text-xs text-pa-tinta-suave">· {{ $planilla['dia'] }}</span>
        @endif
    </span>
    <span class="shrink-0 font-semibold tabular-nums text-pa-tinta">$ {{ number_format($planilla['deuda_ars'], 0, ',', '.') }}</span>
</li>
