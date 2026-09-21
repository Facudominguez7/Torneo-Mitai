<?php

// Behavioral tests for x-ui.filter-chip (issue #110, Phase 1, T1.7).
//
// Extracted from the real $clasesChip closure at
// resources/views/layouts/fixture.blade.php:71-77, reused at lines 102, 115,
// 132. The panel-tone treatment has no direct one-to-one precedent (the
// Panel uses select-based filter groups, not toggle chips) and follows the
// same tone-split convention as x-ui.button. See
// odd/tasks/design-system-tokens.md and the component's own docblock.

use Illuminate\Support\Facades\Blade;

function renderUiFilterChip(string $attributes = '', string $slot = 'Todas'): string
{
    return Blade::render("<x-ui.filter-chip {$attributes}>{$slot}</x-ui.filter-chip>");
}

it('renders as an anchor when href is given', function () {
    $html = renderUiFilterChip('href="/fixture?idFecha=todas"');

    expect($html)->toContain('<a ')
        ->and($html)->toContain('href="/fixture?idFecha=todas"');
});

it('renders as a button when href is omitted', function () {
    $html = renderUiFilterChip();

    expect($html)->toContain('<button')
        ->and($html)->toContain('type="button"')
        ->and($html)->not->toContain('<a ');
});

it('marks the active state with aria-current and active-specific classes on the portal tone', function () {
    $html = renderUiFilterChip('href="/fixture" :active="true" tone="portal"');

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('border-brand-gold')
        ->and($html)->toContain('bg-brand-gold/20');
});

it('renders the inactive classes and no aria-current on the portal tone by default', function () {
    $html = renderUiFilterChip('href="/fixture" tone="portal"');

    expect($html)->not->toContain('aria-current')
        ->and($html)->toContain('border-white/20')
        ->and($html)->toContain('text-white/70');
});

it('marks the active state with aria-current and active-specific classes on the panel tone', function () {
    $html = renderUiFilterChip(':active="true" tone="panel"');

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('bg-brand-navy')
        ->and($html)->toContain('text-white');
});

it('renders the inactive classes on the panel tone by default', function () {
    $html = renderUiFilterChip('tone="panel"');

    expect($html)->not->toContain('aria-current')
        ->and($html)->toContain('bg-gray-100')
        ->and($html)->toContain('text-gray-700');
});

it('falls back to the panel tone for an unknown tone', function () {
    $html = renderUiFilterChip('tone="not-a-tone"');

    expect($html)->toContain('bg-gray-100');
});

it('always renders as a rounded pill with a visible focus state regardless of tone or state', function () {
    $html = renderUiFilterChip('tone="portal" :active="true"');

    expect($html)->toContain('rounded-full')
        ->and($html)->toContain('focus-visible:ring-2');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiFilterChip('class="min-w-max"');

    expect($html)->toContain('min-w-max')
        ->and($html)->toContain('rounded-full');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiFilterChip('data-testid="chip-todas" wire:click="filtrar(\'todas\')"');

    expect($html)->toContain('data-testid="chip-todas"')
        ->and($html)->toContain('wire:click="filtrar(\'todas\')"');
});
