<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Buena Fe - {{ $equipoSeleccionado->equipo->nombre }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Lista de Buena Fe</h1>
        <h2>{{ $EdicionSeleccionada->nombre }}</h2>
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
                <th style="width: 20%;">DNI</th>
                <th style="width: 40%;">Apellido y Nombre</th>
                <th style="width: 20%;">Fecha Nacimiento</th>
                <th style="width: 15%;">Fecha Alta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jugadoresActivos as $index => $jugador)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $jugador->dni_jugador }}</td>
                    <td>{{ $jugador->jugador->apellido }}, {{ $jugador->jugador->nombre }}</td>
                    <td>{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}</td>
                    <td>{{ $jugador->fecha_alta->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generado automáticamente por el sistema Mita'í.</p>
    </div>
</body>
</html>
