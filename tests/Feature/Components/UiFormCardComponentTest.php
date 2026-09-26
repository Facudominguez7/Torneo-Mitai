<?php

// Behavioral tests for x-ui.form-card (T3, panel-admin-redesign).
//
// White card wrapper for the ~15 Panel/**/_form.blade.php partials, which
// today render a bare <div class="mb-5"> per field with text-white labels
// on a card with no real container. This component owns only the visual
// shell (card, section separators, sticky action bar) — the caller's own
// <form> element stays outside it, so form-card can also wrap read-only
// content without ever nesting a <form> inside another.

use Illuminate\Support\Facades\Blade;

function renderUiFormCard(string $attributes = '', string $slots = ''): string
{
    return Blade::render("<x-ui.form-card {$attributes}>{$slots}</x-ui.form-card>");
}

it('renders the default slot content', function () {
    $html = renderUiFormCard('', '<div data-testid="campo-nombre">Nombre</div>');

    expect($html)->toContain('data-testid="campo-nombre"');
});

it('renders the card shell classes', function () {
    $html = renderUiFormCard();

    expect($html)->toContain('max-w-3xl')
        ->and($html)->toContain('rounded-xl')
        ->and($html)->toContain('bg-white')
        ->and($html)->toContain('border-gray-200');
});

it('separates direct sections with a border instead of margins', function () {
    $html = renderUiFormCard('', '<div>Sección 1</div><div>Sección 2</div>');

    expect($html)->toContain('divide-y');
});

it('renders the actions slot inside a bg-gray-50 footer bar', function () {
    $slots = '<x-slot name="actions"><button data-testid="guardar">Guardar</button></x-slot>';
    $html = renderUiFormCard('', $slots);

    expect($html)->toContain('data-testid="guardar"')
        ->and($html)->toContain('bg-gray-50');
});

it('right-aligns the actions bar', function () {
    $slots = '<x-slot name="actions"><button>Guardar</button></x-slot>';
    $html = renderUiFormCard('', $slots);

    expect($html)->toContain('justify-end');
});

it('renders no footer bar markup when the actions slot is not used', function () {
    $html = renderUiFormCard();

    expect($html)->not->toContain('bg-gray-50');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiFormCard('class="mt-6"');

    expect($html)->toContain('mt-6')
        ->and($html)->toContain('max-w-3xl');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiFormCard('data-testid="form-equipo"');

    expect($html)->toContain('data-testid="form-equipo"');
});
