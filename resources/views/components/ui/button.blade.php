{{--
    x-ui.button — base contract for the resources/views/components/ui/*
    component family (issue #110, Phase 1, T1.1).

    This is the reference implementation the remaining ui.* components
    (badge, select, search, page-header, empty-state, filter-chip) should
    copy: @props for the component's own inputs, an attribute-bag merge so
    callers keep full control of class/data-*/aria-*/wire:* attributes, and
    a `tone` prop that switches surface treatment between the portal's dark
    glassmorphism look and the Panel's light Flowbite look (see the README
    in this directory for the evidence behind that decision).
--}}
@props([
    // primary | secondary | danger | success
    'variant' => 'primary',
    // sm | md | lg
    'size' => 'md',
    // panel | portal — which surface this button is drawn on.
    'tone' => 'panel',
    // When present, the component renders as <a href="..."> instead of
    // <button>. Ignored when `disabled` is also true (see below).
    'href' => null,
    'disabled' => false,
])

@php
    // Variant colors are resolved per tone because the two sides of the app
    // use genuinely different surface treatments for the same semantic
    // action (see the tone decision below). All colors come from either the
    // Phase 0 brand tokens (brand-navy/brand-teal/brand-gold, alpha-capable)
    // or Tailwind's default palette (red/emerald) — never a hardcoded hex.
    $variantsByTone = [
        'panel' => [
            'primary' => 'bg-brand-navy text-white hover:bg-brand-navy/90 focus-visible:ring-brand-navy/50',
            'secondary' => 'bg-brand-teal text-white hover:bg-brand-teal/90 focus-visible:ring-brand-teal/50',
            'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500/50',
            'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500/50',
        ],
        'portal' => [
            'primary' => 'border border-brand-gold/40 bg-gradient-to-r from-brand-navy/90 via-brand-teal/85 to-brand-navy/85 text-white hover:brightness-110 focus-visible:ring-brand-gold/40',
            'secondary' => 'border border-white/20 bg-white/10 text-white hover:bg-white/15 focus-visible:ring-white/30',
            'danger' => 'border border-red-300/40 bg-red-500/20 text-white hover:bg-red-500/30 focus-visible:ring-red-300/40',
            'success' => 'border border-emerald-300/40 bg-gradient-to-r from-emerald-800/90 via-emerald-700/85 to-emerald-800/85 text-white hover:brightness-110 focus-visible:ring-emerald-300/40',
        ],
    ];

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $shapeByTone = [
        'panel' => 'rounded-lg',
        'portal' => 'rounded-full',
    ];

    // Unknown tone/variant/size fall back to a safe default instead of
    // emitting no styling at all — a silently unstyled button is a worse
    // failure mode than a wrong-but-visible one.
    $resolvedTone = array_key_exists($tone, $variantsByTone) ? $tone : 'panel';
    $resolvedVariant = $variantsByTone[$resolvedTone][$variant] ?? $variantsByTone[$resolvedTone]['primary'];
    $resolvedSize = $sizeClasses[$size] ?? $sizeClasses['md'];
    $resolvedShape = $shapeByTone[$resolvedTone];

    $classes = collect([
        'inline-flex items-center justify-center gap-2 font-semibold transition',
        'focus:outline-none focus-visible:ring-2',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        $resolvedShape,
        $resolvedSize,
        $resolvedVariant,
    ])->implode(' ');

    // A disabled <a> is not a real disabled control: removing href leaves a
    // link-shaped element with broken semantics, and keeping href with
    // aria-disabled/tabindex tricks still lets some browsers/assistive tech
    // activate it — a trap that looks disabled but isn't. When both `href`
    // and `disabled` are given, disabled wins and a real <button disabled>
    // is rendered instead, dropping the href entirely.
    $isLink = filled($href) && ! $disabled;
@endphp

@if ($isLink)
    {{-- href="{{ }}" on a component arrives already escaped; e($href, false)
         escapes without double-encoding, so "&" survives either way. --}}
    <a href="{!! e($href, false) !!}"
        {{ $attributes->merge(['class' => $classes]) }}
    >{{ $slot }}</a>
@else
    <button
        {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}
        @disabled($disabled)
    >{{ $slot }}</button>
@endif
