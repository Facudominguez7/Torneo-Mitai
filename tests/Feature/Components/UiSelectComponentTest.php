<?php

// Behavioral tests for x-ui.select (issue #110, Phase 1, T1.3).
//
// @tailwindcss/forms is active and already resets <select> styling; this
// component layers the design-system border/focus treatment on top of that
// reset rather than fighting it (see odd/tasks/design-system-tokens.md).

use Illuminate\Support\Facades\Blade;

function renderUiSelect(string $attributes = '', string $slot = '<option value="1">One</option>'): string
{
    return Blade::render("<x-ui.select {$attributes}>{$slot}</x-ui.select>");
}

it('renders a real select element with the slot as its options', function () {
    $html = renderUiSelect();

    expect($html)->toContain('<select')
        ->and($html)->toContain('<option value="1">One</option>');
});

it('renders an associated label when the label prop is given', function () {
    $html = renderUiSelect('label="Categoría" name="idCategoria" id="idCategoria"');

    expect($html)->toContain('<label')
        ->and($html)->toContain('for="idCategoria"')
        ->and($html)->toContain('Categoría')
        ->and($html)->toContain('id="idCategoria"');
});

it('generates a stable id from the name when no explicit id is given, so the label stays associated', function () {
    $html = renderUiSelect('label="Edición" name="idEdicion"');

    expect($html)->toContain('for="idEdicion"')
        ->and($html)->toContain('id="idEdicion"');
});

it('renders no label element when the label prop is omitted', function () {
    $html = renderUiSelect('name="idEquipo"');

    expect($html)->not->toContain('<label');
});

it('applies token-based classes on the panel tone', function () {
    $html = renderUiSelect('tone="panel"');

    expect($html)->toContain('border-gray-300')
        ->and($html)->toContain('rounded-md');
});

it('applies token-based classes on the portal tone', function () {
    $html = renderUiSelect('tone="portal"');

    expect($html)->toContain('bg-white/5')
        ->and($html)->toContain('border-white/20');
});

it('falls back to the panel tone for an unknown tone', function () {
    $html = renderUiSelect('tone="not-a-tone"');

    expect($html)->toContain('border-gray-300');
});

it('always exposes a focus-visible ring class regardless of tone', function () {
    $htmlPanel = renderUiSelect('tone="panel"');
    $htmlPortal = renderUiSelect('tone="portal"');

    expect($htmlPanel)->toContain('focus:ring')
        ->and($htmlPortal)->toContain('focus:ring');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiSelect('class="w-full"');

    expect($html)->toContain('w-full')
        ->and($html)->toContain('border-gray-300');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiSelect('data-testid="select-categoria" wire:model="idCategoria" required');

    expect($html)->toContain('data-testid="select-categoria"')
        ->and($html)->toContain('wire:model="idCategoria"')
        ->and($html)->toContain('required');
});

it('marks the field required in an accessible way when the required attribute is passed with a label', function () {
    $html = renderUiSelect('label="Equipo" name="idEquipo" required');

    expect($html)->toContain('required')
        ->and($html)->toContain('aria-required="true"');
});
