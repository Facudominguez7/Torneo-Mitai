@php
    $partidos = $resumen['partidos'] ?? [];
    $titulo = trim((string) ($resumen['titulo_personalizado'] ?? '')) ?: ($resumen['fecha_nombre'] ?? 'Fecha');
    $mensaje = trim((string) ($resumen['mensaje_personalizado'] ?? '')) ?: 'Estos son los resultados cargados de la fecha.';
    $subtitulo = collect([
        $resumen['edicion_nombre'] ?? 'Edición vigente',
        ! empty($resumen['categoria_nombre']) ? 'Categoría '.$resumen['categoria_nombre'] : null,
    ])->filter()->implode(' · ');
    $font = "font-family:Barlow,'Segoe UI',Arial,Helvetica,sans-serif;";
@endphp
<x-correo.marco
    etiqueta="Resumen de la fecha"
    :titulo="$titulo"
    :subtitulo="$subtitulo"
    :url-boton="url($resumen['url_fixture'] ?? '/fixture')"
    texto-boton="Ver el fixture"
    :url-preferencias="$urlBajaGeneral"
>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3E6170;{{ $font }}">{{ $mensaje }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach ($partidos as $partido)
            @php
                $metadatos = array_filter([$partido['grupo'] ?? null, $partido['cancha'] ?? null, $partido['horario'] ?? null]);
            @endphp
            <tr>
                <td style="padding:0 0 10px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EEF3F5;border:1px solid #DCE5E8;border-radius:14px;">
                        <tr>
                            <td width="40%" style="padding:14px 0 14px 14px;vertical-align:middle;{{ $font }}">
                                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                    <td width="30" style="padding-right:8px;vertical-align:middle;"><img src="{{ $partido['escudo_local'] ?? url('fotos/Logo_Mitai_SinFondo.png') }}" alt="{{ $partido['equipo_local'] ?? 'Local' }}" width="30" height="30" style="display:block;width:30px;height:30px;border:0;border-radius:6px;background-color:#FFFFFF;"></td>
                                    <td style="vertical-align:middle;font-size:15px;font-weight:600;color:#082F3B;{{ $font }}">{{ $partido['equipo_local'] ?? 'Local' }}</td>
                                </tr></table>
                            </td>
                            <td width="20%" align="center" style="padding:14px 4px;vertical-align:middle;">
                                <span style="display:inline-block;background-color:#082F3B;color:#F7F280;padding:5px 12px;border-radius:10px;font-size:17px;font-weight:700;letter-spacing:.04em;white-space:nowrap;{{ $font }}">{{ $partido['goles_local'] ?? 0 }} – {{ $partido['goles_visitante'] ?? 0 }}</span>
                            </td>
                            <td width="40%" align="right" style="padding:14px 14px 14px 0;vertical-align:middle;{{ $font }}">
                                <table role="presentation" cellpadding="0" cellspacing="0" align="right"><tr>
                                    <td align="right" style="vertical-align:middle;font-size:15px;font-weight:600;color:#082F3B;{{ $font }}">{{ $partido['equipo_visitante'] ?? 'Visitante' }}</td>
                                    <td width="30" style="padding-left:8px;vertical-align:middle;"><img src="{{ $partido['escudo_visitante'] ?? url('fotos/Logo_Mitai_SinFondo.png') }}" alt="{{ $partido['equipo_visitante'] ?? 'Visitante' }}" width="30" height="30" style="display:block;width:30px;height:30px;border:0;border-radius:6px;background-color:#FFFFFF;"></td>
                                </tr></table>
                            </td>
                        </tr>
                        @if ($metadatos !== [])
                            <tr>
                                <td colspan="3" style="padding:0 14px 12px;font-size:12px;color:#5B7884;{{ $font }}">{{ implode(' · ', $metadatos) }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        @endforeach
    </table>
</x-correo.marco>
