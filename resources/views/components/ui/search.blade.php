{{--
    x-ui.search — labeled search input (issue #110, Phase 1, T1.4).

    Only ~10 occurrences exist and they are stylistically split: the Panel
    uses plain gray inputs (Panel/equipo/index.blade.php,
    Panel/tabla_goleador/index.blade.php), the portal uses pill-shaped
    dark-glass inputs (layouts/fixture.blade.php,
    layouts/tabla-vallas.blade.php). The `tone` prop is exactly for this
    split — see x-ui.button and odd/tasks/design-system-tokens.md.

    Accessibility (issue #103): a search input must always have an
    accessible name. This component enforces that in priority order:
    1. `label` — a visible <label>, or a visually-hidden one when
       `labelHidden` is true (matches the layouts/tabla-vallas.blade.php
       pattern of a purely icon-driven search with a hidden label).
    2. No `label` — falls back to `aria-label` built from `placeholder`,
       so the control is never left unlabeled.
--}}
@props([
    'label' => null,
    'labelHidden' => false,
    'name' => null,
    'id' => null,
    // panel | portal — which surface this search input is drawn on.
    'tone' => 'panel',
    'placeholder' => null,
    'value' => null,
])

@php
    $toneClasses = [
        'panel' => 'rounded-lg border-gray-300 bg-white text-gray-700 placeholder:text-gray-400 focus:border-brand-navy focus:ring-brand-navy/30',
        'portal' => 'rounded-full border-white/20 bg-white/5 text-white placeholder:text-white/40 focus:border-brand-gold/60 focus:ring-brand-gold/40',
    ];

    // Unknown tone falls back to panel instead of rendering unstyled — see
    // the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $toneClasses) ? $tone : 'panel';

    $classes = collect([
        'block w-full px-4 py-2 text-sm shadow-sm',
        'focus:outline-none focus:ring-2 focus-visible:ring-2',
        $toneClasses[$resolvedTone],
    ])->implode(' ');

    $resolvedId = $id ?? $name;
    $labelClasses = $resolvedTone === 'portal' ? 'text-white' : 'text-gray-700';
@endphp

<div>
    @if ($label && ! $labelHidden)
        <label for="{{ $resolvedId }}" class="mb-1 block text-sm font-medium {{ $labelClasses }}">
            {{ $label }}
        </label>
    @elseif ($label && $labelHidden)
        <label for="{{ $resolvedId }}" class="sr-only">{{ $label }}</label>
    @endif

    <input
        type="search"
        @if ($resolvedId) id="{{ $resolvedId }}" @endif
        @if ($name) name="{{ $name }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($value !== null) value="{{ $value }}" @endif
        @unless ($label) aria-label="{{ $placeholder ?? 'Buscar' }}" @endunless
        {{ $attributes->merge(['class' => $classes]) }}
    />
</div>
