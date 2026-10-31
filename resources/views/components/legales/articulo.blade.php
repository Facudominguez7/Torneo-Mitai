@aware(['articulos' => []])
@props(['ancla'])

{{-- Artículo de una página legal: toma su número y título del índice del documento. --}}
@php
    $numero = array_search($ancla, array_keys($articulos), true) + 1;
@endphp

<section id="{{ $ancla }}" class="legal-articulo">
    <h2><span>ART. {{ str_pad((string) $numero, 2, '0', STR_PAD_LEFT) }}</span>{{ $articulos[$ancla] }}</h2>
    {{ $slot }}
</section>
