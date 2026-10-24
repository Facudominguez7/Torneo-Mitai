{{--
    x-ui.filter-chip — toggle-style filter pill (issue #110, Phase 1, T1.7).

    Extracted from the real $clasesChip closure at
    resources/views/layouts/fixture.blade.php:71-77, reused at lines 102,
    115 and 132 for the date/category filter bars. The portal-tone classes
    below are that closure verbatim (in terms of resolved brand tokens).

    The panel tone has no direct one-to-one precedent: the Panel's
    equivalent UI (Panel/instancia_final/index.blade.php:51,87) is a
    <select>-based filter group, not a toggle chip, so there is nothing to
    extract 1:1 there. The panel-tone treatment below follows the same
    tone-split convention established by x-ui.button (flat solid Panel
    look vs. bordered/gradient portal pill) rather than inventing an
    unrelated shape.
--}}
@props([
    'active' => false,
    // panel | portal — which surface this chip is drawn on.
    'tone' => 'panel',
    'href' => null,
])

@php
    $toneClasses = [
        'panel' => $active
            ? 'bg-brand-navy text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
        'portal' => $active
            ? 'border border-brand-gold bg-brand-gold/20 text-brand-gold'
            : 'border border-white/20 bg-white/5 text-white/70 hover:border-brand-gold/40 hover:bg-brand-gold/10 hover:text-white',
    ];

    // Unknown tone falls back to panel instead of rendering unstyled — see
    // the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $toneClasses) ? $tone : 'panel';

    $classes = collect([
        'inline-flex min-w-max items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40',
        $toneClasses[$resolvedTone],
    ])->implode(' ');
@endphp

@if (filled($href))
    {{-- href="{{ }}" llega ya escapado: e($href, false) evita el doble escape, igual que x-ui.button. --}}
    <a href="{!! e($href, false) !!}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >{{ $slot }}</a>
@else
    <button
        {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}
        @if ($active) aria-current="page" @endif
    >{{ $slot }}</button>
@endif
