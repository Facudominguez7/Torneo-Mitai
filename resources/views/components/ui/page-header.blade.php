{{--
    x-ui.page-header — page title block (issue #110, Phase 1, T1.5).

    HONEST NOTE: unlike the other ui.* components, there is no existing
    shared pattern to extract here. The codebase has 50 raw <h1> tags with
    inconsistent styling (`text-3xl font-bold text-white` on the portal vs
    `text-2xl font-bold text-gray-800` on the Panel vs unstyled elsewhere),
    and zero Panel/** views use a header component — only dashboard.blade.php
    uses Breeze's <x-slot name="header">. This component CREATES the
    convention rather than extracting one, kept deliberately minimal: a
    title, an optional subtitle, an optional actions slot. See
    odd/tasks/design-system-tokens.md for the audit.
--}}
@props([
    'title',
    'subtitle' => null,
    // panel | portal — which surface this header is drawn on.
    'tone' => 'panel',
])

@php
    $toneClasses = [
        'panel' => [
            'title' => 'text-2xl font-bold text-gray-800',
            'subtitle' => 'text-sm text-gray-500',
        ],
        'portal' => [
            'title' => 'text-3xl font-bold text-white',
            'subtitle' => 'text-sm text-white/60',
        ],
    ];

    // Unknown tone falls back to panel instead of rendering unstyled — see
    // the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $toneClasses) ? $tone : 'panel';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        <h1 class="{{ $toneClasses[$resolvedTone]['title'] }}">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 {{ $toneClasses[$resolvedTone]['subtitle'] }}">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
