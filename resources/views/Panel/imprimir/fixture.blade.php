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
            font-family: Arial, sans-serif;
            padding: 12px;
            background: white;
            color: #1f2933;
            line-height: 1.25;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border-bottom: 3px solid #0e4b5e;
            padding-bottom: 10px;
            gap: 14px;
        }
        .header-logo {
            width: 48px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header-text h1 {
            color: #0e4b5e;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header-text p {
            color: #666;
            font-size: 14px;
        }
        .categoria-section {
            margin-bottom: 18px;
            page-break-inside: auto;
            break-inside: auto;
        }
        .categoria-title {
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: white;
            padding: 8px 12px;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            border-radius: 5px;
            page-break-after: avoid;
            break-after: avoid;
        }
        .fecha-grupo {
            background: #f0f7f9;
            padding: 6px 10px;
            margin: 8px 0 6px 0;
            border-left: 4px solid #0e4b5e;
            page-break-after: avoid;
            break-after: avoid;
        }
        .fecha-grupo h3 {
            color: #0e4b5e;
            font-size: 14px;
            margin: 0;
        }
        .fecha-grupo span {
            color: #666;
            font-size: 11px;
        }
        .grupo-title {
            background: #eef2f5;
            color: #0e4b5e;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: bold;
            border-left: 3px solid #2a6d81;
            margin: 6px 0 4px 0;
            page-break-after: avoid;
            break-after: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
            page-break-inside: auto;
            break-inside: auto;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        th {
            background: #0e4b5e;
            color: white;
            padding: 6px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }
        td {
            padding: 5px 6px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .equipo {
            font-weight: 600;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .resultado {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            color: #0e4b5e;
            min-width: 50px;
        }
        .horario {
            color: #666;
            font-size: 9px;
        }
        .cancha {
            color: #666;
            font-size: 9px;
        }
        .grupo {
            overflow-wrap: anywhere;
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
            color: #666;
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
                padding-bottom: 6px;
            }
            .header-logo {
                width: 36px;
            }
            .header-text h1 {
                font-size: 18px;
            }
            .header-text p {
                font-size: 11px;
            }
            .categoria-section {
                margin-bottom: 12px;
                page-break-inside: auto;
                break-inside: auto;
            }
            .categoria-title {
                padding: 6px 8px;
                font-size: 13px;
                margin-bottom: 6px;
            }
            .fecha-grupo {
                padding: 4px 8px;
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
                font-size: 10px;
                margin: 4px 0 3px 0;
            }
            th {
                padding: 4px 3px;
                font-size: 8px;
            }
            td {
                padding: 3px;
                font-size: 8px;
            }
            .resultado {
                min-width: 34px;
                font-size: 9px;
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
