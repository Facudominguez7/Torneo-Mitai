<?php

// Behavioral tests for x-ui.page-header (issue #110, Phase 1, T1.5).
//
// No shared header pattern exists in the codebase today (50 raw <h1> tags,
// each styled differently). This component establishes the convention:
// title, optional subtitle, optional actions slot. See
// odd/tasks/design-system-tokens.md for the evidence audit.

use Illuminate\Support\Facades\Blade;

function renderUiPageHeader(string $attributes = '', string $slots = ''): string
{
    return Blade::render("<x-ui.page-header {$attributes}>{$slots}</x-ui.page-header>");
}

it('renders the title prop inside a real heading element', function () {
    $html = renderUiPageHeader('title="Gestión de Instituciones"');

    expect($html)->toContain('<h1')
        ->and($html)->toContain('Gestión de Instituciones');
});

it('renders no subtitle element when the subtitle prop is omitted', function () {
    $html = renderUiPageHeader('title="Equipos"');

    expect($html)->not->toContain('<p');
});

it('renders the subtitle when given', function () {
    $html = renderUiPageHeader('title="Equipos" subtitle="Listado de equipos inscriptos"');

    expect($html)->toContain('Listado de equipos inscriptos');
});

it('renders the actions slot content when given', function () {
    $slots = '<x-slot name="actions"><button>Nuevo equipo</button></x-slot>';
    $html = renderUiPageHeader('title="Equipos"', $slots);

    expect($html)->toContain('Nuevo equipo');
});

it('renders no actions wrapper markup when the actions slot is not used', function () {
    $html = renderUiPageHeader('title="Equipos"');

    expect($html)->not->toContain('Nuevo equipo');
});

it('applies token-based classes on the panel tone', function () {
    $html = renderUiPageHeader('title="Equipos" tone="panel"');

    expect($html)->toContain('text-gray-800');
});

it('applies token-based classes on the portal tone', function () {
    $html = renderUiPageHeader('title="Fixture" tone="portal"');

    expect($html)->toContain('text-white');
});

it('falls back to the panel tone for an unknown tone', function () {
    $html = renderUiPageHeader('title="Equipos" tone="not-a-tone"');

    expect($html)->toContain('text-gray-800');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiPageHeader('title="Equipos" class="mb-6"');

    expect($html)->toContain('mb-6');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiPageHeader('title="Equipos" data-testid="page-header"');

    expect($html)->toContain('data-testid="page-header"');
});
