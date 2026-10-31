<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ \App\Mail\AvisoMigracionSuscriptoresMail::ASUNTO }}</title>
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
    <style>
        /* Evitar que clientes de email sobreescriban colores */
        :root { color-scheme: light dark; }
        [data-ogsc] body, [data-ogsb] body { background-color: #020617 !important; }
        u + .body { /* Gmail fix */ }
    </style>
</head>
@php
    $logoUrl = url('/fotos/Logo_Mitai_SinFondo.png');

    // Font stack reutilizable para inline styles
    $font = "font-family:Arial,Helvetica,sans-serif;";
@endphp
<body class="body" style="margin:0;padding:0;background-color:#020617;{{ $font }}color:#e2e8f0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;background-color:#020617;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellpadding="0" cellspacing="0" style="max-width:680px;width:100%;background-color:#0b1220;border-radius:18px;border:1px solid #1e293b;overflow:hidden;">
                    {{-- Header con logo y titulo --}}
                    <tr>
                        <td style="padding:0;background-color:#0f172a;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:24px 24px 10px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align:middle;padding-right:12px;" width="58">
                                                    <img src="{{ $logoUrl }}" alt="Logo Torneo Mita'i" width="58" height="58" style="display:block;width:58px;height:58px;border:0;outline:none;">
                                                </td>
                                                <td style="vertical-align:middle;">
                                                    <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#facc15;font-weight:700;{{ $font }}">Torneo Mita'i</p>
                                                    <p style="margin:0;font-size:13px;line-height:1.4;color:#94a3b8;{{ $font }}">Novedades oficiales de cada jornada</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 24px 22px;">
                                        <h1 style="margin:0;font-size:26px;line-height:1.2;color:#f8fafc;font-weight:700;font-style:normal;{{ $font }}">Los avisos ahora llegan desde una lista nueva</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:22px 24px 26px;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#cbd5e1;{{ $font }}">
                                Desde ahora, los avisos de Torneos Mita'í (el resumen de cada fecha, la próxima fecha y las novedades del torneo) llegan desde una lista de suscriptores nueva.
                            </p>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#cbd5e1;{{ $font }}">
                                Te sumamos porque habías aceptado recibir correos con tu cuenta. No tenés que hacer nada para seguir recibiéndolos.
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#cbd5e1;{{ $font }}">
                                Podés elegir qué avisos recibir o darte de baja cuando quieras.
                            </p>

                            {{-- Boton CTA --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background-color:#facc15;border-radius:999px;">
                                                    <a href="{{ $urlPreferencias }}" target="_blank" style="display:inline-block;background-color:#facc15;color:#111827;text-decoration:none;padding:11px 20px;border-radius:999px;font-weight:700;font-size:14px;{{ $font }}">Elegir mis avisos</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer con enlace de baja --}}
                    <tr>
                        <td style="padding:16px 24px;background-color:#0f172a;border-top:1px solid #1e293b;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;{{ $font }}">
                                Si no quer&eacute;s seguir recibiendo estos correos, pod&eacute;s administrar tu baja desde este enlace:
                                <a href="{{ $urlPreferencias }}" target="_blank" style="color:#facc15;text-decoration:none;font-weight:700;{{ $font }}">Gestionar suscripci&oacute;n</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
