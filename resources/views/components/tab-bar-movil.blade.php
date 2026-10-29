@props(['edicion' => null])

@php
    $parametrosEdicion = $edicion?->id ? ['idEdicion' => $edicion->id] : [];

    $pestanas = [
        'inicio' => [
            'etiqueta' => 'Inicio',
            'ruta' => 'home',
            'activa' => request()->routeIs('home'),
            'icono' => '<path d="M3.5 9.5L10 3.5l6.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 8.5V16.5h9V8.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        ],
        'partidos' => [
            'etiqueta' => 'Partidos',
            'ruta' => 'fixture',
            'activa' => request()->routeIs('fixture'),
            'icono' => '<rect x="3" y="4" width="14" height="13" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 8.5h14M7 2.5v3M13 2.5v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        ],
        'posiciones' => [
            'etiqueta' => 'Posiciones',
            'ruta' => 'tabla-posiciones',
            'activa' => request()->routeIs('tabla-posiciones'),
            'icono' => '<path d="M3 17V9M10 17V4M17 17v-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        ],
        'goleadores' => [
            'etiqueta' => 'Goleadores',
            'ruta' => 'goleadores',
            'activa' => request()->routeIs('goleadores') || request()->routeIs('tabla-goleadores'),
            'icono' => '<path d="M10 3l1.6 3.3 3.6.5-2.6 2.6.6 3.6L10 11.2 6.8 13l.6-3.6L4.8 6.8l3.6-.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        ],
        'finales' => [
            'etiqueta' => 'Finales',
            'ruta' => 'instancias-finales',
            'activa' => request()->routeIs('instancias-finales'),
            'icono' => '<path d="M6 3h8v4a4 4 0 01-8 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M6 4H3.5a2.5 2.5 0 002.5 3M14 4h2.5a2.5 2.5 0 01-2.5 3M10 11v3M7 16.5h6M8 14h4v2.5H8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
    ];
@endphp

<nav class="tabbar-movil sticky bottom-0 z-30 flex items-center justify-around border-t border-tema-linea/15 bg-tema-fondo pb-[calc(10px+env(safe-area-inset-bottom))] pt-2.5 sm:hidden"
    aria-label="Navegación inferior">
    @foreach ($pestanas as $pestana)
        <a href="{{ route($pestana['ruta'], $pestana['ruta'] === 'home' ? [] : $parametrosEdicion) }}"
            @if ($pestana['activa']) aria-current="page" @endif>
            <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">{!! $pestana['icono'] !!}</svg>
            {{ $pestana['etiqueta'] }}
        </a>
    @endforeach
</nav>
