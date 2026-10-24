<?php

// Behavioral tests for x-ui.data-table (T3, panel-admin-redesign).
//
// Extracted from the ~20 hand-typed Panel/**/index.blade.php tables, each
// repeating the same bg-gray-200 header / border-collapse markup. This
// component centralizes the card shell, filters bar, header/body slots and
// empty/footer areas so a module only supplies its own <tr> rows.
//
// Naming note: the boolean `:empty` prop and the optional custom-empty-state
// slot are deliberately NOT both named "empty". Verified against real Blade
// behavior (Illuminate\View\Compilers\Concerns\CompilesComponents::compileProps):
// when a prop and a named slot share one name, the @props cleanup step
// either drops the boolean (replaced by the ComponentSlot object) or unsets
// the slot variable entirely, depending on whether the name is declared in
// @props — a genuine collision, not a hypothetical one. The slot is named
// `emptyState` instead to keep both usable at once.

use Illuminate\Support\Facades\Blade;

function renderUiDataTable(string $attributes = '', string $slots = ''): string
{
    return Blade::render("<x-ui.data-table {$attributes}>{$slots}</x-ui.data-table>");
}

it('renders the card shell classes', function () {
    $html = renderUiDataTable();

    expect($html)->toContain('rounded-xl')
        ->and($html)->toContain('border-gray-200')
        ->and($html)->toContain('bg-white')
        ->and($html)->toContain('overflow-hidden');
});

it('wraps the table in an overflow-x-auto container so it never breaks the layout below md', function () {
    $html = renderUiDataTable();

    expect($html)->toContain('overflow-x-auto');
});

it('renders the filters slot inside a bordered area when given', function () {
    $slots = '<x-slot name="filters"><form data-testid="filtros">campo</form></x-slot>';
    $html = renderUiDataTable('', $slots);

    expect($html)->toContain('data-testid="filtros"')
        ->and($html)->toContain('border-b');
});

it('renders no filters markup when the filters slot is not used', function () {
    $html = renderUiDataTable();

    expect($html)->not->toContain('data-testid="filtros"');
});

it('renders the head and body slots inside a real table', function () {
    $slots = '<x-slot name="head"><tr><th data-testid="col-nombre">Nombre</th></tr></x-slot>'
        .'<x-slot name="body"><tr><td data-testid="fila-1">Mitai</td></tr></x-slot>';
    $html = renderUiDataTable('', $slots);

    expect($html)->toContain('<table')
        ->and($html)->toContain('<thead')
        ->and($html)->toContain('<tbody')
        ->and($html)->toContain('data-testid="col-nombre"')
        ->and($html)->toContain('data-testid="fila-1"');
});

it('applies the header row style tokens', function () {
    $html = renderUiDataTable();

    expect($html)->toContain('bg-gray-50')
        ->and($html)->toContain('uppercase')
        ->and($html)->toContain('tracking-wide')
        ->and($html)->toContain('text-gray-600');
});

it('renders the default x-ui.empty-state when :empty is true and no custom emptyState slot is given', function () {
    $html = renderUiDataTable(':empty="true"');

    expect($html)->toContain('No existen registros')
        ->and($html)->toContain('border-dashed');
});

it('renders a custom emptyState slot instead of the default empty-state', function () {
    $slots = '<x-slot name="emptyState"><p data-testid="vacio-custom">Nada por acá</p></x-slot>';
    $html = renderUiDataTable(':empty="true"', $slots);

    expect($html)->toContain('data-testid="vacio-custom"')
        ->and($html)->not->toContain('No existen registros');
});

it('does not render the head/body slot content when :empty is true', function () {
    $slots = '<x-slot name="head"><tr><th data-testid="col-nombre">Nombre</th></tr></x-slot>'
        .'<x-slot name="body"><tr><td data-testid="fila-1">Mitai</td></tr></x-slot>';
    $html = renderUiDataTable(':empty="true"', $slots);

    expect($html)->not->toContain('data-testid="col-nombre"')
        ->and($html)->not->toContain('data-testid="fila-1"');
});

it('renders the footer slot inside a bordered area when given', function () {
    $slots = '<x-slot name="footer"><nav data-testid="paginacion">1 2 3</nav></x-slot>';
    $html = renderUiDataTable('', $slots);

    expect($html)->toContain('data-testid="paginacion"')
        ->and($html)->toContain('border-t');
});

it('renders no footer markup when the footer slot is not used', function () {
    $html = renderUiDataTable();

    expect($html)->not->toContain('data-testid="paginacion"');
});

it('merges caller-supplied classes with the component classes instead of replacing them', function () {
    $html = renderUiDataTable('class="mt-6"');

    expect($html)->toContain('mt-6')
        ->and($html)->toContain('rounded-xl');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiDataTable('data-testid="tabla-equipos"');

    expect($html)->toContain('data-testid="tabla-equipos"');
});

it('omits the footer area when the footer slot only contains whitespace', function () {
    $html = renderUiDataTable('', "<x-slot name=\"footer\">\n    \n</x-slot>");

    expect($html)->not->toContain('border-t border-gray-200 px-5 py-4');
});
