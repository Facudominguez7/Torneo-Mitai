<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Partido - {{ $equipoSeleccionado->equipo->nombre }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            color: #111;
        }
        .page {
            box-sizing: border-box;
            min-height: calc(297mm - 16mm);
            display: flex;
            flex-direction: column;
        }
        .header {
            margin-bottom: 12px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
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
            display: block;
            margin: 0 auto;
        }
        .title-cell {
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 3px 0 0;
            font-size: 16px;
            color: #555;
        }
        .info {
            margin-bottom: 10px;
        }
        .info p {
            margin: 2px 0;
            font-size: 13px;
        }
        .tabla-jugadores {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .tabla-jugadores th,
        .tabla-jugadores td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            text-align: left;
            vertical-align: middle;
        }
        .tabla-jugadores th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        .tabla-jugadores tbody td {
            height: 24px;
            font-size: 11px;
        }
        .tabla-jugadores tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: auto;
        }
        .declaracion-texto {
            font-size: 10px;
            line-height: 1.35;
            margin: 0 0 8px;
            text-align: justify;
        }
        .firma-bloque {
            margin-bottom: 7px;
        }
        .firma-rol {
            margin: 0 0 3px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .campos-firma {
            display: flex;
            gap: 8px;
        }
        .campo {
            flex: 1;
            font-size: 10px;
        }
        .campo-dni {
            max-width: 110px;
        }
        .campo-firma {
            flex: 1.2;
        }
        .linea-campo {
            height: 14px;
            border-bottom: 1px solid #555;
            margin-top: 2px;
        }
        .footer-note {
            text-align: center;
            font-size: 10px;
            color: #777;
            margin: 0;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.js-logo-equipo').forEach(function(logo) {
                logo.onerror = function() {
                    this.onerror = null;
                    this.src = this.dataset.fallback;
                };
            });
        });

        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="logo-cell">
                        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo Mita'í" class="header-logo">
                    </td>
                    <td class="title-cell">
                        <h1>Planilla de Partido</h1>
                        <h2>{{ $EdicionSeleccionada->nombre }}</h2>
                    </td>
                    <td class="logo-cell">
                        @if($equipoSeleccionado->equipo->foto)
                            <img src="{{ asset('fotos/equipos/' . $equipoSeleccionado->equipo->foto) }}" alt="Logo {{ $equipoSeleccionado->equipo->nombre }}" class="header-logo js-logo-equipo" data-fallback="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}">
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

    <table class="tabla-jugadores">
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
            <p class="declaracion-texto">
                Declaro bajo juramento que la información asentada en esta planilla es correcta y
                que las personas firmantes asumen la responsabilidad administrativa y deportiva
                correspondiente por los jugadores incluidos.
            </p>

            <div class="firma-bloque">
                <p class="firma-rol">Técnico</p>
                <div class="campos-firma">
                    <div class="campo">
                        <span>Apellido y Nombre</span>
                        <div class="linea-campo"></div>
                    </div>
                    <div class="campo campo-dni">
                        <span>DNI</span>
                        <div class="linea-campo"></div>
                    </div>
                    <div class="campo campo-firma">
                        <span>Firma</span>
                        <div class="linea-campo"></div>
                    </div>
                </div>
            </div>

            <div class="firma-bloque">
                <p class="firma-rol">Ayudante</p>
                <div class="campos-firma">
                    <div class="campo">
                        <span>Apellido y Nombre</span>
                        <div class="linea-campo"></div>
                    </div>
                    <div class="campo campo-dni">
                        <span>DNI</span>
                        <div class="linea-campo"></div>
                    </div>
                    <div class="campo campo-firma">
                        <span>Firma</span>
                        <div class="linea-campo"></div>
                    </div>
                </div>
            </div>

            <p class="footer-note">Documento generado automáticamente por el sistema Mita'í.</p>
        </div>
    </div>
</body>
</html>
