@props(['tipo' => 'info'])

{{-- Aviso de las pantallas de acceso: "info" (sesión expirada), "exito" (código o enlace enviado), "error". --}}
@php
    $clases = match ($tipo) {
        'exito' => 'border-brand-teal bg-brand-teal-light/25 text-brand-navy-deep',
        'error' => 'border-red-700 bg-red-50 text-red-800',
        default => 'border-brand-navy bg-brand-gold/40 text-brand-navy-deep',
    };
@endphp

<div role="{{ $tipo === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-2.5 border-l-4 px-3.5 py-3 text-[15px] font-medium '.$clases]) }}>
    <svg aria-hidden="true" class="mt-0.5 h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        @if ($tipo === 'exito')
            <path d="M20 6 9 17l-5-5" />
        @elseif ($tipo === 'error')
            <circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" />
        @else
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
        @endif
    </svg>
    <div class="flex flex-col gap-1">{{ $slot }}</div>
</div>
