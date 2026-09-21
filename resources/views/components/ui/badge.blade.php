{{--
    x-ui.badge — status/label pill (issue #110, Phase 1, T1.2).

    Extracted from the repeated hand-typed `rounded-full ... text-xs
    font-semibold` pattern used for status pills across the Panel
    (Panel/jugador/consulta.blade.php, Panel/admin.blade.php,
    Panel/suscriptores-email.blade.php) and, on the portal side, the
    match-status span at layouts/instancias-finales.blade.php:185.

    A badge is a non-interactive status indicator, always a <span> — never
    a control, so it carries no focus-visible state (see x-ui.button for
    the interactive counterpart).
--}}
@props([
    // neutral | success | warning | danger | info
    'variant' => 'neutral',
    // panel | portal — which surface this badge is drawn on.
    'tone' => 'panel',
])

@php
    $variantsByTone = [
        'panel' => [
            'neutral' => 'bg-gray-100 text-gray-700',
            'success' => 'bg-green-100 text-green-700',
            'warning' => 'bg-yellow-100 text-yellow-700',
            'danger' => 'bg-red-100 text-red-700',
            'info' => 'bg-blue-100 text-blue-700',
        ],
        'portal' => [
            'neutral' => 'border border-white/20 bg-white/10 text-white/70',
            'success' => 'border border-emerald-400/30 bg-emerald-500/15 text-emerald-200',
            'warning' => 'border border-amber-300/30 bg-amber-400/15 text-amber-200',
            'danger' => 'border border-rose-400/30 bg-rose-500/15 text-rose-200',
            'info' => 'border border-brand-gold/40 bg-brand-gold/15 text-brand-gold',
        ],
    ];

    // Unknown tone/variant falls back to a safe default instead of
    // rendering unstyled — see the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $variantsByTone) ? $tone : 'panel';
    $resolvedVariant = $variantsByTone[$resolvedTone][$variant] ?? $variantsByTone[$resolvedTone]['neutral'];

    $classes = collect([
        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
        $resolvedVariant,
    ])->implode(' ');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
