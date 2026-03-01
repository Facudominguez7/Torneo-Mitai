<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicado Torneos Mita'i</title>
</head>
@php
    $tituloPrincipal = trim((string) ($resumen['titulo_personalizado'] ?? ''));
    if ($tituloPrincipal === '') {
        $tituloPrincipal = 'Resumen de resultados';
    }

    $mensajePrincipal = trim((string) ($resumen['mensaje_personalizado'] ?? ''));
    if ($mensajePrincipal === '') {
        $mensajePrincipal = 'Estos son los resultados cargados hasta el momento:';
    }

    $partidos = $resumen['partidos'] ?? [];
    $logoUrl = url('/fotos/Logo_Mitai_SinFondo.png');
    $urlDestino = url($resumen['url_fixture'] ?? '/fixture');
    $textoBoton = $partidos !== [] ? 'Ver fixture completo' : 'Ver más información';
@endphp
<body style="margin:0;padding:0;background:#020617;font-family:Arial,Helvetica,sans-serif;color:#e2e8f0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;background:#020617;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background:#0b1220;border-radius:18px;border:1px solid #1e293b;overflow:hidden;">
                    <tr>
                        <td style="padding:0;background:linear-gradient(135deg,#020617 0%,#0f172a 55%,#111827 100%);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:24px 24px 10px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align:middle;padding-right:12px;">
                                                    <img src="{{ $logoUrl }}" alt="Logo Torneo Mita'i" width="58" height="58" style="display:block;width:58px;height:58px;object-fit:contain;">
                                                </td>
                                                <td style="vertical-align:middle;">
                                                    <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#facc15;font-weight:700;">Torneo Mita'i</p>
                                                    <p style="margin:0;font-size:13px;line-height:1.4;color:#94a3b8;">Novedades oficiales de cada jornada</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 24px 22px;">
                                        <h1 style="margin:0;font-size:28px;line-height:1.2;color:#f8fafc;">{{ $tituloPrincipal }}</h1>
                                        <p style="margin:10px 0 0;font-size:13px;color:#cbd5e1;">
                                            {{ $resumen['edicion_nombre'] ?? 'Edición vigente' }} · {{ $resumen['fecha_nombre'] ?? 'Fecha' }}
                                            @if(!empty($resumen['categoria_nombre']))
                                                · Categoría {{ $resumen['categoria_nombre'] }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 24px 24px;background:#0b1220;">
                            <div style="border:1px solid #1f2937;background:#111827;border-radius:12px;padding:14px 16px;margin-bottom:16px;">
                                <p style="margin:0;font-size:14px;line-height:1.5;color:#e2e8f0;">{{ $mensajePrincipal }}</p>
                            </div>

                            @if($partidos !== [])
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                    @foreach($partidos as $partido)
                                        @php
                                            $metadatos = array_filter([
                                                $partido['grupo'] ?? null,
                                                $partido['cancha'] ?? null,
                                                $partido['horario'] ?? null,
                                            ]);
                                        @endphp
                                        <tr>
                                            <td style="padding:14px 0;border-bottom:1px solid #1f2937;font-size:14px;line-height:1.5;color:#cbd5e1;">
                                                <strong style="color:#f8fafc;">{{ $partido['equipo_local'] ?? 'Local' }}</strong>
                                                <span style="display:inline-block;min-width:52px;text-align:center;font-weight:700;color:#facc15;">{{ $partido['goles_local'] ?? 0 }} - {{ $partido['goles_visitante'] ?? 0 }}</span>
                                                <strong style="color:#f8fafc;">{{ $partido['equipo_visitante'] ?? 'Visitante' }}</strong>

                                                @if($metadatos !== [])
                                                    <div style="margin-top:5px;font-size:12px;color:#94a3b8;">
                                                        {{ implode(' · ', $metadatos) }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <div style="margin-top:20px;text-align:center;">
                                <a href="{{ $urlDestino }}" style="display:inline-block;background:#facc15;color:#111827;text-decoration:none;padding:11px 20px;border-radius:999px;font-weight:700;font-size:14px;">{{ $textoBoton }}</a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px;background:#0f172a;border-top:1px solid #1e293b;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                                Si no querés seguir recibiendo estos correos, podés administrar tu baja desde este enlace:
                                <a href="{{ $urlBajaGeneral }}" style="color:#facc15;text-decoration:none;font-weight:700;">Gestionar suscripción</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
