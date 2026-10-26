@props(['variante' => 'principal'])

@php
    $clases = $variante === 'secundario'
        ? 'border border-white/25 text-white hover:border-white/50 hover:bg-white/5'
        : 'bg-brand-gold text-brand-navy-deep hover:bg-brand-gold/90';
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-[50px] w-full items-center justify-center gap-2 rounded-[10px] px-6 text-[17px] font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold disabled:cursor-wait disabled:opacity-60 '.$clases]) }}>
    {{ $slot }}
</button>
