<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixture Completo - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html,
        body {
            width: 100%;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, "Segoe UI", Arial, sans-serif;
            padding: 10px;
            background: #f4f7fb;
            color: #10212b;
            line-height: 1.22;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            border-bottom: 2px solid #0e4b5e;
            padding: 6px 0 10px;
            gap: 14px;
        }
        .header-logo {
            width: 42px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header-text h1 {
            color: #0e4b5e;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin-bottom: 3px;
        }
        .header-text p {
            color: #5a6a74;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .categoria-section {
            margin-bottom: 16px;
            page-break-inside: auto;
            break-inside: auto;
        }
        .categoria-title {
            color: #0e4b5e;
            padding: 0 0 6px;
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 6px;
            border-bottom: 1px solid #c9d7e2;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            page-break-after: avoid;
            break-after: avoid;
        }
        .fecha-grupo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #ffffff;
            padding: 8px 10px;
            margin: 8px 0 6px 0;
            border: 1px solid #d5e1ea;
            border-left: 4px solid #0e4b5e;
            border-radius: 8px;
            page-break-after: avoid;
            break-after: avoid;
        }
        .fecha-grupo h3 {
            color: #0e4b5e;
            font-size: 12px;
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .fecha-grupo span {
            color: #5f7080;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #edf3f7;
            border: 1px solid #d3dee7;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .grupo-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eaf0f5;
            color: #0e4b5e;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
            border-radius: 999px;
            margin: 4px 0 4px 0;
            page-break-after: avoid;
            break-after: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            table-layout: fixed;
            page-break-inside: auto;
            break-inside: auto;
            border: 1px solid #d5e1ea;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        th {
            background: linear-gradient(180deg, #0e4b5e, #0a3a49);
            color: white;
            padding: 7px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #e3ebf1;
            font-size: 10px;
            vertical-align: middle;
            background: #fff;
        }
        tr:nth-child(even) {
            background-color: #f8fbfd;
        }
        tbody tr:hover td {
            background-color: #f1f7fb;
        }
        .equipo {
            font-weight: 800;
            color: #10212b;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .resultado {
            text-align: center;
            font-weight: 900;
            font-size: 11px;
            color: #ffffff;
            min-width: 50px;
            background: linear-gradient(180deg, #0e4b5e, #0a3a49);
            border-radius: 999px;
            padding: 4px 8px;
            display: inline-block;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
        }
        .horario {
            color: #4f6371;
            font-size: 9px;
            font-weight: 700;
        }
        .cancha {
            color: #4f6371;
            font-size: 9px;
            font-weight: 700;
        }
        .grupo {
            overflow-wrap: anywhere;
            color: #0e4b5e;
            font-weight: 700;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0e4b5e;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 5px;
            z-index: 1000;
        }
        .print-button:hover {
            background: #2a6d81;
        }
        .no-partidos {
            text-align: center;
            padding: 30px;
            color: #5f7080;
            font-style: italic;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                padding: 0;
                font-size: 9px;
            }
            .header {
                margin-bottom: 10px;
                padding: 4px 0 8px;
            }
            .header-logo {
                width: 34px;
            }
            .header-text h1 {
                font-size: 17px;
            }
            .header-text p {
                font-size: 9px;
            }
            .categoria-section {
                margin-bottom: 12px;
                page-break-inside: auto;
                break-inside: auto;
            }
            .categoria-title {
                padding: 0 0 4px;
                font-size: 12px;
                margin-bottom: 6px;
            }
            .fecha-grupo {
                padding: 6px 8px;
                margin: 6px 0 4px 0;
            }
            .fecha-grupo h3 {
                font-size: 11px;
            }
            .fecha-grupo span {
                font-size: 9px;
            }
            .grupo-title {
                padding: 4px 8px;
                font-size: 9px;
                margin: 4px 0 3px 0;
            }
            th {
                padding: 4px 3px;
                font-size: 8px;
            }
            td {
                padding: 4px 5px;
                font-size: 8px;
            }
            .resultado {
                min-width: 34px;
                font-size: 8px;
                padding: 3px 6px;
            }
            .horario,
            .cancha {
                font-size: 8px;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Imprimir</button>
    
    <div class="header">
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
        <div class="header-text">
            <h1>Fixture Completo</h1>
            @if($EdicionSeleccionada)
                <p>{{ $EdicionSeleccionada->nombre }}</p>
            @endif
            @if(isset($FechaSeleccionada) && $FechaSeleccionada)
                <p>{{ $FechaSeleccionada->nombre }}</p>
            @endif
        </div>
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
    </div>

    @if($partidosPorCategoria->isEmpty())
        <p class="no-partidos">No hay partidos cargados para esta edición.</p>
    @else
        @foreach($categorias as $categoria)
            @php
                $partidosCategoria = $partidosPorCategoria->get($categoria->id);
            @endphp
            
            @if($partidosCategoria && $partidosCategoria->isNotEmpty())
                <div class="categoria-section">
                    <div class="categoria-title">{{ $categoria->nombreCategoria }}</div>
                    
                    @php
                        $partidosPorFecha = $partidosCategoria->groupBy('nombre_fecha');
                    @endphp

                    @foreach($partidosPorFecha as $nombreFecha => $partidosFecha)
                        <div class="fecha-grupo">
                            <h3>{{ $nombreFecha }}</h3>
                            <span>{{ $partidosFecha->count() }} partidos</span>
                        </div>

                        @php
                            $gruposFecha = $partidosFecha->groupBy(fn ($partido) => $partido->idGrupo ?: 'sin-grupo');
                            $mostrarTituloGrupo = $partidosCategoria->pluck('idGrupo')->map(fn ($idGrupo) => $idGrupo ?: 'sin-grupo')->unique()->count() > 1;
                        @endphp

                        @foreach($gruposFecha as $idGrupoFixture => $partidosGrupo)
                            @if($mostrarTituloGrupo)
                                <div class="grupo-title">
                                    @if($idGrupoFixture !== 'sin-grupo' && isset($grupos[$idGrupoFixture]))
                                        {{ $grupos[$idGrupoFixture]->nombre }}
                                    @else
                                        Sin grupo
                                    @endif
                                </div>
                            @endif

                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 31%;">Local</th>
                                        <th style="width: 10%; text-align: center;">Res.</th>
                                        <th style="width: 31%;">Visitante</th>
                                        <th style="width: 20%;">Fecha/Hora</th>
                                        <th style="width: 8%;">Cancha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($partidosGrupo as $p)
                                        <tr>
                                            <td class="equipo">{{ $p->nombre_local }}</td>
                                            <td class="resultado">{{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}</td>
                                            <td class="equipo">{{ $p->nombre_visitante }}</td>
                                            <td class="horario">
                                                @if($p->horario_datetime)
                                                    {{ \Carbon\Carbon::parse($p->horario_datetime)->format('d/m/Y H:i') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="cancha">{{ $p->cancha ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif
</body>
</html>
