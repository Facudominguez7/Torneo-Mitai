Próxima fecha

{!! $proximaFecha['edicion_nombre'] ?? 'Edición vigente' !!} - {!! $proximaFecha['fecha_nombre'] ?? 'Fecha' !!}@if(!empty($proximaFecha['categoria_nombre'])) - Categoría {!! $proximaFecha['categoria_nombre'] !!}@endif


Estos son los partidos programados:

@foreach($proximaFecha['partidos'] ?? [] as $partido)
@php($metadatos = array_filter([$partido['horario'] ?? null, $partido['cancha'] ?? null, $partido['grupo'] ?? null]))
- {!! $partido['equipo_local'] ?? 'Local' !!} vs {!! $partido['equipo_visitante'] ?? 'Visitante' !!}@if($metadatos !== []) ({!! implode(' | ', $metadatos) !!})@endif

@endforeach

Fixture completo: {{ url($proximaFecha['url_fixture'] ?? '/fixture') }}

Si no quieres seguir recibiendo estos correos, puedes administrar tu suscripción aquí:
{{ $urlPreferencias }}
