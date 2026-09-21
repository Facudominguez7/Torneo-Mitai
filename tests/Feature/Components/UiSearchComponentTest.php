<?php

// Behavioral tests for x-ui.search (issue #110, Phase 1, T1.4).
//
// Evidence is split: the Panel uses plain gray search inputs, the portal
// uses pill-shaped dark-glass inputs. The `tone` prop exists for exactly
// this (odd/tasks/design-system-tokens.md).

use Illuminate\Support\Facades\Blade;

function renderUiSearch(string $attributes = ''): string
{
    return Blade::render("<x-ui.search {$attributes} />");
}

it('renders a real search input', function () {
    $html = renderUiSearch('name="nombreEquipo"');

    expect($html)->toContain('<input')
        ->and($html)->toContain('type="search"')
        ->and($html)->toContain('name="nombreEquipo"');
});

it('requires an accessible name via the label prop, rendered visible by default', function () {
    $html = renderUiSearch('label="Buscar equipo" name="nombreEquipo"');

    expect($html)->toContain('<label')
        ->and($html)->toContain('Buscar equipo');
});

it('renders a visually-hidden label instead of no label when labelHidden is true', function () {
    $html = renderUiSearch('label="Buscar equipo" labelHidden name="nombreEquipo"');

    expect($html)->toContain('<label')
        ->and($html)->toContain('sr-only')
        ->and($html)->toContain('Buscar equipo');
});

it('falls back to aria-label when no label prop is given, so the control is never unlabeled', function () {
    $html = renderUiSearch('name="nombreEquipo" placeholder="Buscar equipo por nombre"');

    expect($html)->toContain('aria-label="Buscar equipo por nombre"');
});

it('applies token-based classes on the panel tone', function () {
    $html = renderUiSearch('tone="panel" label="Buscar" name="q"');

    expect($html)->toContain('border-gray-300')
        ->and($html)->toContain('rounded-lg');
});

it('applies token-based pill classes on the portal tone', function () {
    $html = renderUiSearch('tone="portal" label="Buscar" name="q"');

    expect($html)->toContain('rounded-full')
        ->and($html)->toContain('bg-white/5')
        ->and($html)->toContain('border-white/20');
});

it('falls back to the panel tone for an unknown tone', function () {
    $html = renderUiSearch('tone="not-a-tone" label="Buscar" name="q"');

    expect($html)->toContain('border-gray-300');
});

it('always exposes a focus-visible ring class regardless of tone', function () {
    $htmlPanel = renderUiSearch('tone="panel" label="Buscar" name="q"');
    $htmlPortal = renderUiSearch('tone="portal" label="Buscar" name="q"');

    expect($htmlPanel)->toContain('focus:ring')
        ->and($htmlPortal)->toContain('focus:ring');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiSearch('class="w-64" label="Buscar" name="q"');

    expect($html)->toContain('w-64')
        ->and($html)->toContain('border-gray-300');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiSearch('data-testid="search-equipo" wire:model.live="q" label="Buscar" name="q"');

    expect($html)->toContain('data-testid="search-equipo"')
        ->and($html)->toContain('wire:model.live="q"');
});

it('preserves the current value so the search stays populated after a GET round-trip', function () {
    $html = renderUiSearch('name="q" value="Boca" label="Buscar"');

    expect($html)->toContain('value="Boca"');
});
