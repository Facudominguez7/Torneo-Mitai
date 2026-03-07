<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla Árbitros - Partidos - {{ $EdicionSeleccionada->nombre ?? 'Edición' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.3;
            padding: 15px;
            background: white;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #0e4b5e;
            padding-bottom: 12px;
            gap: 15px;
        }
        .header-logo {
            width: 50px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header-text h1 {
            color: #0e4b5e;
            font-size: 26px;
            margin-bottom: 3px;
        }
        .header-text p {
            color: #666;
            font-size: 14px;
        }
        .categoria-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .categoria-title {
            background: linear-gradient(135deg, #0e4b5e, #2a6d81);
            color: white;
            padding: 10px 15px;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .partido-card {
            border: 2px solid #0e4b5e;
            border-radius: 8px;
            margin-bottom: 15px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .partido-header {
            background: #0e4b5e;
            color: white;
            padding: 8px 12px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .partido-meta {
            font-weight: 700;
            color: inherit;
        }
        .partido-body {
            display: flex;
            align-items: stretch;
        }
        .equipo-section {
            flex: 1;
            padding: 12px 14px;
            text-align: center;
        }
        .equipo-nombre {
            font-size: 17px;
            font-weight: bold;
            color: #0e4b5e;
            margin-bottom: 10px;
            min-height: 24px;
        }
        .goles-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }
        .goles-lineas {
            border-top: 1px solid #999;
            margin: 6px 0;
            min-height: 24px;
        }
        .resultado-section {
            width: 96px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f0f7f9;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            padding: 12px 6px;
        }
        .resultado-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 7px;
        }
        .resultado-boxes {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .resultado-box {
            width: 34px;
            height: 34px;
            border: 2px solid #0e4b5e;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            background: white;
        }
        .resultado-separator {
            font-size: 18px;
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
            font-size: 15px;
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
                padding: 8px;
            }
            .partido-header {
                background: #fff !important;
                color: #111827 !important;
                border-bottom: 1px solid #0e4b5e;
            }
            .partido-card {
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
            <h1>Planilla de Árbitros</h1>
            @if($EdicionSeleccionada)
                <p>{{ $EdicionSeleccionada->nombre }} - Partidos (Pendientes){{ isset($horarioFiltro) && $horarioFiltro ? ' - ' . $horarioFiltro . ' hs' : '' }}</p>
            @endif
        </div>
        <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo" class="header-logo">
    </div>

    @if($partidos->isEmpty())
        <p class="no-partidos">No hay partidos pendientes{{ isset($horarioFiltro) && $horarioFiltro ? ' para el horario ' . $horarioFiltro . ' hs' : '' }}.</p>
    @else
        @php
            $partidosPorHorario = $partidos->groupBy(function ($p) {
                return $p->horario_datetime ? \Carbon\Carbon::parse($p->horario_datetime)->format('H:i') : 'Por definir';
            });
        @endphp

        @foreach($partidosPorHorario as $horario => $partidosHorario)
            <div class="categoria-section" @if(! $loop->last) style="page-break-after: always;" @endif>
                <div class="categoria-title">Horario: {{ $horario }} hs</div>

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
                                <strong>{{ $p->nombre_categoria ?? 'Sin categoría' }}</strong> - {{ $nombreFecha }}
                                @if($mostrarGrupo) | {{ $etiquetaGrupo }} @endif
                                @if($p->es_interzonal) | Interzonal @endif
                                | Cancha {{ $p->cancha ?? '-' }}
                            </span>
                            <span>{{ $p->horario_datetime ? \Carbon\Carbon::parse($p->horario_datetime)->format('d/m/Y') : '-' }}</span>
                        </div>
                        <div class="partido-body">
                            <div class="equipo-section">
                                <div class="equipo-nombre">{{ $p->nombre_local }}</div>
                                <div class="goles-label">Goleadores (N° camiseta)</div>
                                <div class="goles-lineas"></div>
                                <div class="goles-lineas"></div>
                                <div class="goles-lineas"></div>
                            </div>

                            <div class="resultado-section">
                                <div class="resultado-label">RESULTADO</div>
                                <div class="resultado-boxes">
                                    <div class="resultado-box"></div>
                                    <span class="resultado-separator">-</span>
                                    <div class="resultado-box"></div>
                                </div>
                            </div>

                            <div class="equipo-section">
                                <div class="equipo-nombre">{{ $p->nombre_visitante }}</div>
                                <div class="goles-label">Goleadores (N° camiseta)</div>
                                <div class="goles-lineas"></div>
                                <div class="goles-lineas"></div>
                                <div class="goles-lineas"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</body>
</html>
