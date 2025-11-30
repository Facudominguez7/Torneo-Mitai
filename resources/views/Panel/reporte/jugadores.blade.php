<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Jugadores</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }
        .header .team-info {
            text-align: center;
            flex-grow: 1;
        }
        .header .team-info img {
            height: 100px;
            margin-bottom: 15px;
        }
        .header img.logo {
            position: absolute;
            top: 0;
            right: 0;
            height: 100px;
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- Foto del equipo -->
        <div class="team-info">
            <img src="{{ asset('fotos/equipos/' . $equipo->foto) }}" alt="Foto del equipo">
            <h1>{{ $equipo->nombre }}</h1>
            <h2>{{ $nombreCategoria->nombre }}</h2>
        </div>
        <!-- Logo del torneo -->
        <img class="logo" src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo del torneo">
    </div>
    <h1>Reporte de Jugadores</h1>
    <table>
        <thead>
            <tr>
                <th>N° Camiseta</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>DNI</th>
                <th>Fecha de Nacimiento</th>
                <th>Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jugadores as $jugador)
            <tr>
                <td></td>
                <td>{{ $jugador->nombre }}</td>
                <td>{{ $jugador->apellido }}</td>
                <td>
                @if (strlen($jugador->dni) === 8)
                    {{ substr($jugador->dni, 0, 2) . '.' . substr($jugador->dni, 2, 3) . '.' . substr($jugador->dni, 5, 3) }}
                @elseif (strlen($jugador->dni) === 7)
                    {{ substr($jugador->dni, 0, 1) . '.' . substr($jugador->dni, 1, 3) . '.' . substr($jugador->dni, 4, 3) }}
                @else
                    {{ $jugador->dni }}
                @endif
                </td>
                <td>{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d-m-Y') }}</td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>