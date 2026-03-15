<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla Árbitros - Partidos - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.25;
            margin: 0;
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
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 999px;
            z-index: 1000;
        }

        .documento {
            padding: 12px;
        }

        .page {
            width: 100%;
            max-width: 194mm;
            min-height: calc(297mm - 16mm);
            margin: 0 auto 10px;
            padding: 7mm 7mm 6mm;
            background: #fff;
            border: 1px solid #d5e0e5;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
        }

        .page:last-child {
            margin-bottom: 0;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #0e4b5e;
            margin-bottom: 8px;
        }

        .header-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .header-text h1 {
            font-size: 18px;
            color: #0e4b5e;
            margin-bottom: 1px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .header-text p {
            font-size: 13px;
            color: #4f6470;
        }

        .horario-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: #fff;
            border-radius: 8px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }

        .horario-label {
            font-size: 17px;
            font-weight: 700;
        }

        .horario-meta {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .partidos-lista {
            display: grid;
            gap: 6px;
        }

        .partidos-lista--ajustada {
            align-content: start;
        }

        .partidos-lista--ajustada.partidos-lista--1 {
            grid-template-rows: 1fr;
        }

        .partidos-lista--ajustada.partidos-lista--2 {
            grid-template-rows: repeat(2, 1fr);
        }

        .partidos-lista--ajustada.partidos-lista--3 {
            grid-template-rows: repeat(3, 1fr);
        }

        .partidos-lista--ajustada.partidos-lista--4 {
            grid-template-rows: repeat(4, 1fr);
        }

        .partidos-lista--ajustada.partidos-lista--5 {
            grid-template-rows: repeat(5, 1fr);
        }

        .partidos-lista--ajustada.partidos-lista--6 {
            grid-template-rows: repeat(6, 1fr);
        }

        .partido-card {
            border: 1px solid #b7c9d2;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            flex-direction: column;
        }

        .partido-header {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            padding: 5px 8px;
            background: #edf5f8;
            border-bottom: 1px solid #c8d8de;
            font-size: 13px;
        }

        .partido-meta {
            font-weight: 700;
            color: #0e4b5e;
        }

        .partido-fecha {
            color: #4f6470;
            white-space: nowrap;
        }

        .partido-body {
            display: grid;
            grid-template-columns: 1fr 70px 1fr;
            align-items: stretch;
        }

        .equipo-section {
            padding: 7px 8px 6px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .equipo-contenido {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 4px;
        }

        .equipo-nombre {
            font-size: 16px;
            font-weight: 700;
            color: #153744;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .goles-area {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .goles-label {
            font-size: 12px;
            color: #647985;
            margin-bottom: 2px;
        }

        .goles-lineas {
            border-top: 1px solid #111;
            min-height: 12px;
            margin-top: 3px;
        }

        .resultado-section {
            border-left: 1px solid #d3e0e5;
            border-right: 1px solid #d3e0e5;
            background: #f7fbfc;
            padding: 6px 4px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            gap: 4px;
            align-self: stretch;
            min-height: 100%;
        }

        .resultado-label {
            font-size: 9px;
            color: #60757f;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: center;
            line-height: 1.1;
        }

        .resultado-boxes {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .resultado-box {
            width: 20px;
            height: 20px;
            border: 1px solid #0e4b5e;
            border-radius: 3px;
            background: #fff;
        }

        .resultado-separator {
            font-weight: 700;
            color: #0e4b5e;
        }

        .no-partidos {
            max-width: 194mm;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #d5e0e5;
            border-radius: 10px;
            text-align: center;
            color: #60757f;
            font-style: italic;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-button {
                display: none;
            }

            .documento {
                padding: 0;
            }

            .page {
                max-width: none;
                min-height: calc(297mm - 16mm);
                margin: 0;
                padding: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }

            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .partido-card {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Imprimir</button>

    @if($partidos->isEmpty())
        <p class="no-partidos">No hay partidos pendientes{{ isset($horarioFiltro) && $horarioFiltro ? ' para el horario ' . $horarioFiltro . ' hs' : '' }}.</p>
    @else
        @php
            $partidosPorHorario = $partidos->groupBy(function ($p) {
                return $p->horario_datetime ? \Carbon\Carbon::parse($p->horario_datetime)->format('H:i') : 'Por definir';
            });
        @endphp

        <div class="documento">
            @foreach($partidosPorHorario as $horario => $partidosHorario)
                @php
                    $cantidadPartidosHorario = $partidosHorario->count();
                    $claseDistribucion = $cantidadPartidosHorario <= 6
                        ? 'partidos-lista--ajustada partidos-lista--'.$cantidadPartidosHorario
                        : '';
                @endphp
                <section class="page">
                    <div class="page-header">
                        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
                        <div class="header-text">
                            <h1>Planilla de Arbitros</h1>
                            <p>{{ $EdicionSeleccionada->nombre ?? 'Edicion' }} - Partidos pendientes</p>
                        </div>
                        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
                    </div>

                    <div class="horario-banner">
                        <span class="horario-label">Horario {{ $horario }} hs</span>
                        <span class="horario-meta">{{ $partidosHorario->count() }} {{ $partidosHorario->count() === 1 ? 'partido' : 'partidos' }}</span>
                    </div>

                    <div class="partidos-lista {{ $claseDistribucion }}">
                        @foreach($partidosHorario as $p)
                            @php
                                $nombreFecha = $p->nombre_fecha ?? 'Fecha sin nombre';
                                $nombreGrupo = trim((string) ($p->nombre_grupo ?? ''));
                                $fechaNormalizada = mb_strtolower($nombreFecha);
                                $grupoNormalizado = mb_strtolower($nombreGrupo);
                                $etiquetaGrupo = str_starts_with($grupoNormalizado, 'grupo ')
                                    ? $nombreGrupo
                                    : 'Grupo '.$nombreGrupo;
                                $mostrarGrupo = $nombreGrupo !== ''
                                    && ! $p->es_interzonal
                                    && ! str_contains($fechaNormalizada, 'grupo '.$grupoNormalizado)
                                    && ! str_contains($fechaNormalizada, $grupoNormalizado.' grupo');
                            @endphp
                            <div class="partido-card">
                                <div class="partido-header">
                                    <span class="partido-meta">
                                        <strong>{{ $p->nombre_categoria ?? 'Sin categoria' }}</strong> - {{ $nombreFecha }}
                                        @if($mostrarGrupo) | {{ $etiquetaGrupo }} @endif
                                        @if($p->es_interzonal) | Interzonal @endif
                                        | Cancha {{ $p->cancha ?? '-' }}
                                    </span>
                                    <span class="partido-fecha">{{ $p->horario_datetime ? \Carbon\Carbon::parse($p->horario_datetime)->format('d/m/Y') : '-' }}</span>
                                </div>
                                <div class="partido-body">
                                    <div class="equipo-section">
                                        <div class="equipo-contenido">
                                            <div class="equipo-nombre">{{ $p->nombre_local }}</div>
                                            <div class="goles-area">
                                                <div class="goles-label">Goleadores (N camiseta)</div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="resultado-section">
                                        <div class="resultado-label">Resultado</div>
                                        <div class="resultado-boxes">
                                            <div class="resultado-box"></div>
                                            <span class="resultado-separator">-</span>
                                            <div class="resultado-box"></div>
                                        </div>
                                    </div>

                                    <div class="equipo-section">
                                        <div class="equipo-contenido">
                                            <div class="equipo-nombre">{{ $p->nombre_visitante }}</div>
                                            <div class="goles-area">
                                                <div class="goles-label">Goleadores (N camiseta)</div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                                <div class="goles-lineas"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</body>
</html>
