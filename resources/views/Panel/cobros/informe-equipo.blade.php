@php
    $pesos = fn (int $importe): string => '$ '.number_format($importe, 0, ',', '.');
    $planillas = fn (int $cantidad): string => $cantidad.' '.($cantidad === 1 ? 'planilla' : 'planillas');
    $estados = ['pagado' => 'Pagada', 'exento' => 'Eximida', 'no_vino' => 'No vino'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Cuenta de planillas · {{ $nombreEquipo }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        .informe-color { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media print { .no-imprimir { display: none; } .informe-hoja { margin: 0; max-width: none; padding: 0; box-shadow: none; } .informe-fila { break-inside: avoid; } }
    </style>
</head>
<body class="bg-gray-100 font-sans text-sm text-gray-900 print:bg-white">
    <div class="no-imprimir mx-auto mt-4 flex max-w-[210mm] flex-wrap gap-3 px-4">
        <button type="button" onclick="window.print()" class="min-h-11 rounded-lg bg-brand-navy px-4 py-2 font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal">Imprimir / guardar PDF</button>
        <a href="{{ route('cobros.index', ['idEdicion' => $EdicionSeleccionada->id, 'idEquipo' => $Equipo->id]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700">‹ Volver a cobros</a>
    </div>

    <article class="informe-hoja mx-auto mb-6 mt-3 max-w-[210mm] bg-white px-4 py-5 shadow-sm sm:px-6">
        <header class="grid items-end gap-4 border-b-2 border-brand-navy pb-4 sm:grid-cols-[1fr_auto]">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brand-teal">Torneo Mitai · Cuenta de planillas</p>
                <h1 class="mt-1 break-words text-2xl font-bold leading-tight">{{ $nombreEquipo }}</h1>
                <p class="text-gray-500">{{ collect([$nombreCategoria, $EdicionSeleccionada->nombre, 'Emitido '.now()->format('d/m/Y')])->filter()->implode(' · ') }}</p>
            </div>
            <div class="informe-color rounded-md border-l-[6px] border-brand-gold bg-brand-navy px-4 py-2.5 text-white sm:text-right">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-brand-teal-light">Saldo a pagar</span>
                <strong class="block text-3xl leading-tight tabular-nums">{{ $pesos($saldo_ars) }}</strong>
            </div>
        </header>

        <div class="mt-5 grid gap-5 md:grid-cols-[1.25fr_1fr] print:grid-cols-[1.25fr_1fr]">
            <section aria-labelledby="informe-debe">
                <h2 id="informe-debe" class="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-red-700">Debe · {{ $planillas($debe->count()) }}</h2>
                <div class="rounded-md border border-gray-200">
                    @forelse ($debe as $planilla)
                        <div class="informe-fila informe-color flex justify-between gap-3 border-t border-gray-200 bg-red-50 px-3 py-2.5 first:border-t-0">
                            <div class="min-w-0">
                                <strong class="block break-words">{{ $planilla['detalle'] }}</strong>
                                @if ($planilla['dia'])<span class="text-xs text-gray-500">{{ $planilla['dia'] }}</span>@endif
                            </div>
                            <div class="shrink-0 text-right font-bold tabular-nums">
                                {{ $pesos($planilla['deuda_ars']) }}
                                @if ($planilla['deuda_ars'] < $planilla['precio_ars'])<span class="block text-xs font-medium text-gray-500">de {{ $pesos($planilla['precio_ars']) }}</span>@endif
                            </div>
                        </div>
                    @empty
                        <p class="px-3 py-2.5 text-gray-600">No debe planillas de partidos jugados.</p>
                    @endforelse
                    @if ($debe->isNotEmpty())
                        <div class="flex justify-between border-t-2 border-red-700 px-3 py-2.5 font-bold tabular-nums"><span>Total</span><span>{{ $pesos($saldo_ars) }}</span></div>
                    @endif
                </div>
            </section>

            <section aria-labelledby="informe-al-dia">
                <h2 id="informe-al-dia" class="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-green-700">Al día · {{ $planillas($al_dia->count()) }}</h2>
                <div class="rounded-md border border-gray-200">
                    @forelse ($al_dia as $planilla)
                        <div class="informe-fila flex justify-between gap-3 border-t border-gray-200 px-3 py-2.5 first:border-t-0">
                            <div class="min-w-0">
                                <strong class="block break-words">{{ $planilla['detalle'] }}</strong>
                                @if ($planilla['dia'])<span class="text-xs text-gray-500">{{ $planilla['dia'] }}</span>@endif
                            </div>
                            <div class="shrink-0 text-right font-bold">
                                {{ $estados[$planilla['estado']] ?? 'Al día' }}
                                <span class="block text-xs font-medium tabular-nums text-gray-500">{{ $pesos($planilla['aplicado_ars']) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="px-3 py-2.5 text-gray-600">Todavía no hay planillas saldadas.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <p class="mt-6 text-xs text-gray-500">Importes en pesos de partidos jugados hasta la fecha de emisión. Este documento es un informe de consulta: no registra pagos.</p>
    </article>
</body>
</html>
