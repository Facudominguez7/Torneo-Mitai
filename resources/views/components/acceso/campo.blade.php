@props(['id', 'label', 'type' => 'text', 'errores' => [], 'revelable' => false])

{{-- Campo de las pantallas de acceso: label, input y errores. "revelable" suma el botón para mostrar la contraseña. --}}
@php
    $clases = 'h-12 w-full rounded-[10px] border border-white/20 bg-white/[0.06] px-3.5 text-base text-white placeholder:text-white/50 transition focus:border-brand-gold focus:outline-none focus:ring-2 focus:ring-brand-gold/30 aria-[invalid=true]:border-red-300';
    $accesibilidad = $errores ? ['aria-invalid' => 'true', 'aria-describedby' => $id.'-error'] : [];
@endphp

<div class="flex flex-col gap-2">
    <div class="flex items-baseline justify-between gap-3">
        <label for="{{ $id }}" class="text-[15px] font-semibold text-white/90">{{ $label }}</label>
        {{ $accion ?? '' }}
    </div>

    @if ($revelable)
        <div class="relative" x-data="{ visible: false }">
            <input id="{{ $id }}" type="password" x-bind:type="visible ? 'text' : 'password'"
                {{ $attributes->merge(['class' => $clases.' pr-14'] + $accesibilidad) }}>
            <button type="button" x-on:click="visible = ! visible" aria-label="Mostrar contraseña"
                x-bind:aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                x-bind:aria-pressed="visible.toString()"
                class="absolute right-0.5 top-0.5 flex h-11 w-11 items-center justify-center rounded-lg text-white/80 transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold">
                <svg x-show="! visible" aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
                <svg x-show="visible" x-cloak aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3l18 18" />
                    <path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6" />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
            </button>
        </div>
    @else
        <input id="{{ $id }}" type="{{ $type }}" {{ $attributes->merge(['class' => $clases] + $accesibilidad) }}>
    @endif

    @if ($errores)
        <ul id="{{ $id }}-error" class="flex flex-col gap-1 text-sm text-red-300">
            @foreach ((array) $errores as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
</div>
