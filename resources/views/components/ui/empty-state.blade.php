{{--
    x-ui.empty-state — "no results" placeholder (issue #110, Phase 1, T1.6).

    HONEST NOTE: same as x-ui.page-header, there is no existing shared
    pattern here. ~15 scattered "no results" messages exist, each a bare
    <p> with different classes (Panel/estadisticas-jugadores/index.blade.php,
    Panel/suscriptores-email.blade.php, Panel/institucion/index.blade.php).
    This component CREATES the convention, modeled on the closest existing
    example (Panel/suscriptores-email.blade.php:182 — a centered icon,
    dashed border, muted message): a message, an optional description, an
    optional icon (default provided, overridable via the icon slot) and an
    optional actions slot. Kept deliberately simple.
--}}
@props([
    'message',
    'description' => null,
    // panel | portal — which surface this empty state is drawn on.
    'tone' => 'panel',
])

@php
    $toneClasses = [
        'panel' => [
            'border' => 'border-gray-200',
            'icon' => 'text-gray-300',
            'message' => 'text-gray-500',
            'description' => 'text-gray-400',
        ],
        'portal' => [
            'border' => 'border-white/20',
            'icon' => 'text-white/30',
            'message' => 'text-white/60',
            'description' => 'text-white/40',
        ],
    ];

    // Unknown tone falls back to panel instead of rendering unstyled — see
    // the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $toneClasses) ? $tone : 'panel';
    $t = $toneClasses[$resolvedTone];
@endphp

<div {{ $attributes->merge(['class' => "text-center py-12 px-4 rounded-lg border-2 border-dashed {$t['border']}"]) }}>
    <div class="mx-auto mb-4 h-12 w-12 {{ $t['icon'] }}">
        @isset($icon)
            {{ $icon }}
        @else
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        @endisset
    </div>

    <p class="text-lg font-medium {{ $t['message'] }}">{{ $message }}</p>

    @if ($description)
        <p class="mt-2 {{ $t['description'] }}">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
