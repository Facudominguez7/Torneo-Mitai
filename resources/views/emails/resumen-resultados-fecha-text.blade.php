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
    $urlDestino = url($resumen['url_fixture'] ?? '/fixture');
@endphp
{{ $tituloPrincipal }}

{{ $resumen['edicion_nombre'] ?? 'Edicion vigente' }} - {{ $resumen['fecha_nombre'] ?? 'Fecha' }}@if(!empty($resumen['categoria_nombre'])) - Categoria {{ $resumen['categoria_nombre'] }}@endif

{{ $mensajePrincipal }}

@foreach($partidos as $partido)
- {{ $partido['equipo_local'] ?? 'Local' }} {{ $partido['goles_local'] ?? 0 }} - {{ $partido['goles_visitante'] ?? 0 }} {{ $partido['equipo_visitante'] ?? 'Visitante' }}@php
    $metadatos = array_filter([
        $partido['grupo'] ?? null,
        $partido['cancha'] ?? null,
        $partido['horario'] ?? null,
    ]);
@endphp@if($metadatos !== []) ({{ implode(' | ', $metadatos) }})@endif
@endforeach

Mas informacion: {{ $urlDestino }}

Si no queres seguir recibiendo estos correos, podes administrar tu baja aqui:
{{ $urlBajaGeneral }}
