@props([
    'etiqueta',
    'titulo',
    'subtitulo' => null,
    'urlBoton' => null,
    'textoBoton' => null,
    'urlPreferencias',
    'motivo' => "Recibes este correo porque elegiste estos avisos en Torneos Mita'í.",
])
@php
    // Marco común de los correos a suscriptores: identidad "Cartelera clara" de la home (tema claro).
    $font = "font-family:Barlow,'Segoe UI',Arial,Helvetica,sans-serif;";
    $logoUrl = url('fotos/Logo_Mitai_SinFondo.png');
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $titulo }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table { border-collapse: collapse; }
        td { font-family: Arial, Helvetica, sans-serif; }
    </style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#F3F7F8;{{ $font }}color:#082F3B;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F3F7F8;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#FFFFFF;border:1px solid #DCE5E8;border-radius:20px;">
                    {{-- Marca --}}
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="44" style="padding-right:12px;vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" width="44" height="44" alt="Torneos Mita'í" style="display:block;width:44px;height:44px;border:0;outline:none;">
                                    </td>
                                    <td style="vertical-align:middle;{{ $font }}">
                                        <div style="font-size:11px;letter-spacing:.35em;text-transform:uppercase;color:#6B8792;font-weight:600;">Torneo Mitai</div>
                                        <div style="font-size:16px;font-weight:700;color:#082F3B;">Torneos Mita'í</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Encabezado --}}
                    <tr>
                        <td style="padding:26px 32px 0;{{ $font }}">
                            <div style="font-size:11px;letter-spacing:.3em;text-transform:uppercase;color:#2A6D81;font-weight:700;">{{ $etiqueta }}</div>
                            <h1 style="margin:6px 0 0;font-size:32px;line-height:1.15;font-weight:700;color:#082F3B;{{ $font }}">{{ $titulo }}</h1>
                            <div style="margin-top:12px;width:44px;height:3px;line-height:3px;font-size:0;border-radius:3px;background-color:#C9B53A;">&nbsp;</div>
                            @if ($subtitulo)
                                <div style="margin-top:10px;font-size:14px;color:#3E6170;">{{ $subtitulo }}</div>
                            @endif
                        </td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td style="padding:24px 32px 8px;{{ $font }}">
                            {{ $slot }}
                        </td>
                    </tr>

                    @if ($urlBoton && $textoBoton)
                        <tr>
                            <td align="center" style="padding:16px 32px 30px;">
                                <a href="{{ $urlBoton }}" target="_blank" style="display:inline-block;background-color:#0E4B5E;background-image:linear-gradient(90deg,#0E4B5E,#2A6D81);color:#FFFFFF;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px;{{ $font }}">{{ $textoBoton }} &rarr;</a>
                            </td>
                        </tr>
                    @else
                        <tr><td style="padding:0 0 22px;font-size:0;line-height:0;">&nbsp;</td></tr>
                    @endif

                    {{-- Pie con las preferencias --}}
                    <tr>
                        <td style="padding:18px 32px;border-top:1px solid #DCE5E8;font-size:12px;line-height:1.6;color:#5B7884;{{ $font }}">
                            {{ $motivo }}
                            <a href="{{ $urlPreferencias }}" target="_blank" style="color:#0E4B5E;font-weight:700;text-decoration:none;">Elegir mis avisos o darme de baja</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
