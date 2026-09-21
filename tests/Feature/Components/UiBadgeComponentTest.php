<?php

// Behavioral tests for x-ui.badge (issue #110, Phase 1, T1.2).
//
// Follows the x-ui.button contract: render through Blade, assert on the
// produced HTML, never on the component file's source (Phase 0 lesson).

use Illuminate\Support\Facades\Blade;

function renderUiBadge(string $attributes = '', string $slot = 'Verificado'): string
{
    return Blade::render("<x-ui.badge {$attributes}>{$slot}</x-ui.badge>");
}

it('renders as a span, a non-interactive status indicator', function () {
    $html = renderUiBadge();

    expect($html)->toContain('<span')
        ->and($html)->not->toContain('<button')
        ->and($html)->not->toContain('<a ')
        ->and($html)->not->toContain('<div');
});

it('maps each variant to token-based classes on the panel tone', function () {
    $expectations = [
        'neutral' => 'bg-gray-100',
        'success' => 'bg-green-100',
        'warning' => 'bg-yellow-100',
        'danger' => 'bg-red-100',
        'info' => 'bg-blue-100',
    ];

    foreach ($expectations as $variant => $expectedClass) {
        $html = renderUiBadge("variant=\"{$variant}\" tone=\"panel\"");

        expect($html)->toContain($expectedClass);
    }
});

it('maps each variant to token-based classes on the portal tone', function () {
    $expectations = [
        'neutral' => 'bg-white/10',
        'success' => 'bg-emerald-500/15',
        'warning' => 'bg-amber-400/15',
        'danger' => 'bg-rose-500/15',
        'info' => 'bg-brand-gold/15',
    ];

    foreach ($expectations as $variant => $expectedClass) {
        $html = renderUiBadge("variant=\"{$variant}\" tone=\"portal\"");

        expect($html)->toContain($expectedClass);
    }
});

it('falls back to the panel tone and neutral variant for an unknown tone or variant', function () {
    $html = renderUiBadge('variant="not-a-variant" tone="not-a-tone"');

    expect($html)->toContain('bg-gray-100');
});

it('always renders as a rounded pill regardless of tone', function () {
    $html = renderUiBadge('tone="portal"');

    expect($html)->toContain('rounded-full');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiBadge('class="ml-2"');

    expect($html)->toContain('ml-2')
        ->and($html)->toContain('bg-gray-100');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiBadge('data-testid="status-badge" aria-label="Estado verificado"');

    expect($html)->toContain('data-testid="status-badge"')
        ->and($html)->toContain('aria-label="Estado verificado"');
});

it('renders the slot content as the badge label', function () {
    $html = renderUiBadge('', 'Pendiente');

    expect($html)->toContain('Pendiente');
});
