<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cobros de planilla · {{ $EdicionSeleccionada?->nombre ?? 'Sin edición' }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { color: #111827; background: white; }
        tr, article { break-inside: avoid; }
        @media print { .no-imprimir { display: none; } table { font-size: 10pt; } th, td { padding: 6px !important; } }
    </style>
</head>
<body class="p-6">
    <div class="no-imprimir mb-4 flex flex-wrap gap-3">
        <button type="button" onclick="window.print()" class="min-h-11 rounded border border-gray-400 px-4 py-2">Imprimir / guardar PDF</button>
        <a href="{{ route('cobros.index', $filtros) }}" class="inline-flex min-h-11 items-center underline">Volver a los mismos filtros</a>
    </div>
    <h1 class="text-2xl font-semibold">Cobros de planilla · {{ $EdicionSeleccionada?->nombre ?? 'Sin edición' }}</h1>
    <p class="mt-2 text-sm">Emitido: {{ now()->format('d/m/Y H:i') }} · Importes en ARS enteros · Solo consulta.</p>
    <p class="mt-2 text-sm">Estado: {{ $filtros['estado'] ?? 'deudores' }} · Período: {{ $filtros['periodo'] ?? 'hasta_hoy' }} · Categoría: {{ $categorias->firstWhere('id', $filtros['idCategoria'] ?? null)?->nombreCategoria ?? 'Todas' }} · Equipo: {{ $equipos->firstWhere('id', $filtros['idEquipo'] ?? null)?->nombre ?? 'Todos' }} · Fecha del torneo: {{ $fechas->firstWhere('id', $filtros['idFecha'] ?? null)?->nombre ?? 'Todas' }} · Día: {{ $filtros['fecha'] ?? 'Todos' }} · Partido: {{ $filtros['partido'] ?? 'Todos' }} {{ $filtros['tipo'] ?? '' }}</p>
    <p class="mt-2 text-sm">Recepción de ingresos: {{ $filtros['recibido_desde'] ?? 'Sin inicio' }} a {{ $filtros['recibido_hasta'] ?? 'Sin fin' }}. El detalle incluye todos los cargos filtrados, sin paginación.</p>
    <section class="mt-4">@include('Panel.cobros.resumen')</section>
    <section class="mt-4">@include('Panel.cobros.listado', ['impresion' => true])</section>
</body>
</html>
