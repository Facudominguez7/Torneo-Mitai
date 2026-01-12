<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixture Instancias Finales - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
            font-size: 16px; /* Login aumentado */
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
            width: 80px; /* Logos más grandes */
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header-text h1 {
            color: #0e4b5e;
            font-size: 32px; /* Título más grande */
            margin-bottom: 5px;
        }
        .header-text p {
            color: #666;
            font-size: 18px;
        }
        .categoria-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .categoria-title {
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: white;
            padding: 12px 20px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .copa-title {
            background: #f0f0f0;
            padding: 8px 20px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            border-left: 5px solid #0e4b5e;
            margin-top: 10px;
        }
        .fase-grupo {
            background: #e0e0e0;
            padding: 8px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #555;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th {
            background-color: #f8f9fa;
            color: #0e4b5e;
            padding: 12px;
            text-align: center;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: bold;
            border-bottom: 2px solid #0e4b5e;
        }
        td {
            padding: 15px 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        /* Estilos de equipos y logos */
        .team-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .team-cell.local {
            justify-content: flex-end;
            text-align: right;
        }
        .team-cell.visitante {
            justify-content: flex-start;
            text-align: left;
        }
        .team-logo {
            width: 45px; /* Logos visibles */
            height: 45px;
            object-fit: contain;
        }
        .nombre {
            font-size: 18px;
            font-weight: bold;
            color: #222;
        }
        
        .vs-cell {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #0e4b5e;
            white-space: nowrap;
        }
        .info-cell {
            text-align: center;
            font-size: 14px;
            color: #555;
            line-height: 1.4;
        }
        .info-hora {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            display: block;
        }
        .penales {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0e4b5e;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        @media print {
            .print-button { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Imprimir</button>
    
    <div class="header">
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
        <div class="header-text">
            <h1>Fixture Instancias Finales</h1>
            @if(isset($EdicionSeleccionada))
                <p>{{ $EdicionSeleccionada->nombre }}</p>
            @endif
        </div>
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
    </div>

    @if($partidos->isEmpty())
        <p style="text-align: center; margin-top: 50px; font-size: 18px; color: #666;">No hay partidos de instancias finales cargados para esta edición.</p>
    @else
        @php
            $partidosPorCategoria = $partidos->groupBy('nombre_categoria');
        @endphp

        @foreach($partidosPorCategoria as $categoria => $partidosCategoria)
            <div class="categoria-section">
                <div class="categoria-title">{{ $categoria }}</div>
                
                @php
                    $partidosPorCopa = $partidosCategoria->groupBy('nombre_copa');
                @endphp

                @foreach($partidosPorCopa as $copa => $partidosCopa)
                    @if($copa)
                        <div class="copa-title">{{ $copa }}</div>
                    @endif

                    @php
                        $partidosPorFase = $partidosCopa->groupBy('nombre_fase');
                    @endphp

                    @foreach($partidosPorFase as $fase => $partidosFase)
                        <div class="fase-grupo">
                            {{ $fase }}
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th width="15%">Horario / Cancha</th>
                                    <th width="35%">Local</th>
                                    <th width="15%"></th>
                                    <th width="35%">Visitante</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($partidosFase as $p)
                                    <tr>
                                        <!-- Info: Hora y Cancha -->
                                        <td class="info-cell">
                                            @if($p->horario)
                                                <span class="info-hora">{{ \Carbon\Carbon::parse($p->horario)->format('H:i') }}</span>
                                                <span style="font-size: 12px;">{{ \Carbon\Carbon::parse($p->horario)->format('d/m') }}</span>
                                            @else
                                                -
                                            @endif
                                            <div style="font-size: 12px; margin-top: 4px;">{{ $p->cancha ? 'Cancha ' . $p->cancha : '' }}</div>
                                        </td>
                                        
                                        <!-- Local -->
                                        <td>
                                            <div class="team-cell local">
                                                <span class="nombre">{{ $p->nombre_local }}</span>
                                                <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" class="team-logo" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                            </div>
                                        </td>
                                        
                                        <!-- Resultado/VS -->
                                        <td class="vs-cell">
                                            @if($p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null)
                                                {{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}
                                                @if($p->golesEquipoLocal == $p->golesEquipoVisitante && $p->penalesEquipoLocal !== null)
                                                    <span class="penales">({{ $p->penalesEquipoLocal }}-{{ $p->penalesEquipoVisitante }}) pen.</span>
                                                @endif
                                            @else
                                                vs
                                            @endif
                                        </td>
                                        
                                        <!-- Visitante -->
                                        <td>
                                            <div class="team-cell visitante">
                                                <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" class="team-logo" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                                <span class="nombre">{{ $p->nombre_visitante }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    @endif
</body>
</html>
