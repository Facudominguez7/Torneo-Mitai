@php
    $pesos = fn (int $importe): string => '$ '.number_format($importe, 0, ',', '.');
    $planillas = fn (int $cantidad): string => $cantidad.' '.($cantidad === 1 ? 'planilla' : 'planillas');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Deudores de planillas · {{ $EdicionSeleccionada->nombre }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        .informe-color { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media print { .no-imprimir { display: none; } .informe-hoja { margin: 0; max-width: none; padding: 0; box-shadow: none; } .informe-fila { break-inside: avoid; } }
    </style>
</head>
<body class="imprimible bg-pa-fila-hover font-sans text-sm text-pa-tinta print:bg-pa-superficie">
    <div class="no-imprimir mx-auto mt-4 flex max-w-[210mm] flex-wrap gap-3 px-4">
        <button type="button" onclick="window.print()" class="min-h-11 rounded-lg bg-brand-navy px-4 py-2 font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-pa-acento">Imprimir / guardar PDF</button>
        <a href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-pa-campo-borde bg-pa-superficie px-4 py-2 font-semibold text-pa-tinta-media">‹ Volver a cobros</a>
    </div>

    <article class="informe-hoja mx-auto mb-6 mt-3 max-w-[210mm] bg-pa-superficie px-4 py-5 shadow-sm sm:px-6">
        <header class="grid items-end gap-4 border-b-2 border-pa-resaltado pb-4 sm:grid-cols-[1fr_auto]">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-pa-acento">Torneo Mitai · Deudores de planillas</p>
                <h1 class="mt-1 break-words text-2xl font-bold leading-tight">{{ $EdicionSeleccionada->nombre }}</h1>
                <p class="text-pa-tinta-suave">{{ $deudores->count() }} {{ $deudores->count() === 1 ? 'equipo' : 'equipos' }} · {{ $planillas((int) $deudores->sum('cargos')) }} · Emitido {{ now()->format('d/m/Y') }}</p>
            </div>
            <div class="informe-color rounded-md bg-brand-navy px-4 py-2.5 text-white sm:text-right">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-pa-acento-light">Total adeudado</span>
                <strong class="block text-3xl leading-tight tabular-nums">{{ $pesos((int) $deudores->sum('deuda_ars')) }}</strong>
            </div>
        </header>

        @if ($deudores->isEmpty())
            <p class="mt-5 text-pa-tinta-cuerpo">Ningún equipo debe planillas a hoy.</p>
        @else
            <ol class="mt-5 rounded-md border border-pa-linea">
                @foreach ($deudores as $deudor)
                    <li data-informe-deudor="{{ $deudor['idEquipo'] }}" class="informe-fila border-t border-pa-linea px-3 py-3 first:border-t-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <div class="min-w-0">
                                <strong class="break-words text-base">{{ $deudor['equipo'] }}</strong>
                                <span class="text-xs text-pa-tinta-suave">{{ collect([$deudor['categoria'], $planillas($deudor['cargos'])])->filter()->implode(' · ') }}</span>
                            </div>
                            <strong class="shrink-0 text-base tabular-nums text-pa-peligro">{{ $pesos($deudor['deuda_ars']) }}</strong>
                        </div>
                        <ul class="mt-1.5 flex flex-col gap-0.5 text-pa-tinta-cuerpo">
                            @foreach ($deudor['planillas'] as $planilla)
                                <li class="flex justify-between gap-3">
                                    <span class="min-w-0 break-words">{{ $planilla['detalle'] }}@if ($planilla['dia']) <span class="text-xs text-pa-tinta-suave">· {{ $planilla['dia'] }}</span>@endif</span>
                                    <span class="shrink-0 tabular-nums">{{ $pesos($planilla['deuda_ars']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif

        <p class="mt-6 text-xs text-pa-tinta-suave">Importes en pesos de partidos jugados hasta la fecha de emisión, ordenados de mayor a menor deuda. Este documento es un informe de consulta: no registra pagos.</p>
    </article>
</body>
</html>
