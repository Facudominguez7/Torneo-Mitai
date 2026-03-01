<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Partido - {{ $equipoSeleccionado->equipo->nombre }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .logo-cell {
            width: 72px;
            text-align: center;
        }
        .header-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .title-cell {
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
            color: #555;
        }
        .info {
            margin-bottom: 20px;
        }
        .info p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo Mitai" class="header-logo">
                </td>
                <td class="title-cell">
                    <h1>Planilla de Partido</h1>
                    <h2>{{ $EdicionSeleccionada->nombre }}</h2>
                </td>
                <td class="logo-cell">
                    @if($equipoSeleccionado->equipo->foto)
                        <img src="{{ asset('fotos/equipos/' . $equipoSeleccionado->equipo->foto) }}" alt="Logo {{ $equipoSeleccionado->equipo->nombre }}" class="header-logo">
                    @else
                        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo {{ $equipoSeleccionado->equipo->nombre }}" class="header-logo">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="info">
        <p><strong>Equipo:</strong> {{ $equipoSeleccionado->equipo->nombre }}</p>
        <p><strong>Categoría:</strong> {{ $equipoSeleccionado->categoria->nombreCategoria }}</p>
        <p><strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">DNI</th>
                <th style="width: 35%;">Apellido y Nombre</th>
                <th style="width: 15%;">Fecha Nac.</th>
                <th style="width: 15%;">N° Camiseta</th>
                <th style="width: 15%;">Asistió</th>
            </tr>
        </thead>
        <tbody>
            @php
                $cantidadFilas = max(16, $jugadoresActivos->count());
            @endphp

            @for ($index = 0; $index < $cantidadFilas; $index++)
                @php
                    $jugador = $jugadoresActivos[$index] ?? null;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $jugador?->dni_jugador ?? '' }}</td>
                    <td>
                        @if($jugador?->jugador)
                            {{ $jugador->jugador->apellido }}, {{ $jugador->jugador->nombre }}
                        @endif
                    </td>
                    <td>{{ $jugador?->fecha_nacimiento ? \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') : '' }}</td>
                    <td style="border: 1px solid #ddd;"></td> <!-- N° Camiseta -->
                    <td style="border: 1px solid #ddd; height: 28px;"></td> <!-- Asistió/Firma -->
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generado automáticamente por el sistema Mitai.</p>
    </div>
</body>
</html>
