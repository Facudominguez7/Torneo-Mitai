<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixture Completo - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #0e4b5e;
            padding-bottom: 15px;
            gap: 20px;
        }
        .header-logo {
            width: 60px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header-text h1 {
            color: #0e4b5e;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .header-text p {
            color: #666;
            font-size: 14px;
        }
        .categoria-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .categoria-title {
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: white;
            padding: 12px 20px;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .fecha-grupo {
            background: #f0f7f9;
            padding: 8px 15px;
            margin: 12px 0 8px 0;
            border-left: 4px solid #0e4b5e;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
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
                padding: 10px;
            }
            .categoria-section {
                page-break-inside: avoid;
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

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 28%;">Local</th>
                                    <th style="width: 10%; text-align: center;">Res.</th>
                                    <th style="width: 28%;">Visitante</th>
                                    <th style="width: 18%;">Fecha/Hora</th>
                                    <th style="width: 8%;">Cancha</th>
                                    <th style="width: 8%;">Grupo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($partidosFecha as $p)
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
                                        <td>
                                            @if($p->idGrupo && isset($grupos[$p->idGrupo]))
                                                {{ $grupos[$p->idGrupo]->nombre }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif
</body>
</html>
