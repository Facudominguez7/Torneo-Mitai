<?php

// Behavioral tests for x-ui.button (issue #110, Phase 1, T1.1).
//
// LESSON carried over from Phase 0 (see odd/tasks/design-system-tokens.md):
// a test that reads a component file as text proves nothing about what it
// renders. Every assertion here renders the real component through Blade
// and inspects the produced HTML string, never the component's source.

use Illuminate\Support\Facades\Blade;

function renderUiButton(string $attributes = '', string $slot = 'Click me'): string
{
    return Blade::render("<x-ui.button {$attributes}>{$slot}</x-ui.button>");
}

it('renders as a button element by default, with a safe non-submitting type', function () {
    $html = renderUiButton();

    expect($html)->toContain('<button')
        ->and($html)->toContain('type="button"')
        ->and($html)->not->toContain('<a ');
});

it('lets a caller opt into type="submit" explicitly', function () {
    $html = renderUiButton('type="submit"');

    expect($html)->toContain('<button')
        ->and($html)->toContain('type="submit"');
});

it('renders as an anchor when href is passed', function () {
    $html = renderUiButton('href="/panel/equipos"');

    expect($html)->toContain('<a ')
        ->and($html)->toContain('href="/panel/equipos"')
        ->and($html)->not->toContain('<button');
});

it('maps each variant to token-based classes on the panel tone', function () {
    $expectations = [
        'primary' => 'bg-brand-navy',
        'secondary' => 'bg-brand-teal',
        'danger' => 'bg-red-600',
        'success' => 'bg-emerald-600',
    ];

    foreach ($expectations as $variant => $expectedClass) {
        $html = renderUiButton("variant=\"{$variant}\" tone=\"panel\"");

        expect($html)->toContain($expectedClass);
    }
});

it('maps each variant to token-based classes on the portal tone', function () {
    $expectations = [
        'primary' => 'from-brand-navy',
        'secondary' => 'bg-white/10',
        'danger' => 'bg-red-500/20',
        'success' => 'from-emerald-800',
    ];

    foreach ($expectations as $variant => $expectedClass) {
        $html = renderUiButton("variant=\"{$variant}\" tone=\"portal\"");

        expect($html)->toContain($expectedClass);
    }
});

it('renders each size with a distinct padding scale', function () {
    $expectations = [
        'sm' => 'py-1.5',
        'md' => 'py-2',
        'lg' => 'py-3',
    ];

    foreach ($expectations as $size => $expectedClass) {
        $html = renderUiButton("size=\"{$size}\"");

        expect($html)->toContain($expectedClass);
    }
});

it('defaults to the md size when none is given', function () {
    $html = renderUiButton();

    expect($html)->toContain('py-2');
});

it('expresses disabled as a real HTML attribute on a button element', function () {
    $html = renderUiButton('disabled');

    expect($html)->toContain('<button')
        ->and($html)->toContain('disabled')
        ->and($html)->toContain('disabled:opacity-50')
        ->and($html)->toContain('disabled:cursor-not-allowed');
});

it('renders a real disabled button instead of a disabled anchor when href and disabled are both passed', function () {
    $html = renderUiButton('href="/panel/equipos" :disabled="true"');

    expect($html)->toContain('<button')
        ->and($html)->toContain('disabled')
        ->and($html)->not->toContain('<a ')
        ->and($html)->not->toContain('href="/panel/equipos"');
});

it('always exposes a focus-visible ring class regardless of variant or tone', function () {
    $html = renderUiButton('variant="danger" tone="portal"');

    expect($html)->toContain('focus-visible:ring-2');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiButton('class="mt-4 w-full"');

    expect($html)->toContain('mt-4 w-full')
        ->and($html)->toContain('bg-brand-navy');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiButton('data-testid="save-team" aria-label="Save team" wire:click="save"');

    expect($html)->toContain('data-testid="save-team"')
        ->and($html)->toContain('aria-label="Save team"')
        ->and($html)->toContain('wire:click="save"');
});

it('falls back to the panel tone and primary variant for an unknown tone or variant', function () {
    $html = renderUiButton('variant="not-a-variant" tone="not-a-tone"');

    expect($html)->toContain('bg-brand-navy');
});
