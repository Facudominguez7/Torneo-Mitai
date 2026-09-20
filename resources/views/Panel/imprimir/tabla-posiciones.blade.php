<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas de Posiciones - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
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
        .header-text .header-fecha-resultados {
            color: #0e4b5e;
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
        }
        .categoria-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .categoria-title {
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: white;
            padding: 14px 22px;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .grupo-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .grupo-title {
            background: #f0f7f9;
            color: #0e4b5e;
            padding: 10px 16px;
            font-size: 20px;
            font-weight: bold;
            border-left: 4px solid #0e4b5e;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }
        th, td {
            padding: 9px 7px;
            text-align: center;
            font-size: 15px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #0e4b5e;
            color: white;
            font-weight: bold;
        }
        th:nth-child(1) { width: 6%; }     /* Pos */
        th:nth-child(2) { width: 30%; text-align: left; }  /* Equipo */
        th:nth-child(3) { width: 8%; }     /* Pts */
        th:nth-child(4) { width: 7%; }     /* PJ */
        th:nth-child(5) { width: 7%; }     /* PG */
        th:nth-child(6) { width: 7%; }     /* PE */
        th:nth-child(7) { width: 7%; }     /* PP */
        th:nth-child(8) { width: 7%; }     /* GF */
        th:nth-child(9) { width: 7%; }     /* GC */
        th:nth-child(10) { width: 8%; }    /* DG */
        td:nth-child(1) { text-align: center; }
        td:nth-child(2) { text-align: left; }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .equipo-nombre {
            font-weight: 600;
        }
        .puntos {
            font-weight: bold;
            color: #0e4b5e;
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
            .grupo-section {
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
            <h1>Tablas de Posiciones</h1>
            @if($EdicionSeleccionada)
                <p>{{ $EdicionSeleccionada->nombre }}</p>
            @endif
            @if(! empty($fechaResultados))
                <p class="header-fecha-resultados">Fecha de resultados: {{ $fechaResultados->nombre }}</p>
            @endif
        </div>
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
    </div>

    @if(empty($tablasPorCategoria))
        <p style="text-align: center; color: #666; padding: 30px;">No hay tablas de posiciones disponibles.</p>
    @else
        @foreach($tablasPorCategoria as $categoriaData)
            <div class="categoria-section">
                <div class="categoria-title">{{ $categoriaData['categoria']->nombreCategoria }}</div>
                
                @foreach($categoriaData['grupos'] as $grupoData)
                    <div class="grupo-section">
                        <div class="grupo-title">{{ $grupoData['grupo']->nombre }}</div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Pos</th>
                                    <th>Equipo</th>
                                    <th>Pts</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PE</th>
                                    <th>PP</th>
                                    <th>GF</th>
                                    <th>GC</th>
                                    <th>DG</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $posicion = 1; @endphp
                                @foreach ($grupoData['tabla'] as $filaTabla)
                                    <tr>
                                        <td>{{ $posicion }}°</td>
                                        <td class="equipo-nombre">{{ $filaTabla->nombreEquipo }}</td>
                                        <td class="puntos">{{ $filaTabla->puntos }}</td>
                                        <td>{{ $filaTabla->jugado }}</td>
                                        <td>{{ $filaTabla->ganado }}</td>
                                        <td>{{ $filaTabla->empatado }}</td>
                                        <td>{{ $filaTabla->perdido }}</td>
                                        <td>{{ $filaTabla->golesFavor }}</td>
                                        <td>{{ $filaTabla->golesContra }}</td>
                                        <td>{{ $filaTabla->diferenciaGoles >= 0 ? '+' : '' }}{{ $filaTabla->diferenciaGoles }}</td>
                                    </tr>
                                    @php $posicion++; @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</body>
</html>
