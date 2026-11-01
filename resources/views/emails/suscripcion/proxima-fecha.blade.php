@php
    $partidos = $proximaFecha['partidos'] ?? [];
    $subtitulo = collect([
        $proximaFecha['edicion_nombre'] ?? 'Edición vigente',
        ! empty($proximaFecha['categoria_nombre']) ? 'Categoría '.$proximaFecha['categoria_nombre'] : null,
    ])->filter()->implode(' · ');
    $font = "font-family:Barlow,'Segoe UI',Arial,Helvetica,sans-serif;";
@endphp
<x-correo.marco
    etiqueta="Próxima fecha"
    :titulo="$proximaFecha['fecha_nombre'] ?? 'Fecha'"
    :subtitulo="$subtitulo"
    :url-boton="url($proximaFecha['url_fixture'] ?? '/fixture')"
    texto-boton="Ver el fixture"
    :url-preferencias="$urlPreferencias"
>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3E6170;{{ $font }}">Días, horarios y canchas de los partidos programados.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach ($partidos as $partido)
            @php
                // "Sáb 07/11 10:30" se parte en día y hora para la columna de horario.
                $horario = (string) ($partido['horario'] ?? '');
                $hora = str_contains($horario, ' ') ? substr($horario, strrpos($horario, ' ') + 1) : 'A conf.';
                $dia = str_contains($horario, ' ') ? substr($horario, 0, strrpos($horario, ' ')) : '';
                $metadatos = array_filter([$partido['cancha'] ?? null, $partido['grupo'] ?? null]);
            @endphp
            <tr>
                <td style="padding:0 0 10px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EEF3F5;border:1px solid #DCE5E8;border-radius:14px;">
                        <tr>
                            <td width="92" style="padding:14px 0 14px 16px;vertical-align:middle;{{ $font }}">
                                <div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#2A6D81;font-weight:700;">{{ $dia }}</div>
                                <div style="font-size:22px;font-weight:700;color:#082F3B;white-space:nowrap;">{{ $hora }}</div>
                            </td>
                            <td style="padding:12px 16px;border-left:1px solid #DCE5E8;vertical-align:middle;{{ $font }}">
                                <table role="presentation" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="26" style="padding:2px 8px 2px 0;vertical-align:middle;"><img src="{{ $partido['escudo_local'] ?? url('fotos/Logo_Mitai_SinFondo.png') }}" alt="{{ $partido['equipo_local'] ?? 'Local' }}" width="26" height="26" style="display:block;width:26px;height:26px;border:0;border-radius:6px;background-color:#FFFFFF;"></td>
                                        <td style="padding:2px 0;vertical-align:middle;font-size:15px;font-weight:600;color:#082F3B;{{ $font }}">{{ $partido['equipo_local'] ?? 'Local' }}</td>
                                    </tr>
                                    <tr>
                                        <td width="26" style="padding:2px 8px 2px 0;vertical-align:middle;"><img src="{{ $partido['escudo_visitante'] ?? url('fotos/Logo_Mitai_SinFondo.png') }}" alt="{{ $partido['equipo_visitante'] ?? 'Visitante' }}" width="26" height="26" style="display:block;width:26px;height:26px;border:0;border-radius:6px;background-color:#FFFFFF;"></td>
                                        <td style="padding:2px 0;vertical-align:middle;font-size:15px;font-weight:600;color:#082F3B;{{ $font }}">{{ $partido['equipo_visitante'] ?? 'Visitante' }}</td>
                                    </tr>
                                </table>
                                @if ($metadatos !== [])
                                    <div style="margin-top:6px;font-size:12px;color:#5B7884;{{ $font }}">{{ implode(' · ', $metadatos) }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
    </table>
</x-correo.marco>
