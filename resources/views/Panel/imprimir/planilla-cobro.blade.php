<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Cobro - Jornada {{ $fechaJornada ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #16313c;
            background: #eef3f5;
        }

        .print-button {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #0e4b5e;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 15px;
            cursor: pointer;
            border-radius: 999px;
            z-index: 1000;
        }

        .documento {
            padding: 14px;
        }

        .page {
            width: 100%;
            max-width: 194mm;
            margin: 0 auto 14px;
            padding: 7mm;
            background: #fff;
            border: 1px solid #d5e0e5;
            border-radius: 10px;
        }

        /* ── Encabezado ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0e4b5e;
            margin-bottom: 12px;
        }

        .header-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .header-text h1 {
            font-size: 20px;
            color: #0e4b5e;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .header-text p {
            font-size: 13px;
            color: #4f6470;
            margin-top: 3px;
        }

        /* ── Tabla ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        thead th {
            background: #0e4b5e;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 7px 8px;
            border: 1px solid #09384a;
            text-align: left;
        }

        thead th.center {
            text-align: center;
        }

        tbody tr:nth-child(even) {
            background: #f5f9fb;
        }

        tbody td {
            padding: 12px 8px;
            border: 1px solid #cddde4;
            font-size: 14px;
            color: #16313c;
            vertical-align: middle;
        }

        td.num {
            text-align: center;
            color: #6b8490;
            font-size: 12px;
            width: 30px;
        }

        td.equipo {
            font-weight: 600;
            text-align: center;
        }

        td.horario-cell {
            font-weight: 700;
            font-size: 15px;
            color: #0e4b5e;
            background: #e8f4f8;
            text-align: center;
            white-space: nowrap;
            width: 80px;
            vertical-align: middle;
        }

        td.categoria-cell {
            font-size: 13px;
            font-weight: 600;
            color: #1a4a5a;
            background: #f0f8fb;
            text-align: center;
            vertical-align: middle;
            width: 110px;
        }

        td.monto {
            width: 90px;
            text-align: center;
        }

        /* ── Cierre del día ── */
        .cierre {
            margin-top: 16px;
            border: 2px solid #0e4b5e;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .cierre-titulo {
            background: #0e4b5e;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 8px 14px;
            text-align: center;
        }

        .cierre-body {
            padding: 12px 14px;
        }

        .cierre-totales {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .cierre-total-box {
            border: 1px solid #b7c9d2;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }

        .cierre-total-box .box-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b8490;
            margin-bottom: 6px;
        }

        .cierre-total-box .box-value {
            border-bottom: 1px solid #0e4b5e;
            min-height: 22px;
        }

        .cierre-divider {
            border: none;
            border-top: 1px dashed #b7c9d2;
            margin: 12px 0;
        }

        .egresos-titulo {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0e4b5e;
            margin-bottom: 6px;
        }

        .egresos-tabla {
            margin-bottom: 8px;
        }

        .egresos-tabla thead th {
            background: #d9edf4;
            color: #0e4b5e;
            font-size: 11px;
            padding: 5px 8px;
            border: 1px solid #b7c9d2;
        }

        .egresos-tabla tbody td {
            font-size: 13px;
            padding: 14px 8px;
            border: 1px solid #cddde4;
            min-height: 36px;
        }

        .egresos-tabla td.egreso-concepto {
            width: 75%;
        }

        .egresos-tabla td.egreso-monto {
            width: 25%;
            text-align: center;
        }

        .cierre-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .cierre-label {
            font-size: 13px;
            font-weight: 600;
            color: #0e4b5e;
            min-width: 160px;
            flex-shrink: 0;
        }

        .cierre-linea {
            flex: 1;
            border-bottom: 1px solid #9bb5be;
            min-height: 20px;
        }

        .cierre-firmas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .firma-box {
            text-align: center;
        }

        .firma-linea {
            border-bottom: 1px solid #374f5a;
            min-height: 30px;
            margin-bottom: 5px;
        }

        .firma-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #6b8490;
            letter-spacing: 0.04em;
        }

        /* ── Print ── */
        @media print {
            body { background: #fff; }

            .print-button { display: none; }

            .documento { padding: 0; }

            .page {
                max-width: none;
                margin: 0;
                padding: 0;
                border: none;
                border-radius: 0;
                page-break-after: always;
                break-after: page;
            }

            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .cierre {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            tbody tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Imprimir</button>

    <div class="documento">
        <div class="page">
            <div class="page-header">
                <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
                <div class="header-text">
                    <h1>Planilla de Cobro</h1>
                    <p>{{ $EdicionSeleccionada->nombre ?? 'Edición' }} &mdash; Jornada {{ $fechaJornada ?? '' }}</p>
                </div>
                <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
            </div>

            @if(empty($agrupado))
                <p style="text-align:center; color:#60757f; font-style:italic; padding: 24px 0;">
                    No hay partidos registrados para esta edición.
                </p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Horario</th>
                            <th>Categoría</th>
                            <th>Equipo</th>
                            <th class="center">Mercado Pago ($)</th>
                            <th class="center">Efectivo ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $numero = 1; @endphp
                        @foreach($agrupado as $horario => $categorias)
                            @php
                                $totalEquiposHorario = array_sum(array_map('count', $categorias));
                                $horarioMostrado = false;
                            @endphp
                            @foreach($categorias as $categoria => $equipos)
                                @php
                                    $cantEquipos = count($equipos);
                                    $primeraFila = true;
                                @endphp
                                @foreach($equipos as $equipo)
                                    <tr>
                                        @if(!$horarioMostrado)
                                            <td class="horario-cell" rowspan="{{ $totalEquiposHorario }}">{{ $horario }} hs</td>
                                            @php $horarioMostrado = true; @endphp
                                        @endif
                                        @if($primeraFila)
                                            <td class="categoria-cell" rowspan="{{ $cantEquipos }}">{{ $categoria }}</td>
                                            @php $primeraFila = false; @endphp
                                        @endif
                                        <td class="equipo">{{ $equipo }}</td>
                                        <td class="monto"></td>
                                        <td class="monto"></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                {{-- Cierre del día --}}
                <div class="cierre">
                    <div class="cierre-titulo">Cierre del Día</div>
                    <div class="cierre-body">
                        <div class="cierre-totales">
                            <div class="cierre-total-box">
                                <div class="box-label">Total Mercado Pago</div>
                                <div class="box-value"></div>
                            </div>
                            <div class="cierre-total-box">
                                <div class="box-label">Total Efectivo</div>
                                <div class="box-value"></div>
                            </div>
                            <div class="cierre-total-box" style="border-color:#0e4b5e;">
                                <div class="box-label" style="color:#0e4b5e; font-weight:700;">Total General</div>
                                <div class="box-value" style="border-color:#0e4b5e;"></div>
                            </div>
                        </div>

                        <hr class="cierre-divider">

                        {{-- Egresos / Pagos realizados --}}
                        <div class="egresos-titulo">Egresos (pagos realizados)</div>
                        <table class="egresos-tabla">
                            <thead>
                                <tr>
                                    <th>Concepto / Destinatario</th>
                                    <th class="center">Monto ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for($i = 0; $i < 20; $i++)
                                    <tr>
                                        <td class="egreso-concepto"></td>
                                        <td class="egreso-monto"></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>

                        <div class="cierre-row" style="margin-top: 8px;">
                            <span class="cierre-label">Total Egresos:</span>
                            <div class="cierre-linea" style="max-width: 120px;"></div>
                            <span style="flex:1;"></span>
                            <span class="cierre-label" style="text-align:right;">Saldo final (Ingresos − Egresos):</span>
                            <div class="cierre-linea" style="max-width: 120px;"></div>
                        </div>

                        <hr class="cierre-divider">

                        <div class="cierre-row">
                            <span class="cierre-label">Observaciones:</span>
                            <div class="cierre-linea"></div>
                        </div>
                        <div class="cierre-row">
                            <span class="cierre-label">&nbsp;</span>
                            <div class="cierre-linea"></div>
                        </div>

                        <hr class="cierre-divider">

                        <div class="cierre-firmas">
                            <div class="firma-box">
                                <div class="firma-linea"></div>
                                <div class="firma-label">Responsable de Cobro</div>
                            </div>
                            <div class="firma-box">
                                <div class="firma-linea"></div>
                                <div class="firma-label">Fecha y Hora de Cierre</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
