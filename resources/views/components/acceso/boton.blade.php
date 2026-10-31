@props(['variante' => 'principal'])

@php
    $clases = $variante === 'secundario'
        ? 'border border-brand-navy/30 bg-white text-brand-navy-deep hover:border-brand-navy'
        : 'acceso-boton bg-brand-navy text-white hover:bg-brand-teal';
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-14 w-full items-center justify-center gap-2 rounded px-6 text-[17px] font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-navy disabled:cursor-wait disabled:opacity-60 '.$clases]) }}>
    {{ $slot }}
</button>
