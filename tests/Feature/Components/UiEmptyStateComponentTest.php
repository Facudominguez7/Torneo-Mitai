<?php

// Behavioral tests for x-ui.empty-state (issue #110, Phase 1, T1.6).
//
// No shared "no results" pattern exists in the codebase today (~15 scattered
// bare <p> messages, each styled differently). This component establishes
// the convention: a message, an optional description, an optional icon and
// an optional actions slot. See odd/tasks/design-system-tokens.md.

use Illuminate\Support\Facades\Blade;

function renderUiEmptyState(string $attributes = '', string $slots = ''): string
{
    return Blade::render("<x-ui.empty-state {$attributes}>{$slots}</x-ui.empty-state>");
}

it('renders the message prop', function () {
    $html = renderUiEmptyState('message="No se encontraron jugadores"');

    expect($html)->toContain('No se encontraron jugadores');
});

it('renders the description when given', function () {
    $html = renderUiEmptyState('message="No hay resultados" description="Probá con otros filtros"');

    expect($html)->toContain('Probá con otros filtros');
});

it('renders no description markup when the description prop is omitted', function () {
    $html = renderUiEmptyState('message="No hay resultados"');

    expect($html)->not->toContain('Probá con otros filtros');
});

it('renders the actions slot content when given', function () {
    $slots = '<x-slot name="actions"><button>Limpiar filtros</button></x-slot>';
    $html = renderUiEmptyState('message="No hay resultados"', $slots);

    expect($html)->toContain('Limpiar filtros');
});

it('renders a default icon unless the icon slot is overridden', function () {
    $html = renderUiEmptyState('message="No hay resultados"');

    expect($html)->toContain('<svg');
});

it('lets a caller override the icon slot', function () {
    $slots = '<x-slot name="icon"><svg data-testid="custom-icon"></svg></x-slot>';
    $html = renderUiEmptyState('message="No hay resultados"', $slots);

    expect($html)->toContain('data-testid="custom-icon"');
});

it('applies token-based classes on the panel tone', function () {
    $html = renderUiEmptyState('message="No hay resultados" tone="panel"');

    expect($html)->toContain('border-gray-200')
        ->and($html)->toContain('text-gray-500');
});

it('applies token-based classes on the portal tone', function () {
    $html = renderUiEmptyState('message="No hay resultados" tone="portal"');

    expect($html)->toContain('border-white/20')
        ->and($html)->toContain('text-white/60');
});

it('falls back to the panel tone for an unknown tone', function () {
    $html = renderUiEmptyState('message="No hay resultados" tone="not-a-tone"');

    expect($html)->toContain('border-gray-200');
});

it('is centered and uses a dashed border container regardless of tone', function () {
    $html = renderUiEmptyState('message="No hay resultados"');

    expect($html)->toContain('text-center')
        ->and($html)->toContain('border-dashed');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiEmptyState('message="No hay resultados" class="my-8"');

    expect($html)->toContain('my-8');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiEmptyState('message="No hay resultados" data-testid="empty-state"');

    expect($html)->toContain('data-testid="empty-state"');
});
