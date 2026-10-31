@props(['titulo', 'actualizado', 'articulos' => [], 'resumen' => []])

{{-- Página legal "Reglamento": cabecera con líneas de cancha, pestañas entre los dos documentos,
    índice de artículos y resumen en tres puntos. Cada artículo usa x-legales.articulo con su ancla. --}}
@php
    $palabras = count(preg_split('/\s+/u', trim(strip_tags($slot)), -1, PREG_SPLIT_NO_EMPTY));
    $minutosLectura = max(1, (int) ceil($palabras / 200));
    $documentos = ['terminos' => 'Términos', 'privacidad' => 'Privacidad'];
@endphp

<x-app-layout>
    <div class="legal">
        <header class="legal-cabecera">
            <svg aria-hidden="true" class="legal-lineas" viewBox="0 0 620 340" fill="none">
                <rect x="20" y="20" width="700" height="300" rx="4" />
                <circle cx="320" cy="170" r="90" />
                <line x1="320" y1="20" x2="320" y2="320" />
                <rect x="560" y="90" width="120" height="160" />
            </svg>

            <div class="legal-cabecera-in">
                <span class="legal-sobre">Documentos del torneo</span>
                <h1 class="legal-titulo">{{ $titulo }}</h1>
                <p class="legal-meta">
                    <span>Última actualización: {{ $actualizado }}</span>
                    <span>{{ count($articulos) }} artículos</span>
                    <span>{{ $minutosLectura }} min de lectura</span>
                </p>

                <nav class="legal-pestanas" aria-label="Documentos legales">
                    @foreach ($documentos as $ruta => $nombre)
                        <a href="{{ route($ruta) }}"{!! request()->routeIs($ruta) ? ' aria-current="page"' : '' !!}>{{ $nombre }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <div class="legal-cuerpo">
            <details class="legal-indice-movil">
                <summary>Índice <span>{{ count($articulos) }} artículos</span></summary>
                <ol>
                    @foreach ($articulos as $ancla => $nombre)
                        <li><a href="#{{ $ancla }}"><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $nombre }}</a></li>
                    @endforeach
                </ol>
            </details>

            <nav class="legal-indice" aria-label="Artículos">
                <p>En esta página</p>
                <ol>
                    @foreach ($articulos as $ancla => $nombre)
                        <li><a href="#{{ $ancla }}"><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $nombre }}</a></li>
                    @endforeach
                </ol>
            </nav>

            <article class="legal-texto">
                @if ($resumen)
                    <dl class="legal-resumen">
                        @foreach ($resumen as $punto => $detalle)
                            <div>
                                <dt>{{ $punto }}</dt>
                                <dd>{{ $detalle }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                {{ $slot }}
            </article>
        </div>
    </div>
</x-app-layout>
